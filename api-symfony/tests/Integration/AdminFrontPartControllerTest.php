<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\FrontPart;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminFrontPartControllerTest extends DatabaseWebTestCase
{
    public static function provideFrontPartPayloads(): iterable
    {
        yield 'simple component' => ['Hero Banner', 'UI', 'JS', 'Preview text', 'Detail text', '1'];
        yield 'css snippet' => ['Buttons', 'UI', 'CSS', null, null, '0'];
    }

    #[DataProvider('provideFrontPartPayloads')]
    public function testCreateUpdateDeleteFrontPart(
        string $title,
        ?string $categoryName,
        ?string $type,
        ?string $previewText,
        ?string $detailText,
        string $status
    ): void {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/frontparts/store', [
            'title' => $title,
            'category_name' => $categoryName,
            'type' => $type,
            'preview_text' => $previewText,
            'detail_text' => $detailText,
            'status' => $status,
        ]);

        $this->assertResponseRedirects('/admin/frontparts/');

        $this->em->clear();
        $frontPart = $this->em->getRepository(FrontPart::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($frontPart);
        $this->assertSame($status === '1' ? '1' : '0', $frontPart->getStatus());

        $newTitle = $title . ' Updated';
        $this->client->request('POST', '/admin/frontparts/' . $frontPart->getId() . '/update', [
            'title' => $newTitle,
            'category_name' => $categoryName,
            'type' => $type,
            'preview_text' => $previewText,
            'detail_text' => $detailText,
            'status' => $status,
        ]);

        $this->assertResponseRedirects('/admin/frontparts/');

        $this->em->clear();
        $updated = $this->em->getRepository(FrontPart::class)->find($frontPart->getId());
        $this->assertNotNull($updated);
        $this->assertSame($newTitle, $updated->getTitle());

        $this->client->request('POST', '/admin/frontparts/' . $frontPart->getId() . '/delete');
        $this->assertResponseRedirects('/admin/frontparts/');

        $this->em->clear();
        $deleted = $this->em->getRepository(FrontPart::class)->find($frontPart->getId());
        $this->assertNull($deleted);
    }

    public function testFrontPartsIndexView(): void
    {
        $user = $this->createAdminUser();
        $frontPart = new FrontPart();
        $frontPart->setTitle('Index Component');
        $frontPart->setSlug('index-component');
        $this->em->persist($frontPart);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/frontparts/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Index Component', $this->client->getResponse()->getContent());
    }

    public function testFrontPartCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/frontparts/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_frontparts_store');
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testFrontPartEditView(): void
    {
        $user = $this->createAdminUser();
        $frontPart = new FrontPart();
        $frontPart->setTitle('Edit Component');
        $frontPart->setSlug('edit-component');
        $this->em->persist($frontPart);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/frontparts/' . $frontPart->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_frontparts_update', ['id' => $frontPart->getId()]
        );
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testFrontPartValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/frontparts/store', [
            'title' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('title', $this->client->getResponse()->getContent());
    }
}
