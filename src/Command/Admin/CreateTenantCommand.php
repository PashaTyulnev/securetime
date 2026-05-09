<?php

namespace App\Command\Admin;

use App\Entity\Admin\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin:create-tenant',
    description: 'Legt einen neuen Tenant in der Admin-Datenbank an',
)]
class CreateTenantCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $adminEntityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subdomain', InputArgument::REQUIRED, 'Subdomain des Tenants (z.B. demo)')
            ->addArgument('name', InputArgument::REQUIRED, 'Anzeigename des Tenants (z.B. "Demo Kunde")')
            ->addArgument('databaseName', InputArgument::REQUIRED, 'Name der Tenant-Datenbank (z.B. securetime_demo)')
            ->addOption('database-url', null, InputOption::VALUE_OPTIONAL, 'Vollständige Datenbank-URL (überschreibt databaseName)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subdomain    = $input->getArgument('subdomain');
        $name         = $input->getArgument('name');
        $databaseName = $input->getArgument('databaseName');
        $databaseUrl  = $input->getOption('database-url');

        $existing = $this->adminEntityManager->getRepository(Tenant::class)->findOneBy(['subdomain' => $subdomain]);
        if ($existing !== null) {
            $io->error(sprintf('Tenant mit Subdomain "%s" existiert bereits.', $subdomain));
            return Command::FAILURE;
        }

        $tenant = new Tenant();
        $tenant->setSubdomain($subdomain);
        $tenant->setName($name);
        $tenant->setDatabaseName($databaseName);
        $tenant->setDatabaseUrl($databaseUrl);
        $tenant->setIsActive(true);

        $this->adminEntityManager->persist($tenant);
        $this->adminEntityManager->flush();

        $io->success(sprintf('Tenant "%s" (Subdomain: %s) wurde erfolgreich angelegt.', $name, $subdomain));
        $io->table(
            ['ID', 'Name', 'Subdomain', 'Datenbank', 'Aktiv', 'Erstellt am'],
            [[
                $tenant->getId(),
                $tenant->getName(),
                $tenant->getSubdomain(),
                $tenant->getDatabaseName(),
                $tenant->isActive() ? 'Ja' : 'Nein',
                $tenant->getCreatedAt()->format('d.m.Y H:i:s'),
            ]]
        );

        return Command::SUCCESS;
    }
}
