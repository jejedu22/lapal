<?php

namespace App\Tests\Fonctionnel;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Reprise de l'historique des migrations laissé par Doctrine Migrations 2 (Symfony 4.4).
 */
class RepriseMigrationsCommandTest extends KernelTestCase
{
    private Connection $connexion;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connexion = static::getContainer()->get('doctrine')->getConnection();
        foreach (['migration_versions', 'doctrine_migration_versions'] as $table) {
            $this->connexion->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
    }

    private function lancer(): CommandTester
    {
        $testeur = new CommandTester((new Application(self::$kernel))->find('app:migrations:reprise'));
        $testeur->execute([]);
        $testeur->assertCommandIsSuccessful();

        return $testeur;
    }

    public function testRepriseDeLAncienHistorique(): void
    {
        $this->connexion->executeStatement('CREATE TABLE migration_versions (version VARCHAR(14) NOT NULL PRIMARY KEY, executed_at DATETIME NOT NULL)');
        $this->connexion->insert('migration_versions', ['version' => '20200412145110', 'executed_at' => '2020-04-12 14:51:10']);
        $this->connexion->insert('migration_versions', ['version' => '20260930090000', 'executed_at' => '2026-09-30 09:00:00']);

        $testeur = $this->lancer();

        $this->assertStringContainsString('2 migration(s) reprise(s)', $testeur->getDisplay());
        $this->assertFalse($this->connexion->createSchemaManager()->tablesExist(['migration_versions']));
        $this->assertSame([
            ['version' => 'DoctrineMigrations\Version20200412145110', 'executed_at' => '2020-04-12 14:51:10'],
            ['version' => 'DoctrineMigrations\Version20260930090000', 'executed_at' => '2026-09-30 09:00:00'],
        ], $this->connexion->fetchAllAssociative('SELECT version, executed_at FROM doctrine_migration_versions ORDER BY version'));

        // Sans ancienne table, la commande ne fait rien : elle peut tourner à chaque démarrage
        $this->assertStringContainsString('Aucun historique de migrations à reprendre.', $this->lancer()->getDisplay());
        $this->assertSame(2, (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions'));
    }

    public function testNeDoublePasUneMigrationDejaNotee(): void
    {
        $this->connexion->executeStatement('CREATE TABLE migration_versions (version VARCHAR(14) NOT NULL PRIMARY KEY, executed_at DATETIME NOT NULL)');
        $this->connexion->insert('migration_versions', ['version' => '20200412145110', 'executed_at' => '2020-04-12 14:51:10']);
        static::getContainer()->get('doctrine.migrations.dependency_factory')->getMetadataStorage()->ensureInitialized();
        $this->connexion->insert('doctrine_migration_versions', ['version' => 'DoctrineMigrations\Version20200412145110', 'executed_at' => '2020-04-12 14:51:10']);

        $this->assertStringContainsString('0 migration(s) reprise(s)', $this->lancer()->getDisplay());
        $this->assertSame(1, (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions'));
    }

    public function testSansAncienneTable(): void
    {
        $this->assertStringContainsString('Aucun historique de migrations à reprendre.', $this->lancer()->getDisplay());
    }
}
