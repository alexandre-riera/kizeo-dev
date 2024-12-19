<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240925140544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s100 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s120 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s130 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s140 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s150 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s160 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s170 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s40 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s50 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s60 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s70 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s80 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
    }
}
