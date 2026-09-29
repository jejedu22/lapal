<?php

namespace App\Repository;

use App\Entity\JourDistrib;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Commande;
use App\Entity\Pain;
use App\Entity\LigneCommande;

/**
 * @method JourDistrib|null find($id, $lockMode = null, $lockVersion = null)
 * @method JourDistrib|null findOneBy(array $criteria, array $orderBy = null)
 * @method JourDistrib[]    findAll()
 * @method JourDistrib[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JourDistribRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourDistrib::class);
    }

    // /**
    //  * @return JourDistrib[] Returns an array of JourDistrib objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('j.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */
    public function findAllOrder($order, $limit = 40)
    {
        return $this->findBy(array(), array('date' => $order),$limit);
    }

    public function findAllActive()
    {
        $entityManager = $this->getEntityManager();
        
        $now = date('Y-m-d');

        $query = $entityManager->createQuery(
            'SELECT j
            FROM App\Entity\JourDistrib j
            WHERE j.date >= :dateNow
            ORDER BY j.date ASC'
        )
        ->setParameter('dateNow', $now);


        return $query->getResult();
    }

    public function findPoid()
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT c.id, p.id, j.total, SUM(p.poid) as total_commande
            FROM App\Entity\JourDistrib j
            INNER JOIN j.commandes c
            INNER JOIN c.ligneCommandes lc
            INNER JOIN lc.pain p
            GROUP BY c.id, j.total, p.id'
        );

        return $query->getResult();
    }

    public function findPoidPains( $jourDistribId, $painId )
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT SUM(p.poid*lc.quantite) as poid
            FROM App\Entity\JourDistrib j
            INNER JOIN j.commandes c
            INNER JOIN c.ligneCommandes lc
            INNER JOIN lc.pain p
            WHERE j.id = :jourDistribId
            AND p.id = :painId
            GROUP BY p.id'
            )
            ->setParameter('jourDistribId', $jourDistribId)
            ->setParameter('painId', $painId);

        return $query->getResult();
    }
    /*
    public function findOneBySomeField($value): ?JourDistrib
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * Quantités commandées par pain pour une liste de jours, en une seule requête.
     *
     * @return array<int, array<int, array{pain: int, nom: string, poid: float, prix: float, quantite: int}>>
     *         indexé par id de jour puis par id de pain
     */
    public function findQuantitesParPain(array $jourIds): array
    {
        if (empty($jourIds)) {
            return [];
        }

        $lignes = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(c.jourDistrib) AS jour, p.id AS pain, p.nom, p.poid, p.prix, SUM(lc.quantite) AS quantite
            FROM App\Entity\LigneCommande lc
            INNER JOIN lc.commande c
            INNER JOIN lc.pain p
            WHERE c.jourDistrib IN (:jours)
            GROUP BY c.jourDistrib, p.id, p.nom, p.poid, p.prix, p.position
            ORDER BY p.position ASC, p.nom ASC'
        )
        ->setParameter('jours', $jourIds)
        ->getArrayResult();

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[(int) $ligne['jour']][(int) $ligne['pain']] = [
                'pain' => (int) $ligne['pain'],
                'nom' => $ligne['nom'],
                'poid' => (float) $ligne['poid'],
                'prix' => (float) $ligne['prix'],
                'quantite' => (int) $ligne['quantite'],
            ];
        }

        return $resultat;
    }
}
