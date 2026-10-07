<?php

namespace App\Tests\Unit;

use App\Entity\Commande;
use App\Entity\JourDistrib;
use App\Entity\LigneCommande;
use App\Entity\Pain;
use PHPUnit\Framework\TestCase;

class CommandeTest extends TestCase
{
    public function testLignesTrieesSelonOrdreDesPains(): void
    {
        $seigle = (new Pain())->setNom('Seigle')->setPosition(2);
        $blanc = (new Pain())->setNom('Blanc')->setPosition(1);
        $commande = new Commande();
        $commande->addLigneCommande((new LigneCommande())->setPain($seigle)->setQuantite(1));
        $commande->addLigneCommande((new LigneCommande())->setPain($blanc)->setQuantite(3));

        $lignes = $commande->getLignesTriees();

        $this->assertSame('Blanc', $lignes[0]->getPain()->getNom());
        $this->assertSame('Seigle', $lignes[1]->getPain()->getNom());
    }

    public function testAssociationsBidirectionnelles(): void
    {
        $jour = new JourDistrib();
        $commande = new Commande();
        $ligne = new LigneCommande();

        $jour->addCommande($commande);
        $commande->addLigneCommande($ligne);
        $this->assertSame($jour, $commande->getJourDistrib());
        $this->assertSame($commande, $ligne->getCommande());

        $commande->removeLigneCommande($ligne);
        $jour->removeCommande($commande);
        $this->assertNull($ligne->getCommande());
        $this->assertNull($commande->getJourDistrib());
        $this->assertCount(0, $jour->getCommandes());
    }
}
