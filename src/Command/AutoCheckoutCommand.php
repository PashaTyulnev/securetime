<?php

namespace App\Command;

use App\Entity\TimeTracker;
use App\Repository\TimeTrackerRepository;
use App\Service\TimeTracker\TimeTrackerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auto-checkout',
    description: 'Prüft ob Benutzer länger als 12,5 Stunden eingecheckt sind und checkt sie automatisch aus'
)]
class AutoCheckoutCommand extends Command
{
    private const MAX_WORKING_HOURS = 12.5;
    private const MAX_WORKING_MINUTES = 750; // 12.5 * 60

    public function __construct(
        private readonly TimeTrackerRepository $timeTrackerRepository,
        private readonly TimeTrackerService $timeTrackerService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Automatisches Auschecken nach 12,5 Stunden');

        // Finde alle eingecheckten Benutzer
        $checkedInEntries = $this->timeTrackerRepository->findCheckedInUsers();

        if (empty($checkedInEntries)) {
            $io->success('Keine eingecheckten Benutzer gefunden.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Gefunden: %d eingecheckte Benutzer', count($checkedInEntries)));

        $autoCheckedOutCount = 0;
        $now = new \DateTimeImmutable();

        foreach ($checkedInEntries as $checkinEntry) {
            $user = $checkinEntry->getUser();
            $checkinTimestamp = $checkinEntry->getTimestamp();

            // Berechne die Arbeitszeit in Minuten
            $workingMinutes = ($now->getTimestamp() - $checkinTimestamp->getTimestamp()) / 60;
            $workingHours = $workingMinutes / 60;

            // Prüfe ob die maximale Arbeitszeit überschritten wurde
            if ($workingMinutes > self::MAX_WORKING_MINUTES) {
                $io->warning(sprintf(
                    'Benutzer %s arbeitet seit %.2f Stunden (%.0f Minuten). Automatisches Auschecken...',
                    $user->getUsername(),
                    $workingHours,
                    $workingMinutes
                ));

                try {
                    // Automatisches Auschecken
                    $this->timeTrackerService->checkout($user);

                    // Setze den autoCheckOut Flag auf dem Checkout-Eintrag
                    // Der Checkout-Eintrag ist der neueste Eintrag für diesen Benutzer
                    $checkoutEntry = $this->timeTrackerRepository->findOneBy(
                        ['user' => $user, 'type' => 'checkout'],
                        ['id' => 'DESC']
                    );

                    if ($checkoutEntry) {
                        $checkoutEntry->setAutoCheckOut(true);
                        $this->entityManager->persist($checkoutEntry);
                        $this->entityManager->flush();
                    }

                    $autoCheckedOutCount++;
                    $io->success(sprintf('Benutzer %s wurde automatisch ausgecheckt.', $user->getUsername()));
                } catch (\Exception $e) {
                    $io->error(sprintf(
                        'Fehler beim automatischen Auschecken von %s: %s',
                        $user->getUsername(),
                        $e->getMessage()
                    ));
                }
            } else {
                $io->comment(sprintf(
                    'Benutzer %s arbeitet seit %.2f Stunden (noch %.2f Stunden bis zum Limit)',
                    $user->getUsername(),
                    $workingHours,
                    self::MAX_WORKING_HOURS - $workingHours
                ));
            }
        }

        if ($autoCheckedOutCount > 0) {
            $io->success(sprintf('%d Benutzer wurden automatisch ausgecheckt.', $autoCheckedOutCount));
        } else {
            $io->success('Keine Benutzer mussten automatisch ausgecheckt werden.');
        }

        return Command::SUCCESS;
    }
}
