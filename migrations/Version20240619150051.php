<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240619150051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement CHANGE dernière_visite dernière_visite VARCHAR(255) DEFAULT NULL, CHANGE date_previsionnelle_visite_1 date_previsionnelle_visite_1 VARCHAR(255) DEFAULT NULL, CHANGE date_previsionnelle_visite_2 date_previsionnelle_visite_2 VARCHAR(255) DEFAULT NULL, CHANGE date_effective_1 date_effective_1 VARCHAR(255) DEFAULT NULL, CHANGE date_effective_2 date_effective_2 VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement CHANGE dernière_visite dernière_visite DATE DEFAULT NULL, CHANGE date_previsionnelle_visite_1 date_previsionnelle_visite_1 DATE DEFAULT NULL, CHANGE date_previsionnelle_visite_2 date_previsionnelle_visite_2 DATE DEFAULT NULL, CHANGE date_effective_1 date_effective_1 DATE DEFAULT NULL, CHANGE date_effective_2 date_effective_2 DATE DEFAULT NULL');
    }
}
