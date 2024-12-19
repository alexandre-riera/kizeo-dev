<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240911121633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD date_enregistrement VARCHAR(255) DEFAULT NULL, DROP date_previsionnelle_visite_1, DROP date_previsionnelle_visite_2, DROP date_effective_1, DROP date_effective_2');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD date_previsionnelle_visite_2 VARCHAR(255) DEFAULT NULL, ADD date_effective_1 VARCHAR(255) DEFAULT NULL, ADD date_effective_2 VARCHAR(255) DEFAULT NULL, CHANGE date_enregistrement date_previsionnelle_visite_1 VARCHAR(255) DEFAULT NULL');
    }
}
