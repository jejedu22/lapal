<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\JourDistribRepository")
 */
class JourDistrib
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Pain", inversedBy="jourDistribs")
     */
    private $pains;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Commande", mappedBy="jourDistrib", cascade={"persist", "remove"})
     */
    private $commandes;

    /**
     * @ORM\Column(type="float")
     */
    private $total;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $poidRestant;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $closed;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Boulanger", inversedBy="jourDistribs")
     */
    private $boulanger;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentaire;

    public function __construct()
    {
        $this->pains = new ArrayCollection();
        $this->commandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection|Pain[]
     */
    public function getPains(): Collection
    {
        return $this->pains;
    }

    public function addPain(Pain $pain): self
    {
        if (!$this->pains->contains($pain)) {
            $this->pains[] = $pain;
        }

        return $this;
    }

    public function removePain(Pain $pain): self
    {
        if ($this->pains->contains($pain)) {
            $this->pains->removeElement($pain);
        }

        return $this;
    }

    /**
     * @return Collection|Commande[]
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): self
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes[] = $commande;
            $commande->setJourDistrib($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): self
    {
        if ($this->commandes->contains($commande)) {
            $this->commandes->removeElement($commande);
            // set the owning side to null (unless already changed)
            if ($commande->getJourDistrib() === $this) {
                $commande->setJourDistrib(null);
            }
        }

        return $this;
    }
    public function __toString() {
        // to show the name of the Category in the select
        if(is_null($this->date)) {
            return 'NULL';
        }  
        return gettype($this->date);
        // to show the id of the Category in the select
        // return $this->id;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getPoidRestant(): ?float
    {
        return $this->poidRestant;
    }

    public function setPoidRestant(?float $poidRestant): self
    {
        $this->poidRestant = $poidRestant;

        return $this;
    }

    public function getClosed(): ?bool
    {
        return $this->closed;
    }

    public function setClosed(?bool $closed): self
    {
        $this->closed = $closed;

        return $this;
    }

    public function getBoulanger(): ?Boulanger
    {
        return $this->boulanger;
    }

    public function setBoulanger(?Boulanger $boulanger): self
    {
        $this->boulanger = $boulanger;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}
