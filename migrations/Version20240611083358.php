<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240611083358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipement (id INT AUTO_INCREMENT NOT NULL, numero_equipement INT NOT NULL, nature VARCHAR(255) NOT NULL, mode_fonctionnement VARCHAR(255) NOT NULL, repere_site_client VARCHAR(255) NOT NULL, mise_en_service DATE NOT NULL, numero_de_serie VARCHAR(255) NOT NULL, marque VARCHAR(255) NOT NULL, hauteur VARCHAR(255) NOT NULL, largeur VARCHAR(255) NOT NULL, plaque_signaletique VARCHAR(255) NOT NULL, anomalies LONGTEXT DEFAULT NULL, etat VARCHAR(255) NOT NULL, dernière_visite DATE DEFAULT NULL, trigramme_tech VARCHAR(3) NOT NULL, date_previsionnelle_visite_1 DATE DEFAULT NULL, date_previsionnelle_visite_2 DATE DEFAULT NULL, date_effective_1 DATE DEFAULT NULL, date_effective_2 DATE DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE equipement');
    }
}
