<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240911094724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD photo_plaque VARCHAR(255) DEFAULT NULL, ADD photo_choc VARCHAR(255) DEFAULT NULL, ADD photo_choc_montant VARCHAR(255) DEFAULT NULL, ADD photo_panneau_intermediaire VARCHAR(255) DEFAULT NULL, ADD photo_panneau_bas_inter_ext VARCHAR(255) DEFAULT NULL, ADD photo_lame_basse_int_ext VARCHAR(255) DEFAULT NULL, ADD photo_lame_intermediaire_int VARCHAR(255) DEFAULT NULL, ADD photo_environnement_equipement VARCHAR(255) DEFAULT NULL, ADD photo_bache VARCHAR(255) DEFAULT NULL, ADD photo_marquage_au_sol VARCHAR(255) DEFAULT NULL, ADD photo_environnement_eclairage VARCHAR(255) DEFAULT NULL, ADD photo_coffret_de_commande VARCHAR(255) DEFAULT NULL, ADD photo_carte VARCHAR(255) DEFAULT NULL, ADD photo_rail VARCHAR(255) DEFAULT NULL, ADD photo_equerre_rail VARCHAR(255) DEFAULT NULL, ADD photo_fixation_coulisse VARCHAR(255) DEFAULT NULL, ADD photo_moteur VARCHAR(255) DEFAULT NULL, ADD photo_deformation_plateau VARCHAR(255) DEFAULT NULL, ADD photo_deformation_plaque VARCHAR(255) DEFAULT NULL, ADD photo_deformation_structure VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP photo_plaque, DROP photo_choc, DROP photo_choc_montant, DROP photo_panneau_intermediaire, DROP photo_panneau_bas_inter_ext, DROP photo_lame_basse_int_ext, DROP photo_lame_intermediaire_int, DROP photo_environnement_equipement, DROP photo_bache, DROP photo_marquage_au_sol, DROP photo_environnement_eclairage, DROP photo_coffret_de_commande, DROP photo_carte, DROP photo_rail, DROP photo_equerre_rail, DROP photo_fixation_coulisse, DROP photo_moteur, DROP photo_deformation_plateau, DROP photo_deformation_plaque, DROP photo_deformation_structure');
    }
}
