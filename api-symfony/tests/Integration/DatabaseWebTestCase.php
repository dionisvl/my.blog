<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->resetDatabase();
    }

    protected function resetDatabase(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function createAdminUser(): User
    {
        $user = new User();
        $this->setPrivate($user, 'name', 'Admin');
        $this->setPrivate($user, 'email', 'admin@example.com');
        $this->setPrivate($user, 'password', 'hashed');
        $this->setPrivate($user, 'isAdmin', true);
        $this->setPrivate($user, 'status', 1);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function setPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
