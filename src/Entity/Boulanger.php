<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BoulangerRepository")
 */
class Boulanger
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
     * @ORM\Column(type="string", length=255)
     */
    private $prenom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\JourDistrib", mappedBy="boulanger")
     */
    private $jourDistribs;

    public function __construct()
    {
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

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
            $jourDistrib->setBoulanger($this);
        }

        return $this;
    }

    public function removeJourDistrib(JourDistrib $jourDistrib): self
    {
        if ($this->jourDistribs->contains($jourDistrib)) {
            $this->jourDistribs->removeElement($jourDistrib);
            // set the owning side to null (unless already changed)
            if ($jourDistrib->getBoulanger() === $this) {
                $jourDistrib->setBoulanger(null);
            }
        }

        return $this;
    }
}
