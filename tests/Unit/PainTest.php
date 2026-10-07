<?php

namespace App\Tests\Unit;

use App\Entity\Pain;
use PHPUnit\Framework\TestCase;

class PainTest extends TestCase
{
    private function pain(string $nom, int $position = 0, float $poid = 1): Pain
    {
        return (new Pain())->setNom($nom)->setPosition($position)->setPoid($poid)->setPrix(4);
    }

    public function testValeursParDefaut(): void
    {
        $pain = new Pain();

        $this->assertTrue($pain->getActif());
        $this->assertSame(0, $pain->getPosition());
        $this->assertCount(0, $pain->getLigneCommandes());
        $this->assertCount(0, $pain->getJourDistribs());
    }

    public function testComparerParPositionPuisParNom(): void
    {
        $pains = [
            $this->pain('seigle', 2),
            $this->pain('Complet', 1),
            $this->pain('blanc', 1),
            $this->pain('Épeautre', 0),
        ];
        usort($pains, [Pain::class, 'comparer']);

        $this->assertSame(['Épeautre', 'blanc', 'Complet', 'seigle'], array_map(function (Pain $p) {
            return $p->getNom();
        }, $pains));
    }

    public function testLibelle(): void
    {
        $this->assertSame('Campagne - 1,5 kg', $this->pain('Campagne', 0, 1.5)->getLibelle());
        $this->assertSame('Boule - 1 kg', $this->pain('Boule', 0, 1)->getLibelle());
    }
}
