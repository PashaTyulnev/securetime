<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116135939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift ADD required_qualification_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demand_shift ADD CONSTRAINT FK_3A6A1D403826FD0F FOREIGN KEY (required_qualification_id) REFERENCES qualification (id)');
        $this->addSql('CREATE INDEX IDX_3A6A1D403826FD0F ON demand_shift (required_qualification_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift DROP FOREIGN KEY FK_3A6A1D403826FD0F');
        $this->addSql('DROP INDEX IDX_3A6A1D403826FD0F ON demand_shift');
        $this->addSql('ALTER TABLE demand_shift DROP required_qualification_id');
    }
}
