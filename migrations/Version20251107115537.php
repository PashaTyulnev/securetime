<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107115537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company RENAME INDEX uniq_81398e09f5b7af75 TO UNIQ_4FBF094FF5B7AF75');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E6389395C3F3');
        $this->addSql('DROP INDEX IDX_4C62E6389395C3F3 ON contact');
        $this->addSql('ALTER TABLE contact CHANGE customer_id company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('CREATE INDEX IDX_4C62E638979B1AD6 ON contact (company_id)');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B29395C3F3');
        $this->addSql('DROP INDEX IDX_105994B29395C3F3 ON facility');
        $this->addSql('ALTER TABLE facility CHANGE customer_id company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B2979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('CREATE INDEX IDX_105994B2979B1AD6 ON facility (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company RENAME INDEX uniq_4fbf094ff5b7af75 TO UNIQ_81398E09F5B7AF75');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E638979B1AD6');
        $this->addSql('DROP INDEX IDX_4C62E638979B1AD6 ON contact');
        $this->addSql('ALTER TABLE contact CHANGE company_id customer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E6389395C3F3 FOREIGN KEY (customer_id) REFERENCES company (id)');
        $this->addSql('CREATE INDEX IDX_4C62E6389395C3F3 ON contact (customer_id)');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B2979B1AD6');
        $this->addSql('DROP INDEX IDX_105994B2979B1AD6 ON facility');
        $this->addSql('ALTER TABLE facility CHANGE company_id customer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B29395C3F3 FOREIGN KEY (customer_id) REFERENCES company (id)');
        $this->addSql('CREATE INDEX IDX_105994B29395C3F3 ON facility (customer_id)');
    }
}
