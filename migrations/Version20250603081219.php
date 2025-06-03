<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250603081219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s10 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s100 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s120 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s130 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s140 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s150 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s160 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s170 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s40 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s50 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s60 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s70 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s80 ADD is_archive TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE form ADD photo_compte_rendu VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD reset_token VARCHAR(100) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s10 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s100 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s120 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s130 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s140 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s150 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s160 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s170 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s40 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s50 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s60 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s70 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE equipement_s80 DROP is_archive
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE form DROP photo_compte_rendu
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP reset_token, DROP reset_token_expires_at
        SQL);
    }
}
