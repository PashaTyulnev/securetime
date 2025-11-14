<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112122257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shift (id INT AUTO_INCREMENT NOT NULL, facility_id INT DEFAULT NULL, employee_id INT DEFAULT NULL, date_time_from DATETIME NOT NULL, date_time_to DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_A50B3B45A7014910 (facility_id), INDEX IDX_A50B3B458C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45A7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B458C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45A7014910');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B458C03F15C');
        $this->addSql('DROP TABLE shift');
    }
}
