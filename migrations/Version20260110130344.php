<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110130344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demand_shift_partner_company (demand_shift_id INT NOT NULL, partner_company_id INT NOT NULL, INDEX IDX_5048BC44D0AC90 (demand_shift_id), INDEX IDX_5048BC8D6A1930 (partner_company_id), PRIMARY KEY(demand_shift_id, partner_company_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demand_shift_partner_company ADD CONSTRAINT FK_5048BC44D0AC90 FOREIGN KEY (demand_shift_id) REFERENCES demand_shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demand_shift_partner_company ADD CONSTRAINT FK_5048BC8D6A1930 FOREIGN KEY (partner_company_id) REFERENCES partner_company (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift_partner_company DROP FOREIGN KEY FK_5048BC44D0AC90');
        $this->addSql('ALTER TABLE demand_shift_partner_company DROP FOREIGN KEY FK_5048BC8D6A1930');
        $this->addSql('DROP TABLE demand_shift_partner_company');
    }
}
