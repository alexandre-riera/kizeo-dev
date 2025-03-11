<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250311135551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 ADD contrat_s10_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s10 ADD CONSTRAINT FK_40FB2246BBE38E95 FOREIGN KEY (contrat_s10_id) REFERENCES contrat_s10 (id)');
        $this->addSql('CREATE INDEX IDX_40FB2246BBE38E95 ON equipement_s10 (contrat_s10_id)');
        $this->addSql('ALTER TABLE equipement_s100 ADD contrat_s100_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s100 ADD CONSTRAINT FK_6B24C0A6EE0ECEB3 FOREIGN KEY (contrat_s100_id) REFERENCES contrat_s100 (id)');
        $this->addSql('CREATE INDEX IDX_6B24C0A6EE0ECEB3 ON equipement_s100 (contrat_s100_id)');
        $this->addSql('ALTER TABLE equipement_s120 ADD contrat_s120_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s120 ADD CONSTRAINT FK_5912A22494CE9DD3 FOREIGN KEY (contrat_s120_id) REFERENCES contrat_s120 (id)');
        $this->addSql('CREATE INDEX IDX_5912A22494CE9DD3 ON equipement_s120 (contrat_s120_id)');
        $this->addSql('ALTER TABLE equipement_s130 ADD contrat_s130_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s130 ADD CONSTRAINT FK_40099365A9AEB463 FOREIGN KEY (contrat_s130_id) REFERENCES contrat_s130 (id)');
        $this->addSql('CREATE INDEX IDX_40099365A9AEB463 ON equipement_s130 (contrat_s130_id)');
        $this->addSql('ALTER TABLE equipement_s140 ADD contrat_s140_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s140 ADD CONSTRAINT FK_F4805A21B8E6873 FOREIGN KEY (contrat_s140_id) REFERENCES contrat_s140 (id)');
        $this->addSql('CREATE INDEX IDX_F4805A21B8E6873 ON equipement_s140 (contrat_s140_id)');
        $this->addSql('ALTER TABLE equipement_s150 ADD contrat_s150_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s150 ADD CONSTRAINT FK_165334E326EE41C3 FOREIGN KEY (contrat_s150_id) REFERENCES contrat_s150 (id)');
        $this->addSql('CREATE INDEX IDX_165334E326EE41C3 ON equipement_s150 (contrat_s150_id)');
        $this->addSql('ALTER TABLE equipement_s160 ADD contrat_s160_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s160 ADD CONSTRAINT FK_3D7E6720614E3B13 FOREIGN KEY (contrat_s160_id) REFERENCES contrat_s160 (id)');
        $this->addSql('CREATE INDEX IDX_3D7E6720614E3B13 ON equipement_s160 (contrat_s160_id)');
        $this->addSql('ALTER TABLE equipement_s170 ADD contrat_s170_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s170 ADD CONSTRAINT FK_246556615C2E12A3 FOREIGN KEY (contrat_s170_id) REFERENCES contrat_s170 (id)');
        $this->addSql('CREATE INDEX IDX_246556615C2E12A3 ON equipement_s170 (contrat_s170_id)');
        $this->addSql('ALTER TABLE equipement_s40 ADD contrat_s40_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s40 ADD CONSTRAINT FK_3D8CD603730301E5 FOREIGN KEY (contrat_s40_id) REFERENCES contrat_s40 (id)');
        $this->addSql('CREATE INDEX IDX_3D8CD603730301E5 ON equipement_s40 (contrat_s40_id)');
        $this->addSql('ALTER TABLE equipement_s50 ADD contrat_s50_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s50 ADD CONSTRAINT FK_2497E7424E632855 FOREIGN KEY (contrat_s50_id) REFERENCES contrat_s50 (id)');
        $this->addSql('CREATE INDEX IDX_2497E7424E632855 ON equipement_s50 (contrat_s50_id)');
        $this->addSql('ALTER TABLE equipement_s60 ADD contrat_s60_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s60 ADD CONSTRAINT FK_FBAB4819C35285 FOREIGN KEY (contrat_s60_id) REFERENCES contrat_s60 (id)');
        $this->addSql('CREATE INDEX IDX_FBAB4819C35285 ON equipement_s60 (contrat_s60_id)');
        $this->addSql('ALTER TABLE equipement_s70 ADD contrat_s70_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s70 ADD CONSTRAINT FK_16A185C034A37B35 FOREIGN KEY (contrat_s70_id) REFERENCES contrat_s70 (id)');
        $this->addSql('CREATE INDEX IDX_16A185C034A37B35 ON equipement_s70 (contrat_s70_id)');
        $this->addSql('ALTER TABLE equipement_s80 ADD contrat_s80_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_s80 ADD CONSTRAINT FK_9139990FB6F3ECE4 FOREIGN KEY (contrat_s80_id) REFERENCES contrat_s80 (id)');
        $this->addSql('CREATE INDEX IDX_9139990FB6F3ECE4 ON equipement_s80 (contrat_s80_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_s10 DROP FOREIGN KEY FK_40FB2246BBE38E95');
        $this->addSql('DROP INDEX IDX_40FB2246BBE38E95 ON equipement_s10');
        $this->addSql('ALTER TABLE equipement_s10 DROP contrat_s10_id');
        $this->addSql('ALTER TABLE equipement_s100 DROP FOREIGN KEY FK_6B24C0A6EE0ECEB3');
        $this->addSql('DROP INDEX IDX_6B24C0A6EE0ECEB3 ON equipement_s100');
        $this->addSql('ALTER TABLE equipement_s100 DROP contrat_s100_id');
        $this->addSql('ALTER TABLE equipement_s120 DROP FOREIGN KEY FK_5912A22494CE9DD3');
        $this->addSql('DROP INDEX IDX_5912A22494CE9DD3 ON equipement_s120');
        $this->addSql('ALTER TABLE equipement_s120 DROP contrat_s120_id');
        $this->addSql('ALTER TABLE equipement_s130 DROP FOREIGN KEY FK_40099365A9AEB463');
        $this->addSql('DROP INDEX IDX_40099365A9AEB463 ON equipement_s130');
        $this->addSql('ALTER TABLE equipement_s130 DROP contrat_s130_id');
        $this->addSql('ALTER TABLE equipement_s140 DROP FOREIGN KEY FK_F4805A21B8E6873');
        $this->addSql('DROP INDEX IDX_F4805A21B8E6873 ON equipement_s140');
        $this->addSql('ALTER TABLE equipement_s140 DROP contrat_s140_id');
        $this->addSql('ALTER TABLE equipement_s150 DROP FOREIGN KEY FK_165334E326EE41C3');
        $this->addSql('DROP INDEX IDX_165334E326EE41C3 ON equipement_s150');
        $this->addSql('ALTER TABLE equipement_s150 DROP contrat_s150_id');
        $this->addSql('ALTER TABLE equipement_s160 DROP FOREIGN KEY FK_3D7E6720614E3B13');
        $this->addSql('DROP INDEX IDX_3D7E6720614E3B13 ON equipement_s160');
        $this->addSql('ALTER TABLE equipement_s160 DROP contrat_s160_id');
        $this->addSql('ALTER TABLE equipement_s170 DROP FOREIGN KEY FK_246556615C2E12A3');
        $this->addSql('DROP INDEX IDX_246556615C2E12A3 ON equipement_s170');
        $this->addSql('ALTER TABLE equipement_s170 DROP contrat_s170_id');
        $this->addSql('ALTER TABLE equipement_s40 DROP FOREIGN KEY FK_3D8CD603730301E5');
        $this->addSql('DROP INDEX IDX_3D8CD603730301E5 ON equipement_s40');
        $this->addSql('ALTER TABLE equipement_s40 DROP contrat_s40_id');
        $this->addSql('ALTER TABLE equipement_s50 DROP FOREIGN KEY FK_2497E7424E632855');
        $this->addSql('DROP INDEX IDX_2497E7424E632855 ON equipement_s50');
        $this->addSql('ALTER TABLE equipement_s50 DROP contrat_s50_id');
        $this->addSql('ALTER TABLE equipement_s60 DROP FOREIGN KEY FK_FBAB4819C35285');
        $this->addSql('DROP INDEX IDX_FBAB4819C35285 ON equipement_s60');
        $this->addSql('ALTER TABLE equipement_s60 DROP contrat_s60_id');
        $this->addSql('ALTER TABLE equipement_s70 DROP FOREIGN KEY FK_16A185C034A37B35');
        $this->addSql('DROP INDEX IDX_16A185C034A37B35 ON equipement_s70');
        $this->addSql('ALTER TABLE equipement_s70 DROP contrat_s70_id');
        $this->addSql('ALTER TABLE equipement_s80 DROP FOREIGN KEY FK_9139990FB6F3ECE4');
        $this->addSql('DROP INDEX IDX_9139990FB6F3ECE4 ON equipement_s80');
        $this->addSql('ALTER TABLE equipement_s80 DROP contrat_s80_id');
    }
}
