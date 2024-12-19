<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612092731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact CHANGE nom nom VARCHAR(255) DEFAULT NULL, CHANGE prenom prenom VARCHAR(255) DEFAULT NULL, CHANGE adressep_1 adressep_1 VARCHAR(255) DEFAULT NULL, CHANGE cpostalp cpostalp VARCHAR(255) DEFAULT NULL, CHANGE villep villep VARCHAR(255) DEFAULT NULL, CHANGE contact_site contact_site VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE adressep_1 adressep_1 VARCHAR(255) NOT NULL, CHANGE cpostalp cpostalp VARCHAR(255) NOT NULL, CHANGE villep villep VARCHAR(255) NOT NULL, CHANGE contact_site contact_site VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) NOT NULL');
    }
}
