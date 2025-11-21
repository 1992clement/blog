<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * @return Article[]
     */
    public function search(string $criteria): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.title LIKE :criteria')
            ->setParameter('criteria', '%' . $criteria . '%')
            ->orderBy('a.creationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
