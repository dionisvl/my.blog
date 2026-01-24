<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Incoming;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminIncomingManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function toggleStatus(Incoming $incoming): Incoming
    {
        $incoming->setStatus(1 === $incoming->getStatus() ? 0 : 1);
        $this->entityManager->flush();

        return $incoming;
    }

    public function delete(Incoming $incoming): void
    {
        $this->entityManager->remove($incoming);
        $this->entityManager->flush();
    }
}
