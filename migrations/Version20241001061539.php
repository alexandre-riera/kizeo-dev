<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241001061539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s100 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD is_etat_des_lieux_fait TINYINT(1) DEFAULT NULL, ADD is_en_maintenance TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s100 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s50 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
        $this->addSql('ALTER TABLE equipement_s80 DROP is_etat_des_lieux_fait, DROP is_en_maintenance');
    }
}
