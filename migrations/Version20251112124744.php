<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112124744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE qualification (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, rank INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE employee ADD qualification_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A11A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id)');
        $this->addSql('CREATE INDEX IDX_5D9F75A11A75EE38 ON employee (qualification_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A11A75EE38');
        $this->addSql('DROP TABLE qualification');
        $this->addSql('DROP INDEX IDX_5D9F75A11A75EE38 ON employee');
        $this->addSql('ALTER TABLE employee DROP qualification_id');
    }
}
