<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200413131735 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE ligne_commande1 (id INT AUTO_INCREMENT NOT NULL, commande_id INT DEFAULT NULL, pain_id INT DEFAULT NULL, quantite INT NOT NULL, INDEX IDX_62E3071882EA2E54 (commande_id), INDEX IDX_62E3071864775A84 (pain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pain1 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, poid INT NOT NULL, prix DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE commande1 (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ligne_commande1 ADD CONSTRAINT FK_62E3071882EA2E54 FOREIGN KEY (commande_id) REFERENCES commande1 (id)');
        $this->addSql('ALTER TABLE ligne_commande1 ADD CONSTRAINT FK_62E3071864775A84 FOREIGN KEY (pain_id) REFERENCES pain1 (id)');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BDC08D46');
        $this->addSql('DROP INDEX IDX_3170B74BDC08D46 ON ligne_commande');
        $this->addSql('ALTER TABLE ligne_commande CHANGE pains_id pain_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B64775A84 FOREIGN KEY (pain_id) REFERENCES pain (id)');
        $this->addSql('CREATE INDEX IDX_3170B74B64775A84 ON ligne_commande (pain_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE ligne_commande1 DROP FOREIGN KEY FK_62E3071864775A84');
        $this->addSql('ALTER TABLE ligne_commande1 DROP FOREIGN KEY FK_62E3071882EA2E54');
        $this->addSql('DROP TABLE ligne_commande1');
        $this->addSql('DROP TABLE pain1');
        $this->addSql('DROP TABLE commande1');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B64775A84');
        $this->addSql('DROP INDEX IDX_3170B74B64775A84 ON ligne_commande');
        $this->addSql('ALTER TABLE ligne_commande CHANGE pain_id pains_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BDC08D46 FOREIGN KEY (pains_id) REFERENCES pain (id)');
        $this->addSql('CREATE INDEX IDX_3170B74BDC08D46 ON ligne_commande (pains_id)');
    }
}
