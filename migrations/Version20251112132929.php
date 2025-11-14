<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112132929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demand_shift (id INT AUTO_INCREMENT NOT NULL, facility_id INT DEFAULT NULL, valid_from DATE DEFAULT NULL, valid_to DATE DEFAULT NULL, time_from TIME NOT NULL, time_to TIME NOT NULL, time_to_is_next_day TINYINT(1) NOT NULL, amount_employees INT NOT NULL, INDEX IDX_3A6A1D40A7014910 (facility_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demand_shift ADD CONSTRAINT FK_3A6A1D40A7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift DROP FOREIGN KEY FK_3A6A1D40A7014910');
        $this->addSql('DROP TABLE demand_shift');
    }
}
