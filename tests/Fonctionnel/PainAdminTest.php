<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Commande;
use App\Entity\JourDistrib;
use App\Entity\Pain;

/**
 * Administration des pains.
 */
class PainAdminTest extends FonctionnelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter();
    }

    public function testListeDansLOrdreChoisi(): void
    {
        $this->creerPain('Seigle', 0.5, 3, 2);
        $this->creerPain('Campagne', 1, 4.5, 1);
        $this->creerPain('Ancien', 1, 4, 0, false);

        $crawler = $this->client->request('GET', '/pain/');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Campagne', 'Seigle', 'Ancien'], $crawler->filter('a.lien-ligne')->each(function ($a) {
            return $a->text();
        }));
    }

    public function testAjouterUnPainEnFinDeListe(): void
    {
        $this->creerPain('Campagne', 1, 4.5, 3);

        $crawler = $this->client->request('GET', '/pain/new');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'pain[nom]' => 'Épeautre',
            'pain[poid]' => '0.75',
            'pain[prix]' => '5.2',
            'pain[actif]' => true,
        ]));

        $this->assertResponseRedirects('/pain/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Le pain « Épeautre » a été ajouté.', $this->messagesFlash());
        $pain = $this->em->getRepository(Pain::class)->findOneBy(['nom' => 'Épeautre']);
        $this->assertSame(0.75, $pain->getPoid());
        $this->assertSame(5.2, $pain->getPrix());
        $this->assertSame(4, $pain->getPosition());
        $this->assertTrue($pain->getActif());
    }

    public function testFormulaireInvalide(): void
    {
        $crawler = $this->client->request('GET', '/pain/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'pain[nom]' => 'Campagne',
            'pain[poid]' => 'lourd',
            'pain[prix]' => '4',
        ]));

        $this->assertFalse($this->client->getResponse()->isRedirect());
        $this->assertCount(1, $this->client->getCrawler()->filter('#pain_poid.is-invalid'));
        $this->assertCount(0, $this->em->getRepository(Pain::class)->findAll());
    }

    public function testModifierEtArchiver(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);

        $crawler = $this->client->request('GET', '/pain/'.$pain->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form([
            'pain[nom]' => 'Campagne bio',
            'pain[prix]' => '5',
        ]);
        $form['pain[actif]']->untick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/pain/');
        $pain = $this->recharger(Pain::class, $pain->getId());
        $this->assertSame('Campagne bio', $pain->getNom());
        $this->assertSame(5.0, $pain->getPrix());
        $this->assertFalse($pain->getActif());
    }

    public function testEnregistrerLOrdre(): void
    {
        $a = $this->creerPain('A', 1, 1, 1);
        $b = $this->creerPain('B', 1, 1, 2);
        $c = $this->creerPain('C', 1, 1, 3);

        $crawler = $this->client->request('GET', '/pain/');
        $jeton = $crawler->filter('[data-ordre-jeton]')->attr('data-ordre-jeton');
        $this->client->request('POST', '/pain/ordre', ['_token' => $jeton, 'ordre' => [$c->getId(), $a->getId(), $b->getId()]]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['ok' => true], json_decode($this->client->getResponse()->getContent(), true));
        $this->assertSame(1, $this->recharger(Pain::class, $c->getId())->getPosition());
        $this->assertSame(2, $this->recharger(Pain::class, $a->getId())->getPosition());
        $this->assertSame(3, $this->recharger(Pain::class, $b->getId())->getPosition());
    }

    public function testOrdreRefuseSansJeton(): void
    {
        $a = $this->creerPain('A', 1, 1, 1);

        $this->client->request('POST', '/pain/ordre', ['_token' => 'faux', 'ordre' => [$a->getId()]]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSupprimerUnPainSansCommande(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $this->creerJour('+3 days', 20, [$pain]);

        $crawler = $this->client->request('GET', '/pain/'.$pain->getId().'/edit');
        $this->client->submit($crawler->filter('form[action="/pain/'.$pain->getId().'"]')->form());

        $this->assertResponseRedirects('/pain/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Le pain « Campagne » a été supprimé.', $this->messagesFlash());
        $this->assertCount(0, $this->em->getRepository(Pain::class)->findAll());
    }

    public function testSupprimerUnPainSupprimeSesCommandes(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5);
        $seigle = $this->creerPain('Seigle', 0.5, 3);
        $jour = $this->creerJour('+3 days', 20, [$campagne, $seigle]);
        $this->creerCommande($jour, 'Marie', 'Curie', [[$campagne, 2], [$seigle, 1]]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$seigle, 2]]);

        $crawler = $this->client->request('GET', '/pain/'.$campagne->getId().'/edit');
        $this->client->submit($crawler->filter('form[action="/pain/'.$campagne->getId().'"]')->form());

        $this->assertResponseRedirects('/pain/');
        $this->client->followRedirect();
        $this->assertStringContainsString('ainsi que 1 commande qui le contenait', $this->messagesFlash());
        $this->em->clear();
        $this->assertSame(['Dupont'], array_map(function (Commande $c) {
            return $c->getNom();
        }, $this->em->getRepository(Commande::class)->findAll()));
        $this->assertSame(1.0, $this->recharger(JourDistrib::class, $jour->getId())->getPoidsCommande());
        $this->assertNull($this->recharger(Pain::class, $campagne->getId()));
    }
}
