<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241001090203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE societe_s10 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, siret VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE societe_s10');
    }
}
