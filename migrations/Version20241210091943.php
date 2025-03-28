<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241210091943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form ADD photo_plaque VARCHAR(255) DEFAULT NULL, ADD photo_choc VARCHAR(255) DEFAULT NULL, ADD photo_choc_montant VARCHAR(255) DEFAULT NULL, ADD photo_panneau_intermediaire_i VARCHAR(255) DEFAULT NULL, ADD photo_panneau_bas_inter_ext VARCHAR(255) DEFAULT NULL, ADD photo_lame_basse__int_ext VARCHAR(255) DEFAULT NULL, ADD photo_lame_intermediaire_int_ VARCHAR(255) DEFAULT NULL, ADD photo_envirronement_eclairage VARCHAR(255) DEFAULT NULL, ADD photo_bache VARCHAR(255) DEFAULT NULL, ADD photo_marquage_au_sol VARCHAR(255) DEFAULT NULL, ADD photo_environnement_equipement1 VARCHAR(255) DEFAULT NULL, ADD photo_coffret_de_commande VARCHAR(255) DEFAULT NULL, ADD photo_carte VARCHAR(255) DEFAULT NULL, ADD photo_rail VARCHAR(255) DEFAULT NULL, ADD photo_equerre_rail VARCHAR(255) DEFAULT NULL, ADD photo_fixation_coulisse VARCHAR(255) DEFAULT NULL, ADD photo_moteur VARCHAR(255) DEFAULT NULL, ADD photo_deformation_plateau VARCHAR(255) DEFAULT NULL, ADD photo_deformation_plaque VARCHAR(255) DEFAULT NULL, ADD photo_deformation_structure VARCHAR(255) DEFAULT NULL, ADD photo_deformation_chassis VARCHAR(255) DEFAULT NULL, ADD photo_deformation_levre VARCHAR(255) DEFAULT NULL, ADD photo_fissure_cordon VARCHAR(255) DEFAULT NULL, ADD photo_joue VARCHAR(255) DEFAULT NULL, ADD photo_butoir VARCHAR(255) DEFAULT NULL, ADD photo_vantail VARCHAR(255) DEFAULT NULL, ADD photo_linteau VARCHAR(255) DEFAULT NULL, ADD photo_barriere VARCHAR(255) DEFAULT NULL, ADD photo_tourniquet VARCHAR(255) DEFAULT NULL, ADD photo_sas VARCHAR(255) DEFAULT NULL, ADD photo_marquage_au_sol_ VARCHAR(255) DEFAULT NULL, ADD photo_marquage_au_sol_2 VARCHAR(255) DEFAULT NULL, ADD photo_2 VARCHAR(255) DEFAULT NULL');
        
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form DROP photo_plaque, DROP photo_choc, DROP photo_choc_montant, DROP photo_panneau_intermediaire_i, DROP photo_panneau_bas_inter_ext, DROP photo_lame_basse__int_ext, DROP photo_lame_intermediaire_int_, DROP photo_envirronement_eclairage, DROP photo_bache, DROP photo_marquage_au_sol, DROP photo_environnement_equipement1, DROP photo_coffret_de_commande, DROP photo_carte, DROP photo_rail, DROP photo_equerre_rail, DROP photo_fixation_coulisse, DROP photo_moteur, DROP photo_deformation_plateau, DROP photo_deformation_plaque, DROP photo_deformation_structure, DROP photo_deformation_chassis, DROP photo_deformation_levre, DROP photo_fissure_cordon, DROP photo_joue, DROP photo_butoir, DROP photo_vantail, DROP photo_linteau, DROP photo_barriere, DROP photo_tourniquet, DROP photo_sas, DROP photo_marquage_au_sol_, DROP photo_marquage_au_sol_2, DROP photo_2');
        
    }
}
