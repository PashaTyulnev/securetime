<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120121205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE facility_position (id INT AUTO_INCREMENT NOT NULL, facility_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, note VARCHAR(255) DEFAULT NULL, INDEX IDX_102F5D7AA7014910 (facility_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE facility_position ADD CONSTRAINT FK_102F5D7AA7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facility_position DROP FOREIGN KEY FK_102F5D7AA7014910');
        $this->addSql('DROP TABLE facility_position');
    }
}
