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
    public function findPoid()
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT c.id, j.total, SUM(p.poid) as total_commande
            FROM App\Entity\JourDistrib j
            INNER JOIN j.commandes c
            INNER JOIN c.ligneCommandes lc
            INNER JOIN lc.pain p
            GROUP BY c.id, j.total'
        );

        return $query->getResult();
        ;
    }

    public function findPoids($jourDistribId)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT p.nom, p.poid, SUM(p.poid) as poid_pain
            FROM App\Entity\JourDistrib j
            INNER JOIN j.commandes c
            INNER JOIN c.ligneCommandes lc
            INNER JOIN lc.pain p
            WHERE j.id = :jourDistribId
            GROUP BY p.id'
            )->setParameter('jourDistribId', $jourDistribId);

        return $query->getResult();
        ;
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
}
