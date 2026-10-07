<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Boulanger;
use App\Entity\Commande;
use App\Entity\JourDistrib;
use App\Entity\LigneCommande;
use App\Entity\Pain;
use App\Entity\Settings;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : un navigateur simulé et une base SQLite vide pour chaque test.
 */
abstract class FonctionnelTestCase extends WebTestCase
{
    /** @var KernelBrowser */
    protected $client;

    /** @var EntityManagerInterface */
    protected $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Base SQLite repartie de zéro : on supprime le fichier puis on recrée le schéma
        $connexion = $this->em->getConnection();
        $fichier = $connexion->getParams()['path'] ?? null;
        $connexion->close();
        if ($fichier && file_exists($fichier)) {
            unlink($fichier);
        }
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em = null;
        $this->client = null;
    }

    protected function creerPain(string $nom, float $poid, float $prix, int $position = 0, bool $actif = true): Pain
    {
        $pain = (new Pain())->setNom($nom)->setPoid($poid)->setPrix($prix)->setPosition($position)->setActif($actif);
        $this->em->persist($pain);
        $this->em->flush();

        return $pain;
    }

    protected function creerBoulanger(string $prenom = 'Paul', string $nom = 'Fournier'): Boulanger
    {
        $boulanger = (new Boulanger())->setPrenom($prenom)->setNom($nom);
        $this->em->persist($boulanger);
        $this->em->flush();

        return $boulanger;
    }

    /**
     * @param Pain[] $pains
     */
    protected function creerJour(string $date, float $total, array $pains = [], ?Boulanger $boulanger = null, bool $ferme = false): JourDistrib
    {
        $jour = (new JourDistrib())
            ->setDate(new \DateTime($date))
            ->setTotal($total)
            ->setPoidRestant(0)
            ->setClosed($ferme)
            ->setBoulanger($boulanger);
        foreach ($pains as $pain) {
            $jour->addPain($pain);
        }
        $this->em->persist($jour);
        $this->em->flush();

        return $jour;
    }

    /**
     * Commande déjà passée ; le poids commandé du jour est mis à jour comme le fait l'application.
     *
     * @param array<int, array{0: Pain, 1: int}> $lignes
     */
    protected function creerCommande(JourDistrib $jour, string $prenom, string $nom, array $lignes, bool $livree = false): Commande
    {
        $commande = (new Commande())->setPrenom($prenom)->setNom($nom)->setLivree($livree);
        $jour->addCommande($commande);
        $poids = 0.0;
        foreach ($lignes as [$pain, $quantite]) {
            $commande->addLigneCommande((new LigneCommande())->setPain($pain)->setQuantite($quantite));
            $poids += $pain->getPoid() * $quantite;
        }
        $jour->setPoidRestant(round($jour->getPoidsCommande() + $poids, 3));
        $this->em->persist($commande);
        $this->em->flush();

        return $commande;
    }

    protected function creerUtilisateur(string $identifiant = 'admin', string $motDePasse = 'secret123'): User
    {
        $user = (new User())->setUsername($identifiant)->setPassword(password_hash($motDePasse, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function creerParametre(string $nom, ?string $valeur): Settings
    {
        $parametre = (new Settings())->setName($nom)->setValue($valeur);
        $this->em->persist($parametre);
        $this->em->flush();

        return $parametre;
    }

    /**
     * Connexion par le formulaire de l'application.
     */
    protected function connecter(string $identifiant = 'admin', string $motDePasse = 'secret123'): void
    {
        if (null === $this->em->getRepository(User::class)->findOneBy(['username' => $identifiant])) {
            $this->creerUtilisateur($identifiant, $motDePasse);
        }
        $this->client->request('GET', '/logout');
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'username' => $identifiant,
            'password' => $motDePasse,
        ]));
        $this->assertResponseRedirects();
    }

    /**
     * Recharge une entité depuis la base (les requêtes passent par un autre gestionnaire d'entités).
     *
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T|null
     */
    protected function recharger(string $classe, int $id)
    {
        $this->em->clear();

        return $this->em->find($classe, $id);
    }

    /**
     * Texte des messages flash affichés sur la page courante.
     */
    protected function messagesFlash(): string
    {
        return implode(' | ', $this->client->getCrawler()->filter('.alert')->each(function ($noeud) {
            return trim(preg_replace('/\s+/u', ' ', $noeud->text()));
        }));
    }
}
