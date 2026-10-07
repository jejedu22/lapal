<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Boulanger;
use App\Entity\Settings;
use App\Entity\User;

/**
 * Boulangers, comptes de l'équipe et configuration.
 */
class AdministrationTest extends FonctionnelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter();
    }

    public function testAjouterEtModifierUnBoulanger(): void
    {
        $crawler = $this->client->request('GET', '/boulanger/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'boulanger[prenom]' => 'Paul',
            'boulanger[nom]' => 'Fournier',
        ]));
        $this->assertResponseRedirects('/boulanger/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Le boulanger a été ajouté.', $this->messagesFlash());
        $this->assertSelectorTextContains('body', 'Paul Fournier');

        $boulanger = $this->em->getRepository(Boulanger::class)->findOneBy(['nom' => 'Fournier']);
        $this->client->request('GET', '/boulanger/'.$boulanger->getId());
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/boulanger/'.$boulanger->getId().'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['boulanger[nom]' => 'Boulanger']));
        $this->assertResponseRedirects('/boulanger/');
        $this->assertSame('Boulanger', $this->recharger(Boulanger::class, $boulanger->getId())->getNom());
    }

    public function testSupprimerUnBoulanger(): void
    {
        $boulanger = $this->creerBoulanger();

        $crawler = $this->client->request('GET', '/boulanger/'.$boulanger->getId().'/edit');
        $this->client->submit($crawler->filter('form[action="/boulanger/'.$boulanger->getId().'"]')->form());

        $this->assertResponseRedirects('/boulanger/');
        $this->assertNull($this->recharger(Boulanger::class, $boulanger->getId()));
    }

    public function testBoulangerAvecDesJoursNonSupprimable(): void
    {
        $boulanger = $this->creerBoulanger();
        $this->creerJour('+3 days', 20, [], $boulanger);

        $crawler = $this->client->request('GET', '/boulanger/'.$boulanger->getId().'/edit');
        $this->client->submit($crawler->filter('form[action="/boulanger/'.$boulanger->getId().'"]')->form());

        $this->assertResponseRedirects('/boulanger/'.$boulanger->getId().'/edit');
        $this->client->followRedirect();
        $this->assertStringContainsString('il ne peut pas être supprimé', $this->messagesFlash());
        $this->assertNotNull($this->recharger(Boulanger::class, $boulanger->getId()));
    }

    public function testCreerUnCompte(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Créer le compte')->form([
            'registration_form[username]' => 'benevole',
            'registration_form[plainPassword]' => 'pain2026',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseRedirects('/user/');
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'benevole']);
        $this->assertNotNull($user);
        $this->assertNotSame('pain2026', $user->getPassword());
        $this->assertTrue(password_verify('pain2026', $user->getPassword()));

        // Le nouveau compte peut se connecter
        $this->client->request('GET', '/logout');
        $this->connecter('benevole', 'pain2026');
    }

    public function testCreationDeCompteValidee(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer le compte')->form([
            'registration_form[username]' => 'benevole',
            'registration_form[plainPassword]' => '123',
        ]));

        $this->assertFalse($this->client->getResponse()->isRedirect());
        $this->assertSelectorTextContains('body', 'au moins 6 caractères');
        $this->assertSelectorTextContains('body', 'Cocher la case !');
        $this->assertNull($this->em->getRepository(User::class)->findOneBy(['username' => 'benevole']));
    }

    public function testListeEtFicheDesComptes(): void
    {
        $user = $this->creerUtilisateur('benevole');

        $this->client->request('GET', '/user/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'benevole');

        $this->client->request('GET', '/user/'.$user->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testSupprimerUnCompte(): void
    {
        $user = $this->creerUtilisateur('benevole');

        $crawler = $this->client->request('GET', '/user/'.$user->getId());
        $this->client->submit($crawler->filter('form[action="/user/'.$user->getId().'"]')->form());

        $this->assertResponseRedirects('/user/');
        $this->client->followRedirect();
        $this->assertStringContainsString('Utilisateur supprimé !', $this->messagesFlash());
        $this->assertNull($this->recharger(User::class, $user->getId()));
    }

    public function testLePremierCompteEstProtege(): void
    {
        // « admin » (connecté dans setUp) porte l'identifiant 1
        $this->connecter('autre', 'secret123');
        $admin = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $this->assertSame(1, $admin->getId());

        $crawler = $this->client->request('GET', '/user/'.$admin->getId());
        $this->client->submit($crawler->filter('form[action="/user/1"]')->form());

        $this->client->followRedirect();
        $this->assertStringContainsString('Utilisateur protégé !', $this->messagesFlash());
        $this->assertNotNull($this->recharger(User::class, 1));
    }

    public function testImpossibleDeSupprimerSonPropreCompte(): void
    {
        $this->connecter('autre', 'secret123');
        $autre = $this->em->getRepository(User::class)->findOneBy(['username' => 'autre']);

        $crawler = $this->client->request('GET', '/user/'.$autre->getId());
        $this->client->submit($crawler->filter('form[action="/user/'.$autre->getId().'"]')->form());

        $this->client->followRedirect();
        $this->assertStringContainsString('Impossible de supprimer l\'utilisateur connecté !', $this->messagesFlash());
        $this->assertNotNull($this->recharger(User::class, $autre->getId()));
    }

    public function testModifierLaCouleur(): void
    {
        $couleur = $this->creerParametre('color', 'orange');
        $this->creerParametre('name', 'Fournil');

        $crawler = $this->client->request('GET', '/settings/');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href="/settings/'.$couleur->getId().'/edit"]'));

        $crawler = $this->client->request('GET', '/settings/'.$couleur->getId().'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['settings[value]' => 'green']));

        $this->assertResponseRedirects('/settings/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Le paramètre a été mis à jour.', $this->messagesFlash());
        $this->assertSame('green', $this->recharger(Settings::class, $couleur->getId())->getValue());
        $this->assertCount(1, $crawler->filter('nav.navbar-green'));
        $this->assertStringContainsString('Fournil', $crawler->filter('title')->text());
    }

    public function testModifierLAdresseDeContact(): void
    {
        $email = $this->creerParametre('contact_email', null);

        $crawler = $this->client->request('GET', '/settings/'.$email->getId().'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['settings[value]' => 'contact@example.org']));

        $this->assertResponseRedirects('/settings/');
        $this->assertSame('contact@example.org', $this->recharger(Settings::class, $email->getId())->getValue());
        $crawler = $this->client->request('GET', '/');
        $this->assertCount(1, $crawler->filter('a[aria-label="Nous écrire"]'));
    }

    public function testModifierLeTexteDAccueil(): void
    {
        $texte = $this->creerParametre('welcome_text', 'Bonjour');

        $crawler = $this->client->request('GET', '/settings/'.$texte->getId().'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['settings[value]' => 'Bienvenue au <b>fournil</b>']));

        $this->assertResponseRedirects('/settings/');
        $crawler = $this->client->request('GET', '/');
        $this->assertSame('fournil', $crawler->filter('h1.titre-accueil b')->text());
    }

    public function testEnvoyerUnLogo(): void
    {
        $logo = $this->creerParametre('logo', null);
        $dossier = static::getContainer()->getParameter('logo_directory');
        $fichier = sys_get_temp_dir().'/logo-test.png';
        copy(dirname(__DIR__, 2).'/public/icons/favicon-32.png', $fichier);

        $crawler = $this->client->request('GET', '/settings/'.$logo->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['settings[value]']->upload($fichier);
        $this->client->submit($form);

        $this->assertResponseRedirects('/settings/');
        $nom = $this->recharger(Settings::class, $logo->getId())->getValue();
        $this->assertMatchesRegularExpression('/^logotest-[0-9a-f]+\.png$/', $nom);
        $this->assertFileExists($dossier.'/'.$nom);
        unlink($dossier.'/'.$nom);

        $crawler = $this->client->request('GET', '/');
        $this->assertSame('/uploads/logo/'.$nom, $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
    }
}
