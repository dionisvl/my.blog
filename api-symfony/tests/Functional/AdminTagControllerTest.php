<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminTagControllerTest extends DatabaseWebTestCase
{
    public static function provideTagPayloads(): iterable
    {
        yield 'short title' => ['PHP'];

        yield 'compound title' => ['Symfony Framework'];
    }

    #[DataProvider('provideTagPayloads')]
    public function testCreateUpdateDeleteTag(string $title): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/tags/store', [
            'title' => $title,
        ]);

        $this->assertResponseRedirects('/admin/tags/');

        $this->em->clear();
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['title' => $title]);

        self::assertNotNull($tag);

        $newTitle = $title . ' Updated';

        $this->client->request(Request::METHOD_POST, '/admin/tags/' . $tag->getId() . '/update', [
            'id' => $tag->getId(),
            'title' => $newTitle,
        ]);

        $this->assertResponseRedirects('/admin/tags/');

        $this->em->clear();
        $updated = $this->em->getRepository(Tag::class)->find($tag->getId());

        self::assertNotNull($updated);
        self::assertSame($newTitle, $updated->getTitle());

        $this->client->request(Request::METHOD_POST, '/admin/tags/' . $tag->getId() . '/delete');

        $this->assertResponseRedirects('/admin/tags/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Tag::class)->find($tag->getId());
        self::assertNull($deleted);
    }

    public function testTagIndexView(): void
    {
        $user = $this->createAdminUser();
        $tag = new Tag();
        $tag->setTitle('Docker');
        $tag->setSlug('docker');

        $this->em->persist($tag);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/tags/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Docker', $this->client->getResponse()->getContent());
    }

    public function testTagCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/tags/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_tags_store');
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testTagEditView(): void
    {
        $user = $this->createAdminUser();
        $tag = new Tag();
        $tag->setTitle('Testing');
        $tag->setSlug('testing');

        $this->em->persist($tag);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/tags/' . $tag->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_tags_update', ['id' => $tag->getId()]);
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testTagValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/tags/store', [
            'title' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('title', $this->client->getResponse()->getContent());
    }

    public function testTagSlugIsUnique(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/tags/store', [
            'title' => 'Same Tag',
        ]);
        $this->assertResponseRedirects('/admin/tags/');

        $this->client->request(Request::METHOD_POST, '/admin/tags/store', [
            'title' => 'Same Tag',
        ]);
        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('title', $this->client->getResponse()->getContent());

        $this->em->clear();
        $tags = $this->em->getRepository(Tag::class)->findBy(['title' => 'Same Tag']);
        self::assertCount(1, $tags);
    }
}
