<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123112126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facility_position ADD demand_shift_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facility_position ADD CONSTRAINT FK_102F5D7A44D0AC90 FOREIGN KEY (demand_shift_id) REFERENCES demand_shift (id)');
        $this->addSql('CREATE INDEX IDX_102F5D7A44D0AC90 ON facility_position (demand_shift_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facility_position DROP FOREIGN KEY FK_102F5D7A44D0AC90');
        $this->addSql('DROP INDEX IDX_102F5D7A44D0AC90 ON facility_position');
        $this->addSql('ALTER TABLE facility_position DROP demand_shift_id');
    }
}
