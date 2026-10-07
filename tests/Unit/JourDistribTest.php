<?php

namespace App\Tests\Unit;

use App\Entity\JourDistrib;
use PHPUnit\Framework\TestCase;

class JourDistribTest extends TestCase
{
    private function jour(string $date, float $total = 10, float $commande = 0, bool $ferme = false): JourDistrib
    {
        return (new JourDistrib())
            ->setDate(new \DateTime($date))
            ->setTotal($total)
            ->setPoidRestant($commande)
            ->setClosed($ferme);
    }

    public function testPoidsDisponibleEtRemplissage(): void
    {
        $jour = $this->jour('+3 days', 20, 5.5);

        $this->assertSame(5.5, $jour->getPoidsCommande());
        $this->assertSame(14.5, $jour->getPoidsDisponible());
        $this->assertSame(28, $jour->getTauxRemplissage());
    }

    public function testPoidsDisponibleJamaisNegatif(): void
    {
        $jour = $this->jour('+3 days', 10, 12);

        $this->assertSame(0.0, $jour->getPoidsDisponible());
        $this->assertSame(100, $jour->getTauxRemplissage());
    }

    public function testRemplissageSansFournee(): void
    {
        $this->assertSame(100, $this->jour('+3 days', 0)->getTauxRemplissage());
    }

    public function testPoidsCommandeNonRenseigne(): void
    {
        $jour = (new JourDistrib())->setDate(new \DateTime('+1 day'))->setTotal(8);

        $this->assertSame(0.0, $jour->getPoidsCommande());
        $this->assertSame(8.0, $jour->getPoidsDisponible());
    }

    public function testStatutOuvert(): void
    {
        $jour = $this->jour('+3 days');

        $this->assertSame('ouvert', $jour->getStatut());
        $this->assertTrue($jour->isOuvert());
        $this->assertFalse($jour->isPasse());
        $this->assertFalse($jour->isAujourdhui());
    }

    public function testStatutComplet(): void
    {
        $jour = $this->jour('+3 days', 10, 10);

        $this->assertSame('complet', $jour->getStatut());
        $this->assertFalse($jour->isOuvert());
    }

    public function testStatutFerme(): void
    {
        $this->assertSame('ferme', $this->jour('+3 days', 10, 0, true)->getStatut());
    }

    public function testStatutAujourdhui(): void
    {
        $jour = $this->jour('today');

        $this->assertTrue($jour->isAujourdhui());
        $this->assertSame('aujourdhui', $jour->getStatut());
    }

    public function testStatutPasseEstPrioritaire(): void
    {
        $jour = $this->jour('-2 days', 10, 0, true);

        $this->assertTrue($jour->isPasse());
        $this->assertSame('passe', $jour->getStatut());
    }

    public function testFermeturePrioritaireSurAujourdhui(): void
    {
        $this->assertSame('ferme', $this->jour('today', 10, 0, true)->getStatut());
    }
}
