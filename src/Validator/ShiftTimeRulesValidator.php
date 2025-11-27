<?php

namespace App\Validator;

use App\Entity\Shift;
use App\Repository\ShiftRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ShiftTimeRulesValidator extends ConstraintValidator
{
    private const MINIMUM_REST_HOURS = 11;

    public function __construct(
        private readonly ShiftRepository $shiftRepository
    ) {
    }

    public function validate(mixed $shift, Constraint $constraint): void
    {
        if (!$shift instanceof Shift) {
            return;
        }

        $employee = $shift->getEmployee();
        $existingShiftsOfSameDay = $this->getExistingShiftsForEmployee($employee, $shift->getDate());
        $existingShiftsOfPreviousDay = $this->getExistingShiftsForEmployee($employee, (clone $shift->getDate())->modify('-1 day'));
        $existingShiftsOfNextDay = $this->getExistingShiftsForEmployee($employee, (clone $shift->getDate())->modify('+1 day'));

        $newShiftTimeframe = $this->createShiftTimeframe($shift);

        foreach ($existingShiftsOfSameDay as $existingShiftSameDay) {
            $existingShiftTimeframe = $this->createShiftTimeframe($existingShiftSameDay);

            if ($this->shiftsOverlap($newShiftTimeframe, $existingShiftTimeframe)) {
                $this->addOverlapViolation($employee);
                return;
            }

            if ($this->violatesRestPeriodAfterExisting($newShiftTimeframe, $existingShiftTimeframe)) {
                $this->addRestPeriodViolation($employee, 'nach');
                return;
            }

            if ($this->violatesRestPeriodBeforeExisting($newShiftTimeframe, $existingShiftTimeframe)) {
                $this->addRestPeriodViolation($employee, 'vor');
                return;
            }
        }

        // Check previous day's shifts for rest period violations
        foreach ($existingShiftsOfPreviousDay as $existingShiftPreviousDay) {
            //check was overnight
            $demandShiftOvernight = $this->demandShiftIsOvernight($existingShiftPreviousDay->getDemandShift());

            if($demandShiftOvernight){

                $existingShiftTimeframe = $this->createShiftTimeframe($existingShiftPreviousDay);
                if ($this->violatesRestPeriodAfterExisting($newShiftTimeframe, $existingShiftTimeframe)) {
                    $this->addRestPeriodViolation($employee, 'nach');
                    return;
                }
            }

        }

        foreach ($existingShiftsOfNextDay as $existingShiftNextDay) {
            //check was overnight
            $demandShiftOvernight = $this->demandShiftIsOvernight($shift->getDemandShift());

            if($demandShiftOvernight){

                $existingShiftTimeframe = $this->createShiftTimeframe($existingShiftNextDay);
                if ($this->violatesRestPeriodBeforeExisting($newShiftTimeframe, $existingShiftTimeframe)) {
                    $this->addRestPeriodViolation($employee, 'vor');
                    return;
                }
            }
        }
    }

    private function getExistingShiftsForEmployee($employee, \DateTime $date): array
    {
        return $this->shiftRepository->findByEmployeeAndDate($employee, $date);
    }

    private function createShiftTimeframe(Shift $shift): array
    {
        $date = $shift->getDate();
        $demandShift = $shift->getDemandShift();

        return $this->normalizeShiftTimes(
            $date,
            $demandShift->getTimeFrom(),
            $demandShift->getTimeTo()
        );
    }

    private function normalizeShiftTimes(\DateTime $date, \DateTime $from, \DateTime $to): array
    {
        $start = $this->createDateTimeFromTime($date, $from);
        $end = $this->createDateTimeFromTime($date, $to);

        if ($this->isNightShift($from, $to)) {
            $end->modify('+1 day');
        }

        return ['start' => $start, 'end' => $end];
    }

    private function createDateTimeFromTime(\DateTime $date, \DateTime $time): \DateTime
    {
        return (clone $date)->setTime(
            (int)$time->format('H'),
            (int)$time->format('i'),
            0
        );
    }

    private function isNightShift(\DateTime $from, \DateTime $to): bool
    {
        return $to < $from;
    }

    private function shiftsOverlap(array $newShift, array $existingShift): bool
    {
        return $newShift['start'] < $existingShift['end']
            && $newShift['end'] > $existingShift['start'];
    }

    private function violatesRestPeriodAfterExisting(array $newShift, array $existingShift): bool
    {
        $minimumStartTime = $this->calculateMinimumRestTime($existingShift['end']);

        return $newShift['start'] < $minimumStartTime;
    }

    private function violatesRestPeriodBeforeExisting(array $newShift, array $existingShift): bool
    {
        $minimumRestTimeAfterNewShift = $this->calculateMinimumRestTime($newShift['end']);

        return $minimumRestTimeAfterNewShift > $existingShift['start'];
    }

    private function calculateMinimumRestTime(\DateTime $shiftEnd): \DateTime
    {
        $restInterval = new \DateInterval('PT' . self::MINIMUM_REST_HOURS . 'H');

        return (clone $shiftEnd)->add($restInterval);
    }

    private function addOverlapViolation($employee): void
    {
        $employeeName = $this->getEmployeeFullName($employee);

        $this->context
            ->buildViolation("{$employeeName} hat bereits eine Schicht in diesem Zeitraum")
            ->addViolation();
    }

    private function addRestPeriodViolation($employee, string $direction): void
    {
        $employeeName = $this->getEmployeeFullName($employee);

        $this->context
            ->buildViolation("{$employeeName} muss mindestens 11 Stunden Ruhezeit {$direction} der anderen Schicht haben")
            ->addViolation();
    }

    private function getEmployeeFullName($employee): string
    {
        return $employee->getFirstName() . ' ' . $employee->getLastName();
    }

    private function demandShiftIsOvernight($getDemandShift): bool
    {
        return $getDemandShift->getTimeTo() < $getDemandShift->getTimeFrom();
    }
}
