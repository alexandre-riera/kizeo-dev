<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241117104127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 ADD visite VARCHAR(255)  DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD visite VARCHAR(255)  DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 DROP visite');
        $this->addSql('ALTER TABLE equipement_s100 DROP visite');
        $this->addSql('ALTER TABLE equipement_s120 DROP visite');
        $this->addSql('ALTER TABLE equipement_s130 DROP visite');
        $this->addSql('ALTER TABLE equipement_s140 DROP visite');
        $this->addSql('ALTER TABLE equipement_s150 DROP visite');
        $this->addSql('ALTER TABLE equipement_s160 DROP visite');
        $this->addSql('ALTER TABLE equipement_s170 DROP visite');
        $this->addSql('ALTER TABLE equipement_s40 DROP visite');
        $this->addSql('ALTER TABLE equipement_s50 DROP visite');
        $this->addSql('ALTER TABLE equipement_s60 DROP visite');
        $this->addSql('ALTER TABLE equipement_s70 DROP visite');
        $this->addSql('ALTER TABLE equipement_s80 DROP visite');
    }
}
