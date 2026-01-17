<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incoming;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class IncomingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incoming::class);
    }
}
