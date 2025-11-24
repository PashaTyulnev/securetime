<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124154617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45A7014910');
        $this->addSql('DROP INDEX IDX_A50B3B45A7014910 ON shift');
        $this->addSql('ALTER TABLE shift ADD details_id INT NOT NULL, DROP facility_id, DROP date_time_from, DROP date_time_to');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45BB1A0722 FOREIGN KEY (details_id) REFERENCES demand_shift (id)');
        $this->addSql('CREATE INDEX IDX_A50B3B45BB1A0722 ON shift (details_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45BB1A0722');
        $this->addSql('DROP INDEX IDX_A50B3B45BB1A0722 ON shift');
        $this->addSql('ALTER TABLE shift ADD facility_id INT DEFAULT NULL, ADD date_time_from DATETIME NOT NULL, ADD date_time_to DATETIME NOT NULL, DROP details_id');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45A7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
        $this->addSql('CREATE INDEX IDX_A50B3B45A7014910 ON shift (facility_id)');
    }
}
