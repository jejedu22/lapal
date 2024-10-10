<?php

namespace App\Repository;

use App\Entity\Boulanger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Boulanger|null find($id, $lockMode = null, $lockVersion = null)
 * @method Boulanger|null findOneBy(array $criteria, array $orderBy = null)
 * @method Boulanger[]    findAll()
 * @method Boulanger[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoulangerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boulanger::class);
    }

    // /**
    //  * @return Boulanger[] Returns an array of Boulanger objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Boulanger
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
