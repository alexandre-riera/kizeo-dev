<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240613110034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD id_contact VARCHAR(255) DEFAULT NULL, CHANGE numero_equipement numero_equipement VARCHAR(255) NOT NULL, CHANGE nature nature VARCHAR(255) DEFAULT NULL, CHANGE mode_fonctionnement mode_fonctionnement VARCHAR(255) DEFAULT NULL, CHANGE repere_site_client repere_site_client VARCHAR(255) DEFAULT NULL, CHANGE mise_en_service mise_en_service DATE DEFAULT NULL, CHANGE numero_de_serie numero_de_serie VARCHAR(255) DEFAULT NULL, CHANGE marque marque VARCHAR(255) DEFAULT NULL, CHANGE hauteur hauteur VARCHAR(255) DEFAULT NULL, CHANGE largeur largeur VARCHAR(255) DEFAULT NULL, CHANGE plaque_signaletique plaque_signaletique VARCHAR(255) DEFAULT NULL, CHANGE etat etat VARCHAR(255) DEFAULT NULL, CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP id_contact, CHANGE numero_equipement numero_equipement INT NOT NULL, CHANGE nature nature VARCHAR(255) NOT NULL, CHANGE mode_fonctionnement mode_fonctionnement VARCHAR(255) NOT NULL, CHANGE repere_site_client repere_site_client VARCHAR(255) NOT NULL, CHANGE mise_en_service mise_en_service DATE NOT NULL, CHANGE numero_de_serie numero_de_serie VARCHAR(255) NOT NULL, CHANGE marque marque VARCHAR(255) NOT NULL, CHANGE hauteur hauteur VARCHAR(255) NOT NULL, CHANGE largeur largeur VARCHAR(255) NOT NULL, CHANGE plaque_signaletique plaque_signaletique VARCHAR(255) NOT NULL, CHANGE etat etat VARCHAR(255) NOT NULL, CHANGE trigramme_tech trigramme_tech VARCHAR(3) NOT NULL');
    }
}
