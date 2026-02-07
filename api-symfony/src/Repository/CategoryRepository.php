<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<array{0: Category, posts_count: int|string}>
     */
    public function findWithPostCounts(): array
    {
        /** @var list<array{0: Category, posts_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as posts_count')
            ->leftJoin(Post::class, 'p', 'WITH', 'p.category = c AND p.status = false')
            ->groupBy('c.id')
            ->having('COUNT(p.id) > 0')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<array{0: Category, posts_count: int|string}>
     */
    public function findPopularWithPostCounts(int $minCount = 2): array
    {
        /** @var list<array{0: Category, posts_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as posts_count')
            ->leftJoin(Post::class, 'p', 'WITH', 'p.category = c AND p.status = false')
            ->groupBy('c.id')
            ->having('COUNT(p.id) >= :minCount')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->setParameter('minCount', $minCount)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
