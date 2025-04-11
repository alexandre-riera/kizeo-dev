<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250411073211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form ADD photo_choc_tablier_porte VARCHAR(255) DEFAULT NULL, ADD photo_choc_tablier VARCHAR(255) DEFAULT NULL, ADD photo_axe VARCHAR(255) DEFAULT NULL, ADD photo_serrure VARCHAR(255) DEFAULT NULL, ADD photo_serrure1 VARCHAR(255) DEFAULT NULL, ADD photo_feux VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form DROP photo_choc_tablier_porte, DROP photo_choc_tablier, DROP photo_axe, DROP photo_serrure, DROP photo_serrure1, DROP photo_feux');
    }
}
