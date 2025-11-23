<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123155647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demand_shift_facility_position (demand_shift_id INT NOT NULL, facility_position_id INT NOT NULL, INDEX IDX_5B24AF944D0AC90 (demand_shift_id), INDEX IDX_5B24AF99BE336E6 (facility_position_id), PRIMARY KEY(demand_shift_id, facility_position_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demand_shift_facility_position ADD CONSTRAINT FK_5B24AF944D0AC90 FOREIGN KEY (demand_shift_id) REFERENCES demand_shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demand_shift_facility_position ADD CONSTRAINT FK_5B24AF99BE336E6 FOREIGN KEY (facility_position_id) REFERENCES facility_position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demand_shift ADD CONSTRAINT FK_3A6A1D403826FD0F FOREIGN KEY (required_qualification_id) REFERENCES qualification (id)');
        $this->addSql('CREATE INDEX IDX_3A6A1D403826FD0F ON demand_shift (required_qualification_id)');
        $this->addSql('ALTER TABLE facility_position DROP FOREIGN KEY FK_102F5D7A44D0AC90');
        $this->addSql('DROP INDEX IDX_102F5D7A44D0AC90 ON facility_position');
        $this->addSql('ALTER TABLE facility_position DROP demand_shift_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift_facility_position DROP FOREIGN KEY FK_5B24AF944D0AC90');
        $this->addSql('ALTER TABLE demand_shift_facility_position DROP FOREIGN KEY FK_5B24AF99BE336E6');
        $this->addSql('DROP TABLE demand_shift_facility_position');
        $this->addSql('ALTER TABLE demand_shift DROP FOREIGN KEY FK_3A6A1D403826FD0F');
        $this->addSql('DROP INDEX IDX_3A6A1D403826FD0F ON demand_shift');
        $this->addSql('ALTER TABLE facility_position ADD demand_shift_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facility_position ADD CONSTRAINT FK_102F5D7A44D0AC90 FOREIGN KEY (demand_shift_id) REFERENCES demand_shift (id)');
        $this->addSql('CREATE INDEX IDX_102F5D7A44D0AC90 ON facility_position (demand_shift_id)');
    }
}
