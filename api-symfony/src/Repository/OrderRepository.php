<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
final class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<Order>
     */
    public function findAllOrderedByUpdatedAtDesc(): array
    {
        /** @var list<Order> $rows */
        $rows = $this->createQueryBuilder('o')
            ->orderBy('o.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
