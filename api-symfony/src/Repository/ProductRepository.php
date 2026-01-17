<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return list<Product>
     */
    public function findAllOrderedByUpdatedAtDesc(): array
    {
        /** @var list<Product> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findOneBySlug(string $slug): ?Product
    {
        /** @var Product|null $product */
        $product = $this->findOneBy(['slug' => $slug]);
        return $product;
    }
}
