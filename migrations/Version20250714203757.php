<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714203757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s10 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s100 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s120 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s130 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s140 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s150 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s160 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s170 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s40 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s50 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s60 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s70 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s80 ADD email VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s10 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s100 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s120 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s130 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s140 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s150 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s160 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s170 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s40 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s50 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s60 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s70 DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_s80 DROP email
        SQL);
    }
}
