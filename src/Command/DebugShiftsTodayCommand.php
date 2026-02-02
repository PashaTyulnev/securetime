<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;
use App\Service\EmployeeService;
use App\Service\ShiftService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Debug: Warum werden Schichten „heute“ für einen User nicht angezeigt?
 * Prüft User, Employee, PartnerCompany, Shift-Daten und findShiftsForToday-Logik.
 */
#[AsCommand(
    name: 'app:debug-shifts-today',
    description: 'Debug shifts for today for a given username (e.g. fbafkari)',
)]
class DebugShiftsTodayCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly ShiftRepository $shiftRepository,
        private readonly ShiftService $shiftService,
        private readonly EmployeeService $employeeService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username to check (e.g. fbafkari)', 'fbafkari');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getOption('user');

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User with username '{$username}' not found.");
            return Command::FAILURE;
        }

        $io->title("Debug: Shifts today for user '{$username}' (id={$user->getId()})");

        $employee = $user->getEmployee();
        if (!$employee) {
            $io->warning("User '{$username}' has no linked Employee. Dashboard needs User -> Employee.");
            return Command::SUCCESS;
        }

        $io->section('User / Employee');
        $io->listing([
            'user_id: ' . $user->getId(),
            'employee_id: ' . $employee->getId(),
            'employee: ' . $employee->getFullName(),
            'partner_company: ' . ($employee->getPartnerCompany() ? $employee->getPartnerCompany()->getId() . ' (' . ($employee->getPartnerCompany()->getTitle() ?? 'N/A') . ')' : 'NULL'),
        ]);

        $tz = new \DateTimeZone('Europe/Berlin');
        $todayBerlin = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $todayStr = $todayBerlin->format('Y-m-d');

        $io->section('Date used for "today"');
        $io->listing([
            'Timezone: Europe/Berlin',
            'Today (Berlin date): ' . $todayStr,
        ]);

        // Raw: shifts for this employee on this date (any)
        $shiftsForEmployeeToday = $this->shiftRepository->createQueryBuilder('s')
            ->andWhere('s.employee = :employee')
            ->andWhere('s.date = :today')
            ->setParameter('employee', $employee)
            ->setParameter('today', $todayBerlin)
            ->getQuery()
            ->getResult();

        $io->section('Shifts in DB: employee_id=' . $employee->getId() . ', date=' . $todayStr);
        if (\count($shiftsForEmployeeToday) === 0) {
            $io->warning('No shifts found in DB for this employee on date ' . $todayStr . '. Check shift.date storage (timezone?).');
            $conn = $this->shiftRepository->getEntityManager()->getConnection();
            $rows = $conn->fetchAllAssociative('SELECT id, employee_id, date::text, demand_shift_id FROM shift WHERE employee_id = ? ORDER BY id DESC LIMIT 5', [$employee->getId()]);
            if (\count($rows) > 0) {
                $io->note('Sample shifts for this employee (latest 5 by id):');
                $io->table(array_keys($rows[0]), array_map(fn ($r) => array_values($r), $rows));
            } else {
                $io->note('No shifts at all for this employee.');
            }
        } else {
            $io->success(\count($shiftsForEmployeeToday) . ' shift(s) found for this employee today.');
            foreach ($shiftsForEmployeeToday as $s) {
                $ds = $s->getDemandShift();
                $io->listing([
                    'shift_id: ' . $s->getId(),
                    'date: ' . ($s->getDate() ? $s->getDate()->format('Y-m-d') : 'null'),
                    'time_from: ' . ($ds?->getTimeFrom()?->format('H:i') ?? 'null'),
                    'time_to: ' . ($ds?->getTimeTo()?->format('H:i') ?? 'null'),
                ]);
            }
        }

        // How many employees are in "getAllEmployeeEntities(true)" for this user's partner company?
        // When partner company is null, findAll(true, null) returns ALL employees (no filter).
        $partnerCompany = $employee->getPartnerCompany();
        $employeesInScope = $this->employeeRepository->findAll(true, $partnerCompany);
        $employeeIdsInScope = array_map(fn ($e) => $e->getId(), $employeesInScope);
        $inList = \in_array($employee->getId(), $employeeIdsInScope, true);

        $io->section('Employee list used by findShiftsForToday (same partner company or all if null)');
        $io->listing([
            'Partner company: ' . ($partnerCompany ? (string) $partnerCompany->getId() . ' (' . ($partnerCompany->getTitle() ?? '') . ')' : 'NULL (all employees)'),
            'Number of employees in scope: ' . \count($employeesInScope),
            'Is employee ' . $employee->getId() . ' in list? ' . ($inList ? 'YES' : 'NO'),
        ]);
        if (!$inList) {
            $io->warning('This employee is NOT in the list. findShiftsForToday only loads shifts for employees in scope. Check employee.partner_company_id or filters.');
        }

        // Simulate findShiftsForToday: we need to call ShiftService with Security that returns this user
        // We cannot easily impersonate in console. So we run the same repository call manually.
        $todayShiftsFromRepo = $this->shiftRepository->findShiftsForToday($employeesInScope, $tz);
        $todayShiftsForThisUser = [];
        foreach ($todayShiftsFromRepo as $shift) {
            $emp = $shift->getEmployee();
            if ($emp && $emp->getUser() && $emp->getUser()->getId() === $user->getId()) {
                $todayShiftsForThisUser[] = $shift;
            }
        }

        $io->section('Result of findShiftsForToday (simulated with same employee list)');
        $io->listing([
            'Total today shifts (all employees in scope): ' . \count($todayShiftsFromRepo),
            'Shifts for user ' . $username . ': ' . \count($todayShiftsForThisUser),
        ]);

        if (\count($todayShiftsForThisUser) === 0 && \count($shiftsForEmployeeToday) > 0) {
            $io->error('Conflict: DB has shifts for this employee today, but findShiftsForToday returns none. Likely cause: employee not in getAllEmployeeEntities list (partner company filter).');
        }

        return Command::SUCCESS;
    }
}
