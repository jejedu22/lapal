<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Commande;
use App\Entity\JourDistrib;

/**
 * Administration des jours de distribution : création, fermeture, bon, export.
 */
class JourDistribAdminTest extends FonctionnelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter();
    }

    public function testCreerUnJourAvecLesPainsActifs(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $this->creerPain('Ancien', 1, 4, 3, false);
        $boulanger = $this->creerBoulanger();
        $date = (new \DateTime('+7 days'))->format('Y-m-d');

        $crawler = $this->client->request('GET', '/jour/distrib/new');
        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('input[name="jour_distrib[pains][]"]'), 'Les pains archivés ne sont pas proposés');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'jour_distrib[date]' => $date,
            'jour_distrib[total]' => '30',
            'jour_distrib[boulanger]' => $boulanger->getId(),
            'jour_distrib[commentaire]' => 'Au local',
        ]));

        $this->assertResponseRedirects('/jour/distrib/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Le jour de distribution a été créé.', $this->messagesFlash());
        $jour = $this->em->getRepository(JourDistrib::class)->findOneBy([]);
        $this->assertSame($date, $jour->getDate()->format('Y-m-d'));
        $this->assertSame(30.0, $jour->getTotal());
        $this->assertSame('Au local', $jour->getCommentaire());
        $this->assertFalse($jour->getClosed());
        $this->assertSame($boulanger->getId(), $jour->getBoulanger()->getId());
        $this->assertEqualsCanonicalizing([$campagne->getId(), $seigle->getId()], $jour->getPains()->map(function ($p) {
            return $p->getId();
        })->toArray());
    }

    public function testListeEtModification(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $ancien = $this->creerPain('Ancien', 1, 4, 0, false);
        $boulanger = $this->creerBoulanger();
        $jour = $this->creerJour('+3 days', 20, [$pain, $ancien], $boulanger);

        $crawler = $this->client->request('GET', '/jour/distrib/');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href="/jour/distrib/'.$jour->getId().'/bon"]'));

        $crawler = $this->client->request('GET', '/jour/distrib/'.$jour->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Ancien - 1 kg (archivé)');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'jour_distrib[total]' => '25',
        ]));

        $this->assertResponseRedirects('/jour/distrib/');
        $this->assertSame(25.0, $this->recharger(JourDistrib::class, $jour->getId())->getTotal());
    }

    public function testFermerPuisRouvrirLesCommandes(): void
    {
        $jour = $this->creerJour('+3 days', 20, [], $this->creerBoulanger());
        $action = '/jour/distrib/'.$jour->getId().'/fermeture';

        $crawler = $this->client->request('GET', '/jour/distrib/');
        $this->client->submit($crawler->filter('form[action="'.$action.'"]')->form());
        $this->assertResponseRedirects('/jour/distrib/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Les commandes sont fermées pour ce jour.', $this->messagesFlash());
        $this->assertTrue($this->recharger(JourDistrib::class, $jour->getId())->getClosed());

        $this->client->submit($crawler->filter('form[action="'.$action.'"]')->form());
        $this->client->followRedirect();
        $this->assertStringContainsString('Les commandes sont rouvertes pour ce jour.', $this->messagesFlash());
        $this->assertFalse($this->recharger(JourDistrib::class, $jour->getId())->getClosed());
    }

    public function testSupprimerUnJourEtSesCommandes(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain], $this->creerBoulanger());
        $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 1]]);

        $crawler = $this->client->request('GET', '/jour/distrib/'.$jour->getId().'/edit');
        $this->client->submit($crawler->filter('form[action="/jour/distrib/'.$jour->getId().'"]')->form());

        $this->assertResponseRedirects('/jour/distrib/');
        $this->em->clear();
        $this->assertCount(0, $this->em->getRepository(JourDistrib::class)->findAll());
        $this->assertCount(0, $this->em->getRepository(Commande::class)->findAll());
    }

    public function testBonDePreparation(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $jour = $this->creerJour('+3 days', 20, [$campagne, $seigle], $this->creerBoulanger());
        $this->creerCommande($jour, 'Marie', 'Curie', [[$campagne, 2], [$seigle, 1]]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$campagne, 1]]);

        $crawler = $this->client->request('GET', '/jour/distrib/'.$jour->getId().'/bon');

        $this->assertResponseIsSuccessful();
        $texte = $crawler->filter('body')->text();
        $this->assertStringContainsString('Campagne', $texte);
        $this->assertStringContainsString('Seigle', $texte);
        $this->assertLessThan(strpos($texte, 'DUPONT Jean'), strpos($texte, 'CURIE Marie'), 'Commandes triées par nom');
    }

    public function testExportCsv(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $jour = $this->creerJour('2030-06-15', 20, [$campagne, $seigle], $this->creerBoulanger());
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$seigle, 1], [$campagne, 2]], true);
        $commande->setCommentaire('Bien cuit');
        $this->em->flush();

        ob_start();
        $this->client->request('GET', '/jour/distrib/'.$jour->getId().'/export.csv');
        $contenu = ob_get_clean() ?: $this->client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('commandes-2030-06-15.csv', $this->client->getResponse()->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
        $lignes = array_values(array_filter(explode("\n", substr($contenu, 3))));
        $this->assertCount(3, $lignes);
        $this->assertSame('Nom;Prénom;Pain;"Poids unitaire (kg)";Quantité;"Prix unitaire (€)";"Montant (€)";Livrée;Commentaire', $lignes[0]);
        $this->assertSame('Curie;Marie;Campagne;1;2;4,50;9,00;oui;"Bien cuit"', $lignes[1]);
        $this->assertSame('Curie;Marie;Seigle;0,5;1;3,00;3,00;oui;"Bien cuit"', $lignes[2]);
    }
}
