<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930090000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ordre d’affichage des pains';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pain ADD position INT DEFAULT 0 NOT NULL');
        // Ordre initial = ordre affiché jusqu'ici (pains en vente, puis alphabétique)
        $this->addSql('SET @rang := 0');
        $this->addSql('UPDATE pain SET position = (@rang := @rang + 1) ORDER BY actif DESC, nom ASC');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pain DROP position');
    }
}
