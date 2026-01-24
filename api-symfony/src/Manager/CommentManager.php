<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CommentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
    ) {
    }

    public function createComment(Post $post, ?User $user, string $message): Comment
    {
        $comment = new Comment();
        $comment->setText($message);
        $comment->setPost($post);

        if ($user instanceof User) {
            $comment->setAuthor($user);
            $comment->setAuthorName($user->getName());
        } else {
            $anonymousUser = $this->users->find(777);

            if ($anonymousUser instanceof User) {
                $comment->setAuthor($anonymousUser);
            }
            $comment->setAuthorName('anon');
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }
}
