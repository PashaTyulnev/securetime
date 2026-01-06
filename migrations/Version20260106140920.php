<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106140920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partner_company (id INT AUTO_INCREMENT NOT NULL, address_id INT DEFAULT NULL, logo_link VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, INDEX IDX_88556A52F5B7AF75 (address_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE partner_company ADD CONSTRAINT FK_88556A52F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE employee ADD partner_company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A18D6A1930 FOREIGN KEY (partner_company_id) REFERENCES partner_company (id)');
        $this->addSql('CREATE INDEX IDX_5D9F75A18D6A1930 ON employee (partner_company_id)');
        $this->addSql('ALTER TABLE shift CHANGE status status VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A18D6A1930');
        $this->addSql('ALTER TABLE partner_company DROP FOREIGN KEY FK_88556A52F5B7AF75');
        $this->addSql('DROP TABLE partner_company');
        $this->addSql('DROP INDEX IDX_5D9F75A18D6A1930 ON employee');
        $this->addSql('ALTER TABLE employee DROP partner_company_id');
        $this->addSql('ALTER TABLE shift CHANGE status status VARCHAR(255) DEFAULT \'pending\' NOT NULL');
    }
}
