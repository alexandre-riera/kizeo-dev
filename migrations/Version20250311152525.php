<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250311152525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat_s10 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s10 ADD CONSTRAINT FK_AFB67E65E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s10 (id)');
        $this->addSql('CREATE INDEX IDX_AFB67E65E7A1254A ON contrat_s10 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s100 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s100 ADD CONSTRAINT FK_C9ACFC88E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s100 (id)');
        $this->addSql('CREATE INDEX IDX_C9ACFC88E7A1254A ON contrat_s100 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s120 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s120 ADD CONSTRAINT FK_FB9A9E0AE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s120 (id)');
        $this->addSql('CREATE INDEX IDX_FB9A9E0AE7A1254A ON contrat_s120 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s130 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s130 ADD CONSTRAINT FK_E281AF4BE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s130 (id)');
        $this->addSql('CREATE INDEX IDX_E281AF4BE7A1254A ON contrat_s130 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s140 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s140 ADD CONSTRAINT FK_ADC0398CE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s140 (id)');
        $this->addSql('CREATE INDEX IDX_ADC0398CE7A1254A ON contrat_s140 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s150 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s150 ADD CONSTRAINT FK_B4DB08CDE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s150 (id)');
        $this->addSql('CREATE INDEX IDX_B4DB08CDE7A1254A ON contrat_s150 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s160 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s160 ADD CONSTRAINT FK_9FF65B0EE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s160 (id)');
        $this->addSql('CREATE INDEX IDX_9FF65B0EE7A1254A ON contrat_s160 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s170 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s170 ADD CONSTRAINT FK_86ED6A4FE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s170 (id)');
        $this->addSql('CREATE INDEX IDX_86ED6A4FE7A1254A ON contrat_s170 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s40 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s40 ADD CONSTRAINT FK_D2C18A20E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s40 (id)');
        $this->addSql('CREATE INDEX IDX_D2C18A20E7A1254A ON contrat_s40 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s50 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s50 ADD CONSTRAINT FK_CBDABB61E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s50 (id)');
        $this->addSql('CREATE INDEX IDX_CBDABB61E7A1254A ON contrat_s50 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s60 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s60 ADD CONSTRAINT FK_E0F7E8A2E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s60 (id)');
        $this->addSql('CREATE INDEX IDX_E0F7E8A2E7A1254A ON contrat_s60 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s70 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s70 ADD CONSTRAINT FK_F9ECD9E3E7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s70 (id)');
        $this->addSql('CREATE INDEX IDX_F9ECD9E3E7A1254A ON contrat_s70 (contact_id)');
        $this->addSql('ALTER TABLE contrat_s80 ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat_s80 ADD CONSTRAINT FK_7E74C52CE7A1254A FOREIGN KEY (contact_id) REFERENCES contact_s80 (id)');
        $this->addSql('CREATE INDEX IDX_7E74C52CE7A1254A ON contrat_s80 (contact_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat_s10 DROP FOREIGN KEY FK_AFB67E65E7A1254A');
        $this->addSql('DROP INDEX IDX_AFB67E65E7A1254A ON contrat_s10');
        $this->addSql('ALTER TABLE contrat_s10 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s100 DROP FOREIGN KEY FK_C9ACFC88E7A1254A');
        $this->addSql('DROP INDEX IDX_C9ACFC88E7A1254A ON contrat_s100');
        $this->addSql('ALTER TABLE contrat_s100 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s120 DROP FOREIGN KEY FK_FB9A9E0AE7A1254A');
        $this->addSql('DROP INDEX IDX_FB9A9E0AE7A1254A ON contrat_s120');
        $this->addSql('ALTER TABLE contrat_s120 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s130 DROP FOREIGN KEY FK_E281AF4BE7A1254A');
        $this->addSql('DROP INDEX IDX_E281AF4BE7A1254A ON contrat_s130');
        $this->addSql('ALTER TABLE contrat_s130 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s140 DROP FOREIGN KEY FK_ADC0398CE7A1254A');
        $this->addSql('DROP INDEX IDX_ADC0398CE7A1254A ON contrat_s140');
        $this->addSql('ALTER TABLE contrat_s140 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s150 DROP FOREIGN KEY FK_B4DB08CDE7A1254A');
        $this->addSql('DROP INDEX IDX_B4DB08CDE7A1254A ON contrat_s150');
        $this->addSql('ALTER TABLE contrat_s150 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s160 DROP FOREIGN KEY FK_9FF65B0EE7A1254A');
        $this->addSql('DROP INDEX IDX_9FF65B0EE7A1254A ON contrat_s160');
        $this->addSql('ALTER TABLE contrat_s160 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s170 DROP FOREIGN KEY FK_86ED6A4FE7A1254A');
        $this->addSql('DROP INDEX IDX_86ED6A4FE7A1254A ON contrat_s170');
        $this->addSql('ALTER TABLE contrat_s170 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s40 DROP FOREIGN KEY FK_D2C18A20E7A1254A');
        $this->addSql('DROP INDEX IDX_D2C18A20E7A1254A ON contrat_s40');
        $this->addSql('ALTER TABLE contrat_s40 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s50 DROP FOREIGN KEY FK_CBDABB61E7A1254A');
        $this->addSql('DROP INDEX IDX_CBDABB61E7A1254A ON contrat_s50');
        $this->addSql('ALTER TABLE contrat_s50 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s60 DROP FOREIGN KEY FK_E0F7E8A2E7A1254A');
        $this->addSql('DROP INDEX IDX_E0F7E8A2E7A1254A ON contrat_s60');
        $this->addSql('ALTER TABLE contrat_s60 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s70 DROP FOREIGN KEY FK_F9ECD9E3E7A1254A');
        $this->addSql('DROP INDEX IDX_F9ECD9E3E7A1254A ON contrat_s70');
        $this->addSql('ALTER TABLE contrat_s70 DROP contact_id');
        $this->addSql('ALTER TABLE contrat_s80 DROP FOREIGN KEY FK_7E74C52CE7A1254A');
        $this->addSql('DROP INDEX IDX_7E74C52CE7A1254A ON contrat_s80');
        $this->addSql('ALTER TABLE contrat_s80 DROP contact_id');
    }
}
