<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241001090804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE societe_s100 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s120 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s130 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s140 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s150 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s160 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s170 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s40 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s50 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s60 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s70 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE societe_s80 (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) NOT NULL, adressep_1 VARCHAR(255) NOT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) NOT NULL, villep VARCHAR(255) NOT NULL, siret VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, id_societe VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE societe');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE societe (id INT AUTO_INCREMENT NOT NULL, raison_sociale VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, adressep_1 VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, adressep_2 VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, cpostalp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, villep VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, siret VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, telephone VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, id_societe VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE societe_s100');
        $this->addSql('DROP TABLE societe_s120');
        $this->addSql('DROP TABLE societe_s130');
        $this->addSql('DROP TABLE societe_s140');
        $this->addSql('DROP TABLE societe_s150');
        $this->addSql('DROP TABLE societe_s160');
        $this->addSql('DROP TABLE societe_s170');
        $this->addSql('DROP TABLE societe_s40');
        $this->addSql('DROP TABLE societe_s50');
        $this->addSql('DROP TABLE societe_s60');
        $this->addSql('DROP TABLE societe_s70');
        $this->addSql('DROP TABLE societe_s80');
    }
}
