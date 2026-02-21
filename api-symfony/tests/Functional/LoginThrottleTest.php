<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginThrottleTest extends DatabaseWebTestCase
{
    public function testLoginFormLoads(): void
    {
        $this->client->request(Request::METHOD_GET, '/login');

        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Login');
    }

    public function testLoginThrottleAfterMaxAttempts(): void
    {
        $email = 'throttle-test-' . uniqid() . '@example.com';

        // Make exactly 5 failed attempts
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request(Request::METHOD_POST, '/login', [
                '_username' => $email,
                '_password' => 'wrong_password_' . $i,
                '_csrf_token' => $this->getCsrfToken($this->client),
            ]);

            // Each attempt should redirect back to login
            $this->assertResponseRedirects('/login');
        }

        $this->client->request(Request::METHOD_POST, '/login', [
            '_username' => $email,
            '_password' => 'wrong_password_6',
            '_csrf_token' => $this->getCsrfToken($this->client),
        ]);

        $this->assertResponseRedirects('/login');

        $session = $this->client->getRequest()->getSession();
        $error = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        self::assertInstanceOf(TooManyLoginAttemptsAuthenticationException::class, $error);
    }

    public function testMultipleUsersHaveIndependentLimits(): void
    {
        // User 1 makes some attempts
        $user1 = 'user1-' . uniqid() . '@example.com';
        $this->client->request(Request::METHOD_POST, '/login', [
            '_username' => $user1,
            '_password' => 'wrong',
            '_csrf_token' => $this->getCsrfToken($this->client),
        ]);
        $this->assertResponseRedirects('/login');

        // User 2 should not be affected by user 1's attempts
        $user2 = 'user2-' . uniqid() . '@example.com';
        $this->client->request(Request::METHOD_POST, '/login', [
            '_username' => $user2,
            '_password' => 'wrong',
            '_csrf_token' => $this->getCsrfToken($this->client),
        ]);
        $this->assertResponseRedirects('/login');
    }

    private function getCsrfToken(KernelBrowser $client): string
    {
        $client->request(Request::METHOD_GET, '/login');
        $crawler = $client->getCrawler();

        return $crawler->filter('input[name="_csrf_token"]')->attr('value') ?? '';
    }
}
