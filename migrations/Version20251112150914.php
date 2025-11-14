<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112150914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift ADD on_monday TINYINT(1) NOT NULL, ADD on_tuesday TINYINT(1) NOT NULL, ADD on_wednesday TINYINT(1) NOT NULL, ADD on_thursday TINYINT(1) NOT NULL, ADD on_friday TINYINT(1) NOT NULL, ADD on_saturday TINYINT(1) NOT NULL, ADD on_sunday TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demand_shift DROP on_monday, DROP on_tuesday, DROP on_wednesday, DROP on_thursday, DROP on_friday, DROP on_saturday, DROP on_sunday');
    }
}
