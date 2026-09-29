<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PainRepository")
 */
class Pain
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\Column(type="float")
     */
    private $poid;

    /**
     * @ORM\Column(type="float")
     */
    private $prix;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneCommande", mappedBy="pain", cascade={"persist"})
     */
    private $ligneCommandes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\JourDistrib", mappedBy="pains")
     */
    private $jourDistribs;

    /**
     * Un pain archivé n'est plus proposé pour les nouveaux jours de distribution.
     *
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $actif = true;

    /**
     * Rang d'affichage, choisi dans l'administration (le plus petit en premier).
     *
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private $position = 0;

    public function __construct()
    {
        $this->ligneCommandes = new ArrayCollection();
        $this->jourDistribs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPoid(): ?float
    {
        return $this->poid;
    }

    public function setPoid(float $poid): self
    {
        $this->poid = $poid;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): self
    {
        $this->prix = $prix;

        return $this;
    }

    /**
     * @return Collection|LigneCommande[]
     */
    public function getLigneCommandes(): Collection
    {
        return $this->ligneCommandes;
    }

    public function addLigneCommande(LigneCommande $ligneCommande): self
    {
        if (!$this->ligneCommandes->contains($ligneCommande)) {
            $this->ligneCommandes[] = $ligneCommande;
            $ligneCommande->setPain($this);
        }

        return $this;
    }

    public function removeLigneCommande(LigneCommande $ligneCommande): self
    {
        if ($this->ligneCommandes->contains($ligneCommande)) {
            $this->ligneCommandes->removeElement($ligneCommande);
            // set the owning side to null (unless already changed)
            if ($ligneCommande->getPain() === $this) {
                $ligneCommande->setPain(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|JourDistrib[]
     */
    public function getJourDistribs(): Collection
    {
        return $this->jourDistribs;
    }

    public function addJourDistrib(JourDistrib $jourDistrib): self
    {
        if (!$this->jourDistribs->contains($jourDistrib)) {
            $this->jourDistribs[] = $jourDistrib;
            $jourDistrib->addPain($this);
        }

        return $this;
    }

    public function removeJourDistrib(JourDistrib $jourDistrib): self
    {
        if ($this->jourDistribs->contains($jourDistrib)) {
            $this->jourDistribs->removeElement($jourDistrib);
            $jourDistrib->removePain($this);
        }

        return $this;
    }

    public function getActif(): bool
    {
        return (bool) $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getPosition(): int
    {
        return (int) $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Ordre d'affichage des pains : rang choisi, puis nom.
     */
    public static function comparer(Pain $a, Pain $b): int
    {
        return [$a->getPosition(), mb_strtolower($a->getNom())] <=> [$b->getPosition(), mb_strtolower($b->getNom())];
    }

    public function getLibelle(): string
    {
        return $this->nom . ' - ' . str_replace('.', ',', (string) $this->poid) . ' kg';
    }
}
