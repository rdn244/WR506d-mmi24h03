<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012122212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Looks, publications Instagram et passage de la connexion Instagram à Zernio';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE look (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, photo VARCHAR(64) NOT NULL, cree_le DATETIME NOT NULL, proprietaire_id INT NOT NULL, INDEX IDX_2B3AD40276C50E4A (proprietaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE publication_instagram (id INT AUTO_INCREMENT NOT NULL, format VARCHAR(10) NOT NULL, legende LONGTEXT DEFAULT NULL, zernio_id VARCHAR(64) NOT NULL, url VARCHAR(255) DEFAULT NULL, publiee_le DATETIME NOT NULL, look_id INT NOT NULL, INDEX IDX_98EB7854469DC8DC (look_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE look ADD CONSTRAINT FK_2B3AD40276C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication_instagram ADD CONSTRAINT FK_98EB7854469DC8DC FOREIGN KEY (look_id) REFERENCES look (id) ON DELETE CASCADE');
        // Les comptes connectés en direct chez Meta n'existent pas chez Zernio : ils doivent être reconnectés
        $this->addSql('DELETE FROM compte_instagram');
        $this->addSql('DROP INDEX UNIQ_472640F19C19920F ON compte_instagram');
        $this->addSql('ALTER TABLE compte_instagram DROP jeton, DROP expire_le, CHANGE instagram_id zernio_id VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_472640F13CE1B259 ON compte_instagram (zernio_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE look DROP FOREIGN KEY FK_2B3AD40276C50E4A');
        $this->addSql('ALTER TABLE publication_instagram DROP FOREIGN KEY FK_98EB7854469DC8DC');
        $this->addSql('DROP TABLE look');
        $this->addSql('DROP TABLE publication_instagram');
        $this->addSql('DELETE FROM compte_instagram');
        $this->addSql('DROP INDEX UNIQ_472640F13CE1B259 ON compte_instagram');
        $this->addSql('ALTER TABLE compte_instagram ADD jeton LONGTEXT NOT NULL, ADD expire_le DATETIME DEFAULT NULL, CHANGE zernio_id instagram_id VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_472640F19C19920F ON compte_instagram (instagram_id)');
    }
}
