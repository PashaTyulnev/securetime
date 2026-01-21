<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121154814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_tracker ADD facility_id INT DEFAULT NULL, ADD time_deviation INT DEFAULT NULL');
        $this->addSql('ALTER TABLE time_tracker ADD CONSTRAINT FK_F8E48BC9A7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
        $this->addSql('CREATE INDEX IDX_F8E48BC9A7014910 ON time_tracker (facility_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_tracker DROP FOREIGN KEY FK_F8E48BC9A7014910');
        $this->addSql('DROP INDEX IDX_F8E48BC9A7014910 ON time_tracker');
        $this->addSql('ALTER TABLE time_tracker DROP facility_id, DROP time_deviation');
    }
}
