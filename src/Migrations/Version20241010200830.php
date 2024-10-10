<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241010200830 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE commande CHANGE jour_distrib_id jour_distrib_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE ligne_commande CHANGE commande_id commande_id INT DEFAULT NULL, CHANGE pain_id pain_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE jour_distrib ADD boulanger_id INT DEFAULT NULL, ADD commentaire VARCHAR(255) DEFAULT NULL, CHANGE poid_restant poid_restant DOUBLE PRECISION DEFAULT NULL, CHANGE closed closed TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE jour_distrib ADD CONSTRAINT FK_72749309D7A4E1D FOREIGN KEY (boulanger_id) REFERENCES boulanger (id)');
        $this->addSql('CREATE INDEX IDX_72749309D7A4E1D ON jour_distrib (boulanger_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE commande CHANGE jour_distrib_id jour_distrib_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE jour_distrib DROP FOREIGN KEY FK_72749309D7A4E1D');
        $this->addSql('DROP INDEX IDX_72749309D7A4E1D ON jour_distrib');
        $this->addSql('ALTER TABLE jour_distrib DROP boulanger_id, DROP commentaire, CHANGE poid_restant poid_restant DOUBLE PRECISION DEFAULT \'NULL\', CHANGE closed closed TINYINT(1) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE ligne_commande CHANGE commande_id commande_id INT DEFAULT NULL, CHANGE pain_id pain_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`');
    }
}
