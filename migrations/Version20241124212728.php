<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241124212728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 CHANGE trigramme_tech trigramme_tech VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 CHANGE trigramme_tech trigramme_tech VARCHAR(3) DEFAULT NULL');
    }
}
