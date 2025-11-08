<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105080902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, street VARCHAR(255) NOT NULL, zip VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, country VARCHAR(255) NOT NULL, longitude DOUBLE PRECISION DEFAULT NULL, altitude DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, facility_id INT DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, INDEX IDX_4C62E6389395C3F3 (customer_id), INDEX IDX_4C62E638A7014910 (facility_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, address_id INT DEFAULT NULL, number VARCHAR(255) NOT NULL, company VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_81398E09F5B7AF75 (address_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facility (id INT AUTO_INCREMENT NOT NULL, address_id INT NOT NULL, customer_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, short_title VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, approach VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_105994B2F5B7AF75 (address_id), INDEX IDX_105994B29395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E6389395C3F3 FOREIGN KEY (customer_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638A7014910 FOREIGN KEY (facility_id) REFERENCES facility (id)');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_81398E09F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B2F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE facility ADD CONSTRAINT FK_105994B29395C3F3 FOREIGN KEY (customer_id) REFERENCES company (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E6389395C3F3');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E638A7014910');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_81398E09F5B7AF75');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B2F5B7AF75');
        $this->addSql('ALTER TABLE facility DROP FOREIGN KEY FK_105994B29395C3F3');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE facility');
    }
}
