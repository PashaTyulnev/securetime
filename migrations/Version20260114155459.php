<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114155459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE time_tracker (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', timestamp DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', type VARCHAR(255) NOT NULL, note LONGTEXT DEFAULT NULL, is_shift_start TINYINT(1) NOT NULL, INDEX IDX_F8E48BC9A76ED395 (user_id), INDEX IDX_F8E48BC9727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE time_tracker ADD CONSTRAINT FK_F8E48BC9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE time_tracker ADD CONSTRAINT FK_F8E48BC9727ACA70 FOREIGN KEY (parent_id) REFERENCES time_tracker (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_tracker DROP FOREIGN KEY FK_F8E48BC9A76ED395');
        $this->addSql('ALTER TABLE time_tracker DROP FOREIGN KEY FK_F8E48BC9727ACA70');
        $this->addSql('DROP TABLE time_tracker');
    }
}
