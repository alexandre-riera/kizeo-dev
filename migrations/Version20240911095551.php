<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240911095551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD photo_deformation_chassis VARCHAR(255) DEFAULT NULL, ADD photo_deformation_levre VARCHAR(255) DEFAULT NULL, ADD photo_fissure_cordon VARCHAR(255) DEFAULT NULL, ADD photo_joue VARCHAR(255) DEFAULT NULL, ADD photo_butoir VARCHAR(255) DEFAULT NULL, ADD photo_vantail VARCHAR(255) DEFAULT NULL, ADD photo_linteau VARCHAR(255) DEFAULT NULL, ADD photo_bariere VARCHAR(255) DEFAULT NULL, ADD photo_tourniquet VARCHAR(255) DEFAULT NULL, ADD photo_sas VARCHAR(255) DEFAULT NULL, ADD photo_marquage_au_sol_portail VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP photo_deformation_chassis, DROP photo_deformation_levre, DROP photo_fissure_cordon, DROP photo_joue, DROP photo_butoir, DROP photo_vantail, DROP photo_linteau, DROP photo_bariere, DROP photo_tourniquet, DROP photo_sas, DROP photo_marquage_au_sol_portail');
    }
}
