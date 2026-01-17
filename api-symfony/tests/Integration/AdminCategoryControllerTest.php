<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Category;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminCategoryControllerTest extends DatabaseWebTestCase
{
    public static function provideCategoryPayloads(): iterable
    {
        yield 'simple content' => ['Backend', 'Detailed text', 'Preview text'];
        yield 'empty descriptions' => ['DevOps', null, null];
    }

    #[DataProvider('provideCategoryPayloads')]
    public function testCreateUpdateDeleteCategory(string $title, ?string $detail, ?string $preview): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/categories/store', [
            'title' => $title,
            'detail_text' => $detail,
            'preview_text' => $preview,
        ]);

        $this->assertResponseRedirects('/admin/categories/');

        $this->em->clear();
        $category = $this->em->getRepository(Category::class)->findOneBy(['title' => $title]);

        $this->assertNotNull($category);
        $this->assertContains($category->getDetailText(), [$detail, '']);
        $this->assertContains($category->getPreviewText(), [$preview, '']);

        $newTitle = $title . ' Updated';

        $this->client->request('POST', '/admin/categories/' . $category->getId() . '/update', [
            'id' => $category->getId(),
            'title' => $newTitle,
            'detail_text' => $detail,
            'preview_text' => $preview,
        ]);

        $this->assertResponseRedirects('/admin/categories/');

        $this->em->clear();
        $updated = $this->em->getRepository(Category::class)->find($category->getId());

        $this->assertNotNull($updated);
        $this->assertSame($newTitle, $updated->getTitle());

        $this->client->request('POST', '/admin/categories/' . $category->getId() . '/delete');

        $this->assertResponseRedirects('/admin/categories/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Category::class)->find($category->getId());
        $this->assertNull($deleted);
    }

    public function testCategoryIndexView(): void
    {
        $user = $this->createAdminUser();
        $category = new Category();
        $category->setTitle('Python');
        $category->setSlug('python');
        $this->em->persist($category);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/categories/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Python', $this->client->getResponse()->getContent());
    }

    public function testCategoryCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/categories/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_categories_store');
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testCategoryEditView(): void
    {
        $user = $this->createAdminUser();
        $category = new Category();
        $category->setTitle('Frontend');
        $category->setSlug('frontend');
        $this->em->persist($category);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/categories/' . $category->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_categories_update', ['id' => $category->getId()]
        );
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testCategoryValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/categories/store', [
            'title' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('title', $this->client->getResponse()->getContent());
    }

    public function testCategorySlugIsUnique(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/categories/store', [
            'title' => 'Same Title',
        ]);
        $this->assertResponseRedirects('/admin/categories/');

        $this->client->request('POST', '/admin/categories/store', [
            'title' => 'Same Title',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('title', $this->client->getResponse()->getContent());

        $this->em->clear();
        $categories = $this->em->getRepository(Category::class)->findBy(['title' => 'Same Title']);
        $this->assertCount(1, $categories);
    }
}
