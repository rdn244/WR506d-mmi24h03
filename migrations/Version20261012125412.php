<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012125412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Objets de l\'inventaire (scan IA)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE objet (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, marque VARCHAR(80) DEFAULT NULL, categorie VARCHAR(60) DEFAULT NULL, matiere VARCHAR(80) DEFAULT NULL, couleur VARCHAR(40) DEFAULT NULL, photo VARCHAR(64) DEFAULT NULL, personnel TINYINT DEFAULT 0 NOT NULL, modele_ia VARCHAR(120) DEFAULT NULL, cree_le DATETIME NOT NULL, proprietaire_id INT NOT NULL, contenant_id INT DEFAULT NULL, INDEX IDX_46CD4C3876C50E4A (proprietaire_id), INDEX IDX_46CD4C385E211B2E (contenant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE objet ADD CONSTRAINT FK_46CD4C3876C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objet ADD CONSTRAINT FK_46CD4C385E211B2E FOREIGN KEY (contenant_id) REFERENCES contenant (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objet DROP FOREIGN KEY FK_46CD4C3876C50E4A');
        $this->addSql('ALTER TABLE objet DROP FOREIGN KEY FK_46CD4C385E211B2E');
        $this->addSql('DROP TABLE objet');
    }
}
