<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241001085915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_s10 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s100 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s120 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s130 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s140 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s150 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s160 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s170 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s40 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s50 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s60 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s70 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_s80 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, adressep_1 VARCHAR(255) DEFAULT NULL, adressep_2 VARCHAR(255) DEFAULT NULL, cpostalp VARCHAR(255) DEFAULT NULL, villep VARCHAR(255) DEFAULT NULL, rib VARCHAR(255) DEFAULT NULL, contact_site VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, id_contact VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE contact');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, prenom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, adressep_1 VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, adressep_2 VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, cpostalp VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, villep VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, rib VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, contact_site VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, telephone VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, id_contact VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE contact_s10');
        $this->addSql('DROP TABLE contact_s100');
        $this->addSql('DROP TABLE contact_s120');
        $this->addSql('DROP TABLE contact_s130');
        $this->addSql('DROP TABLE contact_s140');
        $this->addSql('DROP TABLE contact_s150');
        $this->addSql('DROP TABLE contact_s160');
        $this->addSql('DROP TABLE contact_s170');
        $this->addSql('DROP TABLE contact_s40');
        $this->addSql('DROP TABLE contact_s50');
        $this->addSql('DROP TABLE contact_s60');
        $this->addSql('DROP TABLE contact_s70');
        $this->addSql('DROP TABLE contact_s80');
    }
}
