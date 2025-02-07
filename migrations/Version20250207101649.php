<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250207101649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contacts_cc (id INT AUTO_INCREMENT NOT NULL, id_contact VARCHAR(255) NOT NULL, raison_sociale_contact VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE files_cc (id INT AUTO_INCREMENT NOT NULL, id_contact_cc_id INT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, INDEX IDX_C76D26063B217F25 (id_contact_cc_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE files_cc ADD CONSTRAINT FK_C76D26063B217F25 FOREIGN KEY (id_contact_cc_id) REFERENCES contacts_cc (id)');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE files_cc DROP FOREIGN KEY FK_C76D26063B217F25');
        $this->addSql('DROP TABLE contacts_cc');
        $this->addSql('DROP TABLE files_cc');
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
