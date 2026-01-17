<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Portfolio;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminPortfolioControllerTest extends DatabaseWebTestCase
{
    public static function providePortfolioPayloads(): iterable
    {
        yield 'basic' => ['Portfolio One', 'Content', 'Description', true, false];
        yield 'featured draft' => ['Portfolio Two', null, null, false, true];
    }

    #[DataProvider('providePortfolioPayloads')]
    public function testCreateUpdateDeletePortfolio(
        string $title,
        ?string $content,
        ?string $description,
        bool $status,
        bool $featured
    ): void {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/portfolios/store', [
            'title' => $title,
            'content' => $content,
            'description' => $description,
            'status' => $status ? '1' : null,
            'is_featured' => $featured ? '1' : null,
        ]);

        $this->assertResponseRedirects('/admin/portfolios/');

        $this->em->clear();
        $portfolio = $this->em->getRepository(Portfolio::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($portfolio);
        $this->assertSame($status ? 1 : 0, $portfolio->getStatus());
        $this->assertSame($featured ? 1 : 0, $portfolio->getIsFeatured());

        $newTitle = $title . ' Updated';
        $this->client->request('POST', '/admin/portfolios/' . $portfolio->getId() . '/update', [
            'title' => $newTitle,
            'content' => $content,
            'description' => $description,
            'status' => $status ? '1' : null,
            'is_featured' => $featured ? '1' : null,
        ]);

        $this->assertResponseRedirects('/admin/portfolios/');

        $this->em->clear();
        $updated = $this->em->getRepository(Portfolio::class)->find($portfolio->getId());
        $this->assertNotNull($updated);
        $this->assertSame($newTitle, $updated->getTitle());

        $this->client->request('POST', '/admin/portfolios/' . $portfolio->getId() . '/delete');
        $this->assertResponseRedirects('/admin/portfolios/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Portfolio::class)->find($portfolio->getId());
        $this->assertNull($deleted);
    }

    public function testPortfolioIndexView(): void
    {
        $user = $this->createAdminUser();
        $portfolio = new Portfolio();
        $portfolio->setTitle('Index Portfolio');
        $portfolio->setSlug('index-portfolio');
        $this->em->persist($portfolio);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/portfolios/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Index Portfolio', $this->client->getResponse()->getContent());
    }

    public function testPortfolioCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/portfolios/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_portfolios_store');
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testPortfolioEditView(): void
    {
        $user = $this->createAdminUser();
        $portfolio = new Portfolio();
        $portfolio->setTitle('Edit Portfolio');
        $portfolio->setSlug('edit-portfolio');
        $this->em->persist($portfolio);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/portfolios/' . $portfolio->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_portfolios_update', ['id' => $portfolio->getId()]
        );
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testPortfolioValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/admin/portfolios/store', [
            'title' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('title', $this->client->getResponse()->getContent());
    }
}
