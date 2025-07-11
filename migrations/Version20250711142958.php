<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250711142958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s10 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_5569176F422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s100 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_2983CAFF422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s120 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_1BB5A87D422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s130 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_2AE993C422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s140 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_4DEF0FFB422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s150 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_54F43EBA422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s160 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_7FD96D79422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s170 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_66C25C38422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s40 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_281EE32A422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s50 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_3105D26B422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s60 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_1A2881A8422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s70 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_333B0E9422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail_s80 (id INT AUTO_INCREMENT NOT NULL, id_contact_id INT NOT NULL, pdf_filename VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', pdf_url VARCHAR(255) NOT NULL, is_pdf_sent TINYINT(1) NOT NULL, sender VARCHAR(255) NOT NULL, INDEX IDX_84ABAC26422BA59D (id_contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s10 ADD CONSTRAINT FK_5569176F422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s10 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s100 ADD CONSTRAINT FK_2983CAFF422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s100 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s120 ADD CONSTRAINT FK_1BB5A87D422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s120 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s130 ADD CONSTRAINT FK_2AE993C422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s130 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s140 ADD CONSTRAINT FK_4DEF0FFB422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s140 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s150 ADD CONSTRAINT FK_54F43EBA422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s150 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s160 ADD CONSTRAINT FK_7FD96D79422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s160 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s170 ADD CONSTRAINT FK_66C25C38422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s170 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s40 ADD CONSTRAINT FK_281EE32A422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s40 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s50 ADD CONSTRAINT FK_3105D26B422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s50 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s60 ADD CONSTRAINT FK_1A2881A8422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s60 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s70 ADD CONSTRAINT FK_333B0E9422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s70 (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s80 ADD CONSTRAINT FK_84ABAC26422BA59D FOREIGN KEY (id_contact_id) REFERENCES contact_s80 (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s10 DROP FOREIGN KEY FK_5569176F422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s100 DROP FOREIGN KEY FK_2983CAFF422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s120 DROP FOREIGN KEY FK_1BB5A87D422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s130 DROP FOREIGN KEY FK_2AE993C422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s140 DROP FOREIGN KEY FK_4DEF0FFB422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s150 DROP FOREIGN KEY FK_54F43EBA422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s160 DROP FOREIGN KEY FK_7FD96D79422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s170 DROP FOREIGN KEY FK_66C25C38422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s40 DROP FOREIGN KEY FK_281EE32A422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s50 DROP FOREIGN KEY FK_3105D26B422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s60 DROP FOREIGN KEY FK_1A2881A8422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s70 DROP FOREIGN KEY FK_333B0E9422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail_s80 DROP FOREIGN KEY FK_84ABAC26422BA59D
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s10
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s100
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s120
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s130
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s140
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s150
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s160
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s170
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s40
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s50
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s60
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s70
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail_s80
        SQL);
    }
}
