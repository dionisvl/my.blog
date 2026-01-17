<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PostViewManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function increment(Post $post): void
    {
        $post->incrementViewsCount();
        $this->entityManager->flush();
    }
}
