<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121144013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facility ADD active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE time_tracker ADD CONSTRAINT FK_F8E48BC9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE time_tracker ADD CONSTRAINT FK_F8E48BC9727ACA70 FOREIGN KEY (parent_id) REFERENCES time_tracker (id)');
        $this->addSql('CREATE INDEX IDX_F8E48BC9A76ED395 ON time_tracker (user_id)');
        $this->addSql('CREATE INDEX IDX_F8E48BC9727ACA70 ON time_tracker (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_tracker DROP FOREIGN KEY FK_F8E48BC9A76ED395');
        $this->addSql('ALTER TABLE time_tracker DROP FOREIGN KEY FK_F8E48BC9727ACA70');
        $this->addSql('DROP INDEX IDX_F8E48BC9A76ED395 ON time_tracker');
        $this->addSql('DROP INDEX IDX_F8E48BC9727ACA70 ON time_tracker');
        $this->addSql('ALTER TABLE facility DROP active');
    }
}
