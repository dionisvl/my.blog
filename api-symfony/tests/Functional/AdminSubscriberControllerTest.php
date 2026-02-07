<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Subscription;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminSubscriberControllerTest extends DatabaseWebTestCase
{
    public static function provideSubscriberEmails(): iterable
    {
        yield 'simple email' => ['alpha@example.com'];

        yield 'long email' => ['team+news@example.com'];
    }

    #[DataProvider('provideSubscriberEmails')]
    public function testCreateDeleteSubscriber(string $email): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/subscribers/store', [
            'email' => $email,
        ]);

        $this->assertResponseRedirects('/admin/subscribers/');

        $this->em->clear();
        $subscriber = $this->em->getRepository(Subscription::class)->findOneBy(['email' => $email]);
        self::assertNotNull($subscriber);

        $this->client->request(Request::METHOD_POST, '/admin/subscribers/' . $subscriber->getId() . '/delete');
        $this->assertResponseRedirects('/admin/subscribers/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Subscription::class)->find($subscriber->getId());
        self::assertNull($deleted);
    }

    public function testSubscriberIndexView(): void
    {
        $user = $this->createAdminUser();
        $subscription = new Subscription();
        $subscription->setEmail('list@example.com');

        $this->em->persist($subscription);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/subscribers/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('list@example.com', $this->client->getResponse()->getContent());
    }

    public function testSubscriberCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/subscribers/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_subscribers_store');
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testSubscriberValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/subscribers/store', [
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('email', $this->client->getResponse()->getContent());
    }

    public function testSubscriberEmailIsUnique(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/subscribers/store', [
            'email' => 'unique@example.com',
        ]);
        $this->assertResponseRedirects('/admin/subscribers/');

        $this->client->request(Request::METHOD_POST, '/admin/subscribers/store', [
            'email' => 'unique@example.com',
        ]);
        $this->assertResponseStatusCodeSame(422);
        self::assertStringContainsString('email', $this->client->getResponse()->getContent());
    }
}
