<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012142503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catégories d\'objets (prédéfinies et personnelles), type précis de l\'objet';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(60) NOT NULL, icone VARCHAR(60) NOT NULL, ordre INT DEFAULT 0 NOT NULL, cree_le DATETIME NOT NULL, proprietaire_id INT DEFAULT NULL, UNIQUE INDEX categorie_nom_unique (proprietaire_id, nom), INDEX IDX_497DD63476C50E4A (proprietaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE categorie ADD CONSTRAINT FK_497DD63476C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        // Catégories prédéfinies, communes à tous (sans propriétaire)
        $this->addSql("INSERT INTO categorie (nom, icone, ordre, cree_le) VALUES
            ('Hauts', 'ph:t-shirt-duotone', 1, NOW()),
            ('Bas', 'ph:pants-duotone', 2, NOW()),
            ('Robes', 'ph:dress-duotone', 3, NOW()),
            ('Vestes et manteaux', 'ph:coat-hanger-duotone', 4, NOW()),
            ('Chaussures', 'ph:sneaker-duotone', 5, NOW()),
            ('Sacs', 'ph:handbag-duotone', 6, NOW()),
            ('Accessoires', 'ph:sunglasses-duotone', 7, NOW()),
            ('Bijoux', 'ph:diamond-duotone', 8, NOW()),
            ('Livres', 'ph:books-duotone', 9, NOW()),
            ('Musique', 'ph:vinyl-record-duotone', 10, NOW()),
            ('Déco', 'ph:lamp-duotone', 11, NOW()),
            ('Autres', 'ph:shapes-duotone', 12, NOW())");
        // L'ancien champ texte « catégorie » (Manteau, T-shirt…) devient le type précis de l'objet
        $this->addSql('ALTER TABLE objet ADD categorie_id INT DEFAULT NULL, CHANGE categorie type VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE objet ADD CONSTRAINT FK_46CD4C38BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_46CD4C38BCF5E72D ON objet (categorie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categorie DROP FOREIGN KEY FK_497DD63476C50E4A');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('ALTER TABLE objet DROP FOREIGN KEY FK_46CD4C38BCF5E72D');
        $this->addSql('DROP INDEX IDX_46CD4C38BCF5E72D ON objet');
        $this->addSql('ALTER TABLE objet DROP categorie_id, CHANGE type categorie VARCHAR(60) DEFAULT NULL');
    }
}
