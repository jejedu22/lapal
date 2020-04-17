<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200413132853 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE jour_distrib (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE jour_distrib_pain (jour_distrib_id INT NOT NULL, pain_id INT NOT NULL, INDEX IDX_AC6AC5FE64949231 (jour_distrib_id), INDEX IDX_AC6AC5FE64775A84 (pain_id), PRIMARY KEY(jour_distrib_id, pain_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE jour_distrib_pain ADD CONSTRAINT FK_AC6AC5FE64949231 FOREIGN KEY (jour_distrib_id) REFERENCES jour_distrib (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jour_distrib_pain ADD CONSTRAINT FK_AC6AC5FE64775A84 FOREIGN KEY (pain_id) REFERENCES pain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande ADD jour_distrib_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D64949231 FOREIGN KEY (jour_distrib_id) REFERENCES jour_distrib (id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D64949231 ON commande (jour_distrib_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D64949231');
        $this->addSql('ALTER TABLE jour_distrib_pain DROP FOREIGN KEY FK_AC6AC5FE64949231');
        $this->addSql('DROP TABLE jour_distrib');
        $this->addSql('DROP TABLE jour_distrib_pain');
        $this->addSql('DROP INDEX IDX_6EEAA67D64949231 ON commande');
        $this->addSql('ALTER TABLE commande DROP jour_distrib_id');
    }
}
