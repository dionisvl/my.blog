<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Portfolio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PortfolioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Portfolio::class);
    }

    /**
     * @return list<Portfolio>
     */
    public function findAllOrderedByUpdatedAtDesc(): array
    {
        /** @var list<Portfolio> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
