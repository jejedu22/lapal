<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Commande;
use App\Entity\JourDistrib;

/**
 * Parcours d'un client : voir les distributions, commander, modifier, annuler.
 */
class CommandeClientTest extends FonctionnelTestCase
{
    public function testAccueilListeLesDistributionsAVenir(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $this->creerJour('+3 days', 20, [$pain], null, false);
        $this->creerJour('+5 days', 20, [$pain], null, true);
        $this->creerJour('-3 days', 20, [$pain]);

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href^="/new/"]'), 'Seul le jour ouvert propose de commander');
        $this->assertStringContainsString('Lapal', $crawler->filter('title')->text());
    }

    public function testCommanderPuisVoirSaCommande(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5, 1);
        $seigle = $this->creerPain('Seigle', 0.5, 3, 2);
        $jour = $this->creerJour('+3 days', 20, [$campagne, $seigle]);

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());
        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('input[name^="quantites["]'));

        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => 'Marie',
            'commande[nom]' => 'Curie',
            'commande[commentaire]' => 'Bien cuit',
            'quantites['.$campagne->getId().']' => 2,
            'quantites['.$seigle->getId().']' => 1,
        ]));

        $this->assertResponseRedirects('/commande/');
        $cookie = $this->client->getCookieJar()->get('commande');
        $this->assertNotNull($cookie);
        $this->assertSame('Marie', json_decode(urldecode($cookie->getValue()))->prenom);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Votre commande a bien été enregistrée.', $this->messagesFlash());
        $this->assertSelectorTextContains('body', 'Campagne');

        $commande = $this->em->getRepository(Commande::class)->findOneBy(['nom' => 'Curie']);
        $this->assertSame('Marie', $commande->getPrenom());
        $this->assertSame('Bien cuit', $commande->getCommentaire());
        $this->assertFalse($commande->getLivree());
        $this->assertCount(2, $commande->getLigneCommandes());
        $this->assertSame(2.5, $this->recharger(JourDistrib::class, $jour->getId())->getPoidsCommande());
    }

    public function testLeFormulaireEstPreRempliAvecLeDernierNom(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('commande', json_encode(['nom' => 'Curie', 'prenom' => 'Marie'])));

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());

        $this->assertSame('Curie', $crawler->filter('#commande_nom')->attr('value'));
        $this->assertSame('Marie', $crawler->filter('#commande_prenom')->attr('value'));
    }

    public function testCommandeVideRefusee(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());
        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => 'Marie',
            'commande[nom]' => 'Curie',
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Choisissez au moins un pain.');
        $this->assertCount(0, $this->em->getRepository(Commande::class)->findAll());
    }

    public function testNomObligatoire(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());
        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => '',
            'commande[nom]' => '',
            'quantites['.$pain->getId().']' => 1,
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Indiquez votre nom.');
        $this->assertSelectorTextContains('body', 'Indiquez votre prénom.');
        $this->assertCount(0, $this->em->getRepository(Commande::class)->findAll());
    }

    public function testCommandeTropLourdeRefusee(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 5, [$pain]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$pain, 3]]);

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());
        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => 'Marie',
            'commande[nom]' => 'Curie',
            'quantites['.$pain->getId().']' => 3,
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Il ne reste que 2');
        $this->assertCount(1, $this->em->getRepository(Commande::class)->findAll());
    }

    public function testQuantiteLimiteeAuMaximum(): void
    {
        $pain = $this->creerPain('Petit pain', 0.1, 1);
        $jour = $this->creerJour('+3 days', 100, [$pain]);

        $crawler = $this->client->request('GET', '/new/'.$jour->getId());
        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => 'Marie',
            'commande[nom]' => 'Curie',
            'quantites['.$pain->getId().']' => 99,
        ]));

        $this->assertResponseRedirects('/commande/');
        $commande = $this->em->getRepository(Commande::class)->findOneBy(['nom' => 'Curie']);
        $this->assertSame(20, $commande->getLigneCommandes()->first()->getQuantite());
    }

    public function testJourFermeRedirigeVersAccueil(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $ferme = $this->creerJour('+3 days', 20, [$pain], null, true);
        $complet = $this->creerJour('+4 days', 1, [$pain]);
        $this->creerCommande($complet, 'Jean', 'Dupont', [[$pain, 1]]);
        $passe = $this->creerJour('-1 day', 20, [$pain]);

        $messages = [
            $ferme->getId() => 'Les commandes sont fermées pour cette distribution.',
            $complet->getId() => 'Cette distribution est complète.',
            $passe->getId() => 'Cette distribution est terminée.',
        ];
        foreach ($messages as $id => $message) {
            $this->client->request('GET', '/new/'.$id);
            $this->assertResponseRedirects('/');
            $this->client->followRedirect();
            $this->assertStringContainsString($message, $this->messagesFlash());
        }
    }

    public function testJourInconnu(): void
    {
        $this->client->request('GET', '/new/999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testModifierSaCommande(): void
    {
        $campagne = $this->creerPain('Campagne', 1, 4.5);
        $seigle = $this->creerPain('Seigle', 0.5, 3);
        $jour = $this->creerJour('+3 days', 4, [$campagne, $seigle]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$campagne, 3]]);

        $crawler = $this->client->request('GET', '/commande/'.$commande->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSame('3', $crawler->filter('#quantite-'.$campagne->getId())->attr('value'));

        // Le poids déjà commandé par cette commande reste disponible pour elle (4 kg au total)
        $this->client->submit($crawler->filter('#form-commande')->form([
            'commande[prenom]' => 'Marie',
            'commande[nom]' => 'Curie',
            'quantites['.$campagne->getId().']' => 3,
            'quantites['.$seigle->getId().']' => 2,
        ]));

        $this->assertResponseRedirects('/commande/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Votre commande a été modifiée.', $this->messagesFlash());
        $commande = $this->recharger(Commande::class, $commande->getId());
        $this->assertCount(2, $commande->getLigneCommandes());
        $this->assertSame(4.0, $commande->getJourDistrib()->getPoidsCommande());
    }

    public function testCommandeDUnJourPasseNonModifiable(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('-2 days', 20, [$pain]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 1]]);

        $this->client->request('GET', '/commande/'.$commande->getId().'/edit');

        $this->assertResponseRedirects('/commande/');
    }

    public function testMaCommandeSansCookieRedirige(): void
    {
        $this->client->request('GET', '/commande/');

        $this->assertResponseRedirects('/');
    }

    public function testMaCommandeSupprimeeEntreTemps(): void
    {
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('commande', json_encode(['command_id' => 999, 'nom' => 'Curie'])));

        $this->client->request('GET', '/commande/');

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Vous n’avez pas de commande en cours.', $this->messagesFlash());
    }

    public function testAnnulerSaCommande(): void
    {
        $pain = $this->creerPain('Campagne', 1.5, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);
        $this->creerCommande($jour, 'Jean', 'Dupont', [[$pain, 1]]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 2]]);
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('commande', json_encode(['command_id' => $commande->getId(), 'nom' => 'Curie', 'prenom' => 'Marie'])));

        $crawler = $this->client->request('GET', '/commande/');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action="/commande/'.$commande->getId().'"]')->form());

        $this->assertResponseRedirects('/');
        $this->assertNull($this->recharger(Commande::class, $commande->getId()));
        $this->assertSame(1.5, $this->recharger(JourDistrib::class, $jour->getId())->getPoidsCommande());
        $this->client->followRedirect();
        $this->assertStringContainsString('La commande de Marie Curie a été annulée.', $this->messagesFlash());
    }

    public function testAnnulationSansJetonValideIgnoree(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);
        $commande = $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 2]]);

        $this->client->request('POST', '/commande/'.$commande->getId(), ['_method' => 'DELETE', '_token' => 'faux']);

        $this->assertResponseRedirects('/');
        $this->assertNotNull($this->recharger(Commande::class, $commande->getId()));
    }

    public function testCommandesAVenirPubliques(): void
    {
        $pain = $this->creerPain('Campagne', 1, 4.5);
        $jour = $this->creerJour('+3 days', 20, [$pain]);
        $this->creerCommande($jour, 'Marie', 'Curie', [[$pain, 2]]);

        $this->client->request('GET', '/synthese/0');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'CURIE Marie');
    }

    public function testManifesteWeb(): void
    {
        $this->creerParametre('name', 'Fournil');
        $this->creerParametre('color', 'green');

        $this->client->request('GET', '/manifest.webmanifest');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $manifeste = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Fournil', $manifeste['name']);
        $this->assertSame('#28a745', $manifeste['theme_color']);
        $this->assertSame('/', $manifeste['start_url']);
        $this->assertSame('standalone', $manifeste['display']);
        $this->assertCount(3, $manifeste['icons']);
    }

    public function testManifesteAvecLogo(): void
    {
        $this->creerParametre('logo', 'logo-123.png');

        $this->client->request('GET', '/manifest.webmanifest');

        $manifeste = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('/uploads/logo/logo-123.png', $manifeste['icons'][0]['src']);
        $this->assertCount(4, $manifeste['icons']);
    }
}
