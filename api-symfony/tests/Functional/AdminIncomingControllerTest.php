<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Incoming;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminIncomingControllerTest extends DatabaseWebTestCase
{
    public static function provideIncomingStatuses(): iterable
    {
        yield 'unread' => [0];

        yield 'read' => [1];
    }

    #[DataProvider('provideIncomingStatuses')]
    public function testToggleAndDeleteIncoming(int $status): void
    {
        $user = $this->createAdminUser();
        $incoming = new Incoming();
        $incoming->setName('Alice');
        $incoming->setEmail('alice@example.com');
        $incoming->setPhone('123');
        $incoming->setMessage('Hello');
        $incoming->setStatus($status);

        $this->em->persist($incoming);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/incomings/' . $incoming->getId() . '/toggle');

        $this->assertResponseRedirects('/admin/incomings/');

        $this->em->clear();
        $updated = $this->em->getRepository(Incoming::class)->find($incoming->getId());
        self::assertNotNull($updated);
        self::assertSame(1 === $status ? 0 : 1, $updated->getStatus());

        $this->client->request(Request::METHOD_POST, '/admin/incomings/' . $incoming->getId() . '/delete');
        $this->assertResponseRedirects('/admin/incomings/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Incoming::class)->find($incoming->getId());
        self::assertNull($deleted);
    }

    public function testIncomingsIndexView(): void
    {
        $user = $this->createAdminUser();
        $incoming = new Incoming();
        $incoming->setName('Bob');
        $incoming->setMessage('Need help');

        $this->em->persist($incoming);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/incomings/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Need help', $this->client->getResponse()->getContent());
    }
}
