<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250318082632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD remplace_par VARCHAR(255) DEFAULT NULL, ADD numero_identification VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s100 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s120 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s130 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s140 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s150 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s160 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s170 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s40 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s50 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s60 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s70 DROP remplace_par, DROP numero_identification');
        $this->addSql('ALTER TABLE equipement_s80 DROP remplace_par, DROP numero_identification');
    }
}
