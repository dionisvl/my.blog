<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Comment;
use App\Entity\Post;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminCommentControllerTest extends DatabaseWebTestCase
{
    public static function provideCommentStatuses(): iterable
    {
        yield 'draft comment' => [0];
        yield 'approved comment' => [1];
    }

    #[DataProvider('provideCommentStatuses')]
    public function testToggleAndDeleteComment(int $status): void
    {
        $user = $this->createAdminUser();
        $post = new Post();
        $post->setTitle('Comment Post');
        $post->setSlug('comment-post');
        $post->setStatus(true);
        $post->setAuthor($user);
        $this->em->persist($post);

        $comment = new Comment();
        $comment->setAuthorName('Jane');
        $comment->setText('Nice post');
        $comment->setPost($post);
        $comment->setAuthor($user);
        $comment->setStatus($status);
        $this->em->persist($comment);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/comments/' . $comment->getId() . '/toggle');

        $this->assertResponseRedirects('/admin/comments/');

        $this->em->clear();
        $updated = $this->em->getRepository(Comment::class)->find($comment->getId());
        $this->assertNotNull($updated);
        $this->assertSame($status === 1 ? 0 : 1, $updated->getStatus());

        $this->client->request('POST', '/admin/comments/' . $comment->getId() . '/delete');
        $this->assertResponseRedirects('/admin/comments/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Comment::class)->find($comment->getId());
        $this->assertNull($deleted);
    }

    public function testCommentsIndexView(): void
    {
        $user = $this->createAdminUser();
        $post = new Post();
        $post->setTitle('Another Post');
        $post->setSlug('another-post');
        $post->setStatus(true);
        $post->setAuthor($user);
        $this->em->persist($post);

        $comment = new Comment();
        $comment->setAuthorName('John');
        $comment->setText('Hello!');
        $comment->setPost($post);
        $comment->setAuthor($user);
        $comment->setStatus(1);
        $this->em->persist($comment);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/comments/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Hello!', $this->client->getResponse()->getContent());
    }
}
