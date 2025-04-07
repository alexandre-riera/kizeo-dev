<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250407122137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contrat (id INT AUTO_INCREMENT NOT NULL, numero_contrat INT NOT NULL, date_signature DATE NOT NULL, duree VARCHAR(255) NOT NULL, tacite_reconduction TINYINT(1) NOT NULL, valorisation VARCHAR(255) NOT NULL, nombre_equipement INT NOT NULL, nombre_visite INT NOT NULL, date_resiliation DATE DEFAULT NULL, statut VARCHAR(255) NOT NULL, date_previsionnelle_1 VARCHAR(255) DEFAULT NULL, date_previsionnelle_2 VARCHAR(255) DEFAULT NULL, date_effective_1 VARCHAR(255) DEFAULT NULL, date_effective_2 VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE form ADD photo_etiquette_somafi VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE contrat');
        $this->addSql('ALTER TABLE form DROP photo_etiquette_somafi');
    }
}
