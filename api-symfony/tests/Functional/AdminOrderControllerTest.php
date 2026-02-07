<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminOrderControllerTest extends DatabaseWebTestCase
{
    public static function provideOrderPayloads(): iterable
    {
        yield 'basic order' => ['Order One', 1200, 'Contents'];

        yield 'another order' => ['Order Two', 500, 'Another content'];
    }

    #[DataProvider('provideOrderPayloads')]
    public function testCreateUpdateDeleteOrder(string $title, int $price, string $contents): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/orders/store', [
            'title' => $title,
            'price' => $price,
            'contents' => $contents,
            'status' => 0,
        ]);

        $this->assertResponseRedirects('/admin/orders/');

        $this->em->clear();
        $order = $this->em->getRepository(Order::class)->findOneBy(['title' => $title]);
        self::assertNotNull($order);
        self::assertSame($price, $order->getPrice());

        $newTitle = $title . ' Updated';
        $this->client->request(Request::METHOD_POST, '/admin/orders/' . $order->getId() . '/update', [
            'title' => $newTitle,
            'price' => $price,
            'contents' => $contents,
            'status' => 1,
        ]);

        $this->assertResponseRedirects('/admin/orders/');

        $this->em->clear();
        $updated = $this->em->getRepository(Order::class)->find($order->getId());
        self::assertNotNull($updated);
        self::assertSame($newTitle, $updated->getTitle());

        $this->client->request(Request::METHOD_POST, '/admin/orders/' . $order->getId() . '/delete');
        $this->assertResponseRedirects('/admin/orders/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Order::class)->find($order->getId());
        self::assertNull($deleted);
    }

    public function testOrderIndexView(): void
    {
        $user = $this->createAdminUser();
        $order = new Order();
        $order->setTitle('Index Order');
        $order->setSlug('index-order');

        $this->em->persist($order);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/orders/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Index Order', $this->client->getResponse()->getContent());
    }

    public function testOrderCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/orders/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_orders_store');
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testOrderEditView(): void
    {
        $user = $this->createAdminUser();
        $order = new Order();
        $order->setTitle('Edit Order');
        $order->setSlug('edit-order');

        $this->em->persist($order);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/orders/' . $order->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_orders_update', ['id' => $order->getId()]);
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testOrderValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/orders/store', [
            'title' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('title', $this->client->getResponse()->getContent());
    }
}
