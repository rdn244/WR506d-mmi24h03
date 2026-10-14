<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930083112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte Instagram connecté à un compte Dressing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE compte_instagram (id INT AUTO_INCREMENT NOT NULL, instagram_id VARCHAR(64) NOT NULL, nom_utilisateur VARCHAR(100) NOT NULL, jeton LONGTEXT NOT NULL, expire_le DATETIME DEFAULT NULL, connecte_le DATETIME NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_472640F19C19920F (instagram_id), UNIQUE INDEX UNIQ_472640F1FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE compte_instagram ADD CONSTRAINT FK_472640F1FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE compte_instagram DROP FOREIGN KEY FK_472640F1FB88E14F');
        $this->addSql('DROP TABLE compte_instagram');
    }
}
