<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210144140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift CHANGE is_on_call is_on_call TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE shift ADD status VARCHAR(255) NOT NULL, ADD is_on_call TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift CHANGE is_on_call is_on_call TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shift DROP status, DROP is_on_call');
    }
}
