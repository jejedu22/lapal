<?php

namespace App\Tests\Fonctionnel;

/**
 * Accès réservé à l'équipe et connexion.
 */
class SecuriteTest extends FonctionnelTestCase
{
    public function testPagesPubliques(): void
    {
        foreach (['/', '/synthese/0', '/login', '/manifest.webmanifest'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
        }
    }

    public function testPagesReserveesRedirigentVersLaConnexion(): void
    {
        $urls = ['/pain/', '/pain/new', '/jour/distrib/', '/jour/distrib/new', '/synthese/1', '/synthese/2',
            '/synthesepoids', '/register', '/settings/', '/user/', '/livree/1'];
        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('/login', null, $url);
        }
    }

    public function testConnexionReussie(): void
    {
        $this->creerUtilisateur('marie', 'motdepasse');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'username' => 'marie',
            'password' => 'motdepasse',
        ]));

        $this->assertResponseRedirects('/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('MARIE', $crawler->filter('.sidebar')->text());
        $this->client->request('GET', '/pain/');
        $this->assertResponseIsSuccessful();
    }

    public function testRetourSurLaPageDemandeeApresConnexion(): void
    {
        $this->creerUtilisateur('marie', 'motdepasse');

        $this->client->request('GET', '/settings/');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'username' => 'marie',
            'password' => 'motdepasse',
        ]));

        $this->assertResponseRedirects('http://localhost/settings/');
    }

    public function testMauvaisMotDePasse(): void
    {
        $this->creerUtilisateur('marie', 'motdepasse');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'username' => 'marie',
            'password' => 'faux',
        ]));

        $this->assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        $this->assertCount(1, $crawler->filter('.alert-danger'));
        $this->assertSame('marie', $crawler->filter('#inputUsername')->attr('value'));
        $this->client->request('GET', '/pain/');
        $this->assertResponseRedirects('/login');
    }

    public function testUtilisateurInconnu(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'username' => 'personne',
            'password' => 'faux',
        ]));

        $crawler = $this->client->followRedirect();
        $this->assertCount(1, $crawler->filter('.alert-danger'));
    }

    public function testDeconnexion(): void
    {
        $this->connecter();

        $this->client->request('GET', '/logout');
        $this->assertResponseRedirects();
        $this->client->request('GET', '/pain/');
        $this->assertResponseRedirects('/login');
    }

    public function testMotDePasseRehacheAuBesoin(): void
    {
        // Ancien hachage à faible coût : il est mis à niveau à la connexion
        $user = $this->creerUtilisateur('marie', 'motdepasse');
        $ancien = $user->getPassword();

        $this->connecter('marie', 'motdepasse');

        $this->assertNotSame($ancien, $this->recharger(\App\Entity\User::class, $user->getId())->getPassword());
    }
}
