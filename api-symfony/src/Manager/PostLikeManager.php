<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Post;
use App\Entity\PostLike;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PostLikeManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function removeLikeForPostAt(int $postId, \DateTimeInterface $createdAt): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PostLike::class, 'pl')
            ->where('pl.post = :postId')
            ->andWhere('pl.createdAt = :createdAt')
            ->setParameter('postId', $postId)
            ->setParameter('createdAt', $createdAt)
            ->getQuery()
            ->execute();
    }

    public function addLike(Post $post, ?int $deviceMemory): PostLike
    {
        $postLike = new PostLike();
        $postLike->setPost($post);

        if (null !== $deviceMemory) {
            $postLike->setDeviceMemory($deviceMemory);
        }

        $this->entityManager->persist($postLike);
        $this->entityManager->flush();

        return $postLike;
    }
}
