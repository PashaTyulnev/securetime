<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124154822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45BB1A0722');
        $this->addSql('DROP INDEX IDX_A50B3B45BB1A0722 ON shift');
        $this->addSql('ALTER TABLE shift CHANGE details_id demand_shift_id INT NOT NULL');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B4544D0AC90 FOREIGN KEY (demand_shift_id) REFERENCES demand_shift (id)');
        $this->addSql('CREATE INDEX IDX_A50B3B4544D0AC90 ON shift (demand_shift_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B4544D0AC90');
        $this->addSql('DROP INDEX IDX_A50B3B4544D0AC90 ON shift');
        $this->addSql('ALTER TABLE shift CHANGE demand_shift_id details_id INT NOT NULL');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45BB1A0722 FOREIGN KEY (details_id) REFERENCES demand_shift (id)');
        $this->addSql('CREATE INDEX IDX_A50B3B45BB1A0722 ON shift (details_id)');
    }
}
