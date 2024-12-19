<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241115142107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_s10 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s100 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s120 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s130 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s140 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s150 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s160 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s170 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s40 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s60 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s70 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_s80 ADD raison_sociale VARCHAR(255) DEFAULT NULL, ADD id_societe VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_s10 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s100 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s120 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s130 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s140 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s150 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s160 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s170 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s40 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s60 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s70 DROP raison_sociale, DROP id_societe');
        $this->addSql('ALTER TABLE contact_s80 DROP raison_sociale, DROP id_societe');
    }
}
