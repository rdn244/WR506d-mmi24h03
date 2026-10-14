<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930080349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Droits d\'accès : sous-comptes, liens entre membres, partage des pièces et rangements, compte public';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lien (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, cree_le DATETIME NOT NULL, demandeur_id INT NOT NULL, destinataire_id INT NOT NULL, UNIQUE INDEX lien_unique (type, demandeur_id, destinataire_id), INDEX IDX_A532B4B595A6EE59 (demandeur_id), INDEX IDX_A532B4B5A4F84F6E (destinataire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sous_compte (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(50) NOT NULL, date_naissance DATETIME NOT NULL, niveau VARCHAR(20) DEFAULT NULL, cree_le DATETIME NOT NULL, compte_principal_id INT NOT NULL, INDEX IDX_1FE9A0972B62F489 (compte_principal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lien ADD CONSTRAINT FK_A532B4B595A6EE59 FOREIGN KEY (demandeur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien ADD CONSTRAINT FK_A532B4B5A4F84F6E FOREIGN KEY (destinataire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sous_compte ADD CONSTRAINT FK_1FE9A0972B62F489 FOREIGN KEY (compte_principal_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contenant ADD partage TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE piece ADD partagee TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD compte_public TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lien DROP FOREIGN KEY FK_A532B4B595A6EE59');
        $this->addSql('ALTER TABLE lien DROP FOREIGN KEY FK_A532B4B5A4F84F6E');
        $this->addSql('ALTER TABLE sous_compte DROP FOREIGN KEY FK_1FE9A0972B62F489');
        $this->addSql('DROP TABLE lien');
        $this->addSql('DROP TABLE sous_compte');
        $this->addSql('ALTER TABLE contenant DROP partage');
        $this->addSql('ALTER TABLE piece DROP partagee');
        $this->addSql('ALTER TABLE utilisateur DROP compte_public');
    }
}
