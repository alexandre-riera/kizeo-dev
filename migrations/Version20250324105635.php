<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250324105635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat_s10 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s100 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s120 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s130 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s140 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s150 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s160 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s40 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s50 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s60 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s70 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contrat_s80 CHANGE date_signature date_signature VARCHAR(255) NOT NULL, CHANGE date_resiliation date_resiliation VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat_s10 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s100 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s120 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s130 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s140 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s150 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s160 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s40 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s50 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s60 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s70 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s80 CHANGE date_signature date_signature DATE NOT NULL, CHANGE date_resiliation date_resiliation DATE DEFAULT NULL');
    }
}
