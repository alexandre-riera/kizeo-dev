<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240923121002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s100 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 ADD longueur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD longueur VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s100 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s120 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s130 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s140 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s150 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s160 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s170 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s40 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s50 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s60 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s70 DROP longueur');
        $this->addSql('ALTER TABLE equipement_s80 DROP longueur');
    }
}
