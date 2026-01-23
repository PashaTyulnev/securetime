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
            
            // Finde den Hauptcheckin (Schichtbeginn)
            $mainCheckin = $this->timeTrackerService->getLastMainCheckin($user);
            
            if (!$mainCheckin) {
                $io->warning(sprintf(
                    'Kein Hauptcheckin für Benutzer %s gefunden. Überspringe...',
                    $user->getUsername()
                ));
                continue;
            }
            
            $mainCheckinTimestamp = $mainCheckin->getTimestamp();
            $lastCheckinTimestamp = $checkinEntry->getTimestamp();

            // Berechne die Arbeitszeit seit dem Hauptcheckin in Minuten
            $workingMinutes = ($now->getTimestamp() - $mainCheckinTimestamp->getTimestamp()) / 60;
            $workingHours = $workingMinutes / 60;

            // Prüfe ob die maximale Arbeitszeit überschritten wurde
            if ($workingMinutes > self::MAX_WORKING_MINUTES) {
                // Berechne Checkout-Timestamp: Hauptcheckin + 12,5 Stunden
                $checkoutTimestamp = $mainCheckinTimestamp->add(new \DateInterval('PT' . self::MAX_WORKING_MINUTES . 'M'));
                
                // Stelle sicher, dass der Checkout nach dem letzten Checkin liegt
                // (wichtig nach Pausen, wenn jemand wieder eingecheckt hat)
                // Mindestens 1 Minute Abstand, damit nachgetragene Pausen genug Platz haben
                $minCheckoutAfterCheckin = 1; // Mindestens 1 Minute nach letztem Checkin
                $minCheckoutTimestamp = $lastCheckinTimestamp->add(new \DateInterval(sprintf('PT%dM', $minCheckoutAfterCheckin)));
                
                if ($checkoutTimestamp <= $minCheckoutTimestamp) {
                    // Wenn der berechnete Checkout zu nah am letzten Checkin ist,
                    // setze ihn auf mindestens 1 Minute nach dem letzten Checkin
                    $checkoutTimestamp = $minCheckoutTimestamp;
                }
                
                $io->warning(sprintf(
                    'Benutzer %s arbeitet seit %.2f Stunden (%.0f Minuten). Automatisches Auschecken zum Zeitpunkt: %s...',
                    $user->getUsername(),
                    $workingHours,
                    $workingMinutes,
                    $checkoutTimestamp->format('Y-m-d H:i:s')
                ));

                try {
                    // Automatisches Auschecken mit spezifischem Timestamp (Hauptcheckin + 12,5 Stunden)
                    $this->timeTrackerService->checkout($user, $checkoutTimestamp);

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
                    $io->success(sprintf(
                        'Benutzer %s wurde automatisch ausgecheckt (Checkout-Zeitpunkt: %s).',
                        $user->getUsername(),
                        $checkoutTimestamp->format('Y-m-d H:i:s')
                    ));
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
