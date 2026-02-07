<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FrontPart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FrontPart>
 */
final class FrontPartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FrontPart::class);
    }

    /**
     * @return list<FrontPart>
     */
    public function findAllOrderedByUpdatedAtDesc(): array
    {
        /** @var list<FrontPart> $rows */
        $rows = $this->createQueryBuilder('f')
            ->orderBy('f.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findOneBySlug(string $slug): ?FrontPart
    {
        /** @var FrontPart|null $frontPart */
        $frontPart = $this->findOneBy(['slug' => $slug]);

        return $frontPart;
    }
}
