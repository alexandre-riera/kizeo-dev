<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer les tables du système URL Shortener SOMAFI
 */
final class Version20250121100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables short_urls et short_url_accesses pour le système URL Shortener SOMAFI';
    }

    public function up(Schema $schema): void
    {
        // Création de la table principale des URLs courtes
        $this->addSql('CREATE TABLE short_urls (
            id INT AUTO_INCREMENT NOT NULL,
            token VARCHAR(20) NOT NULL UNIQUE,
            original_url TEXT NOT NULL,
            agence VARCHAR(10) NOT NULL,
            id_contact INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_active BOOLEAN DEFAULT true NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_token_expires_active (token, expires_at, is_active),
            INDEX idx_agence_contact (agence, id_contact),
            INDEX idx_expires_at (expires_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Création de la table des accès pour les statistiques
        $this->addSql('CREATE TABLE short_url_accesses (
            id INT AUTO_INCREMENT NOT NULL,
            token VARCHAR(20) NOT NULL,
            accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT DEFAULT NULL,
            referer VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_token_accessed (token, accessed_at),
            INDEX idx_accessed_at (accessed_at),
            CONSTRAINT FK_short_url_accesses_token FOREIGN KEY (token) REFERENCES short_urls (token) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE short_url_accesses');
        $this->addSql('DROP TABLE short_urls');
    }
}