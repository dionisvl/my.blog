<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminCommentManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function toggleStatus(Comment $comment): Comment
    {
        $comment->toggleStatus();
        $this->entityManager->flush();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}
