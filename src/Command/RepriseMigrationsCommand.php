<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Doctrine Migrations 2 (Symfony 4.4) notait les migrations jouées dans la table
 * « migration_versions » avec leur seul numéro (20200412145110). Doctrine Migrations 3
 * utilise « doctrine_migration_versions » et le nom complet de la classe
 * (DoctrineMigrations\Version20200412145110) : sans reprise, il rejouerait tout.
 *
 * Cette commande recopie l'historique puis supprime l'ancienne table. Elle ne fait
 * rien si l'ancienne table n'existe pas : on peut la lancer à chaque démarrage.
 */
#[AsCommand(
    name: 'app:migrations:reprise',
    description: 'Reprend l’historique des migrations de Doctrine Migrations 2 (table migration_versions)',
)]
class RepriseMigrationsCommand extends Command
{
    public const ANCIENNE_TABLE = 'migration_versions';

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([self::ANCIENNE_TABLE])) {
            $io->writeln('Aucun historique de migrations à reprendre.');

            return Command::SUCCESS;
        }

        $stockage = $this->dependencyFactory->getMetadataStorage();
        $stockage->ensureInitialized();
        $dejaNotees = $stockage->getExecutedMigrations();

        $reprises = 0;
        $lignes = $this->connection->fetchAllAssociative(sprintf('SELECT version, executed_at FROM %s ORDER BY version', self::ANCIENNE_TABLE));
        foreach ($lignes as $ligne) {
            $version = new Version('DoctrineMigrations\\Version'.$ligne['version']);
            if ($dejaNotees->hasMigration($version)) {
                continue;
            }
            $executeeLe = $ligne['executed_at'] ? new \DateTimeImmutable($ligne['executed_at']) : null;
            $stockage->complete(new ExecutionResult($version, executedAt: $executeeLe));
            ++$reprises;
        }

        $schemaManager->dropTable(self::ANCIENNE_TABLE);
        $io->success(sprintf('%d migration(s) reprise(s) depuis « %s », table supprimée.', $reprises, self::ANCIENNE_TABLE));

        return Command::SUCCESS;
    }
}
