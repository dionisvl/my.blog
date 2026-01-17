<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminSubscriberPayload;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminSubscriberManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(AdminSubscriberPayload $payload): Subscription
    {
        $subscription = new Subscription();
        $subscription->setEmail($payload->email);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    public function delete(Subscription $subscription): void
    {
        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }
}
