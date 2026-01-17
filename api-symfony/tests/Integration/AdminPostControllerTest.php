<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminPostControllerTest extends DatabaseWebTestCase
{
    public static function providePostFormData(): iterable
    {
        yield 'published featured' => ['0', '1', '2024-01-02'];
        yield 'draft not featured' => ['1', '0', '2023-12-31'];
    }

    public static function provideAdminViews(): iterable
    {
        yield 'create view' => ['/admin/posts/create', 'admin_posts_store'];
        yield 'edit view' => ['/admin/posts/%d/edit', 'admin_posts_update'];
    }

    #[DataProvider('provideAdminViews')]
    public function testAdminViewsAreReachable(string $pathTemplate, string $routeName): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('UX', 'ux');
        $post = $this->createPost($user, $category, 'Edit Me', 'edit-me', false);

        $path = str_contains($pathTemplate, '%d')
            ? sprintf($pathTemplate, (int)$post->getId())
            : $pathTemplate;

        $this->client->loginUser($user);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $routeParams = $routeName === 'admin_posts_update' && $post->getId()
            ? ['id' => $post->getId()]
            : [];
        $action = self::getContainer()->get('router')->generate($routeName, $routeParams);
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    private function createCategory(string $title, string $slug): Category
    {
        $category = new Category();
        $this->setPrivate($category, 'title', $title);
        $this->setPrivate($category, 'slug', $slug);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    private function createPost(
        User $user,
        Category $category,
        string $title,
        string $slug,
        bool $published = false
    ): Post {
        $post = new Post();
        $post->setTitle($title);
        $post->setSlug($slug);
        $post->setContent('Content');
        $post->setDescription('Description');
        $post->setStatus($published);
        $post->setIsFeatured(false);
        $post->setAuthor($user);
        $post->setCategory($category);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    #[DataProvider('providePostFormData')]
    public function testCreatePostViaAdminForm(string $status, string $isFeatured, string $date): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('Programming', 'programming');
        $tag = $this->createTag('Symfony', 'symfony');

        $this->client->loginUser($user);

        $title = 'New Post ' . $date;

        $this->client->request('POST', '/admin/posts/store', [
            'title' => $title,
            'content' => 'Body text',
            'description' => 'Short description',
            'date' => $date,
            'category_id' => (string)$category->getId(),
            'status' => $status,
            'is_featured' => $isFeatured,
            'tags' => [$tag->getId()],
        ]);

        $this->assertResponseRedirects('/admin/posts/');

        $this->em->clear();
        $post = $this->em->getRepository(Post::class)->findOneBy(['title' => $title]);

        $this->assertNotNull($post);
        $this->assertSame($title, $post->getTitle());
        $this->assertSame($date, $post->getCreatedAt()->format('Y-m-d'));
        $this->assertSame((bool)filter_var($status, FILTER_VALIDATE_BOOLEAN), $post->getStatus());
        $this->assertSame((bool)filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN), $post->isFeatured());
        $this->assertCount(1, $post->getTags());
    }

    private function createTag(string $title, string $slug): Tag
    {
        $tag = new Tag();
        $tag->setTitle($title);
        $tag->setSlug($slug);

        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    #[DataProvider('providePostFormData')]
    public function testUpdatePostViaAdminForm(string $status, string $isFeatured, string $date): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('Tech', 'tech');
        $tag = $this->createTag('PHP', 'php');
        $post = $this->createPost($user, $category, 'Old Title', 'old-title');

        $this->client->loginUser($user);

        $newTitle = 'Updated ' . $date;

        $this->client->request('POST', '/admin/posts/' . $post->getId() . '/update', [
            'title' => $newTitle,
            'content' => 'Updated content',
            'description' => 'Updated description',
            'date' => $date,
            'category_id' => (string)$category->getId(),
            'status' => $status,
            'is_featured' => $isFeatured,
            'tags' => [$tag->getId()],
        ]);

        $this->assertResponseRedirects('/admin/posts/');

        $this->em->clear();
        $updated = $this->em->getRepository(Post::class)->find($post->getId());

        $this->assertNotNull($updated);
        $this->assertSame($newTitle, $updated->getTitle());
        $this->assertSame($date, $updated->getCreatedAt()->format('Y-m-d'));
        $this->assertSame((bool)filter_var($status, FILTER_VALIDATE_BOOLEAN), $updated->getStatus());
        $this->assertSame((bool)filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN), $updated->isFeatured());
        $this->assertCount(1, $updated->getTags());
    }

    public function testOpenPostPage(): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('PHP', 'php');
        $post = $this->createPost($user, $category, 'Public Post', 'public-post', false);

        $this->client->request('GET', '/post/' . $post->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Public Post', $this->client->getResponse()->getContent());
    }

    public function testDeletePostViaAdminForm(): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('DevOps', 'devops');
        $post = $this->createPost($user, $category, 'To Delete', 'to-delete');
        $postId = $post->getId();

        $this->assertNotNull($postId);
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/posts/' . $postId . '/delete');

        $this->assertResponseRedirects('/admin/posts/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Post::class)->find($postId);
        $this->assertNull($deleted);
    }

    public function testEditedPostAppearsOnHomeAndShowPages(): void
    {
        $user = $this->createAdminUser();
        $category = $this->createCategory('Site', 'site');

        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/posts/store', [
            'title' => 'Original Title',
            'content' => 'Initial content',
            'description' => 'Initial description',
            'date' => '2024-02-01',
            'category_id' => (string)$category->getId(),
            'status' => '0',
            'is_featured' => '0',
        ]);

        $this->assertResponseRedirects('/admin/posts/');

        $this->em->clear();
        $post = $this->em->getRepository(Post::class)->findOneBy(['title' => 'Original Title']);
        $this->assertNotNull($post);

        $postId = $post->getId();
        $this->assertNotNull($postId);

        $this->client->request('POST', '/admin/posts/' . $postId . '/update', [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'description' => 'Updated description',
            'date' => '2024-02-02',
            'category_id' => (string)$category->getId(),
            'status' => '0',
            'is_featured' => '0',
        ]);

        $this->assertResponseRedirects('/admin/posts/');

        $this->em->clear();
        $updated = $this->em->getRepository(Post::class)->find($postId);
        $this->assertNotNull($updated);

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Updated Title', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/post/' . $updated->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Updated Title', $this->client->getResponse()->getContent());
    }
}
