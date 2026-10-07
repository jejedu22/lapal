<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Commande;

/**
 * Écrans de l'équipe le jour de la distribution : suivi des livraisons, synthèses.
 */
class SuiviTest extends FonctionnelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter();
    }

    public function testSuiviAfficheLaProchaineDistribution(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $passe = $this->creerJour('-7 days', 20, [$pain]);
        $prochain = $this->creerJour('+2 days', 20, [$pain]);
        $this->creerJour('+9 days', 20, [$pain]);
        $this->creerCommande($passe, 'Ancien', 'Client', [[$pain, 1]]);
        $this->creerCommande($prochain, 'Marie', 'Zola', [[$pain, 1]]);
        $this->creerCommande($prochain, 'Jean', 'Dupont', [[$pain, 1]]);

        $crawler = $this->client->request('GET', '/synthese/1');

        $this->assertResponseIsSuccessful();
        $this->assertSame((string) $prochain->getId(), $crawler->filter('#choix-jour option[selected]')->attr('value'));
        $noms = $crawler->filter('[data-nom]')->each(function ($n) {
            return $n->attr('data-nom');
        });
        $this->assertCount(2, $noms);
        $this->assertStringContainsString('dupont', $noms[0], 'Commandes triées par nom');
    }

    public function testSuiviDUnJourChoisi(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $passe = $this->creerJour('-7 days', 20, [$pain]);
        $this->creerJour('+2 days', 20, [$pain]);
        $this->creerCommande($passe, 'Ancien', 'Client', [[$pain, 1]]);

        $crawler = $this->client->request('GET', '/synthese/1?jour='.$passe->getId());

        $this->assertSame((string) $passe->getId(), $crawler->filter('#choix-jour option[selected]')->attr('value'));
        $this->assertCount(1, $crawler->filter('[data-nom]'));
    }

    public function testSuiviSansDistribution(): void
    {
        $this->client->request('GET', '/synthese/1');

        $this->assertResponseIsSuccessful();
    }

    public function testCocherLivree(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+2 days', 20, [$pain]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 1]]);

        $crawler = $this->client->request('GET', '/synthese/1');
        $jeton = $crawler->filter('#suivi')->attr('data-token');

        $this->client->request('POST', '/livree/'.$commande->getId().'/basculer', ['_token' => $jeton, 'livree' => '1']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(['id' => $commande->getId(), 'livree' => true], json_decode($this->client->getResponse()->getContent(), true));
        $this->assertTrue($this->recharger(Commande::class, $commande->getId())->getLivree());

        $this->client->request('POST', '/livree/'.$commande->getId().'/basculer', ['_token' => $jeton, 'livree' => '0']);
        $this->assertFalse($this->recharger(Commande::class, $commande->getId())->getLivree());
    }

    public function testCocherLivreeSansJeton(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $commande = $this->creerCommande($this->creerJour('+2 days', 20, [$pain]), 'Marie', 'Curie', [[$pain, 1]]);

        $this->client->request('POST', '/livree/'.$commande->getId().'/basculer', ['_token' => 'faux', 'livree' => '1']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertFalse($this->recharger(Commande::class, $commande->getId())->getLivree());
    }

    public function testAncienLienLivree(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+2 days', 20, [$pain]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 1]]);

        $this->client->request('GET', '/livree/'.$commande->getId());

        $this->assertResponseRedirects('/synthese/1?jour='.$jour->getId());
        $this->assertTrue($this->recharger(Commande::class, $commande->getId())->getLivree());

        $this->client->request('GET', '/livree/999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnnulerDepuisLaSyntheseRevientSurLaPage(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+2 days', 20, [$pain]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 1]]);

        $crawler = $this->client->request('GET', '/synthese/2');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action="/commande/'.$commande->getId().'"]')->form());

        $this->assertResponseRedirects('/synthese/2');
        $this->assertNull($this->recharger(Commande::class, $commande->getId()));
    }

    public function testToutesLesCommandes(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $this->creerCommande($this->creerJour('-30 days', 20, [$pain]), 'Ancien', 'Client', [[$pain, 1]]);
        $this->creerCommande($this->creerJour('+2 days', 20, [$pain]), 'Marie', 'Curie', [[$pain, 1]]);

        $this->client->request('GET', '/synthese/2');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'CLIENT Ancien');
        $this->assertSelectorTextContains('body', 'CURIE Marie');
    }

    public function testSyntheseDesPoids(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $jour = $this->creerJour('+2 days', 20, [$campagne, $seigle]);
        $this->creerCommande($jour, 'Marie', 'Curie', [[$campagne, 2], [$seigle, 1]]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$campagne, 3]]);

        $this->client->request('GET', '/synthesepoids');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Campagne');
        $this->assertSelectorTextContains('body', 'Seigle');
    }

    public function testQuantitesParPain(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $jour = $this->creerJour('+2 days', 20, [$campagne, $seigle]);
        $autre = $this->creerJour('+9 days', 20, [$campagne]);
        $this->creerCommande($jour, 'Marie', 'Curie', [[$campagne, 2], [$seigle, 1]]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$campagne, 3]]);
        $this->creerCommande($autre, 'Jean', 'Dupont', [[$campagne, 1]]);

        $quantites = $this->em->getRepository(\App\Entity\JourDistrib::class)->findQuantitesParPain([$jour->getId(), $autre->getId()]);

        $this->assertSame([
            $campagne->getId() => ['pain' => $campagne->getId(), 'nom' => 'Campagne', 'poid' => 1.0, 'prix' => 4.5, 'quantite' => 5],
            $seigle->getId() => ['pain' => $seigle->getId(), 'nom' => 'Seigle', 'poid' => 0.5, 'prix' => 3.0, 'quantite' => 1],
        ], $quantites[$jour->getId()]);
        $this->assertSame(1, $quantites[$autre->getId()][$campagne->getId()]['quantite']);
        $this->assertSame([], $this->em->getRepository(\App\Entity\JourDistrib::class)->findQuantitesParPain([]));
    }
}
