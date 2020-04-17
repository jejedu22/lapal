<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200413123122 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE ligne_commande_pain');
        $this->addSql('ALTER TABLE ligne_commande ADD pains_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BDC08D46 FOREIGN KEY (pains_id) REFERENCES pain (id)');
        $this->addSql('CREATE INDEX IDX_3170B74BDC08D46 ON ligne_commande (pains_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE ligne_commande_pain (ligne_commande_id INT NOT NULL, pain_id INT NOT NULL, INDEX IDX_7D5442A4E10FEE63 (ligne_commande_id), INDEX IDX_7D5442A464775A84 (pain_id), PRIMARY KEY(ligne_commande_id, pain_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE ligne_commande_pain ADD CONSTRAINT FK_7D5442A464775A84 FOREIGN KEY (pain_id) REFERENCES pain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande_pain ADD CONSTRAINT FK_7D5442A4E10FEE63 FOREIGN KEY (ligne_commande_id) REFERENCES ligne_commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BDC08D46');
        $this->addSql('DROP INDEX IDX_3170B74BDC08D46 ON ligne_commande');
        $this->addSql('ALTER TABLE ligne_commande DROP pains_id');
    }
}
