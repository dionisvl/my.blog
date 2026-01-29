<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminUserControllerTest extends DatabaseWebTestCase
{
    public static function provideUserUpdates(): iterable
    {
        yield 'no password change' => ['New Name', 'new@example.com', '', '1', '1'];

        yield 'with password change' => ['New Name 2', 'new2@example.com', 'secret123', '0', '0'];
    }

    #[DataProvider('provideUserUpdates')]
    public function testEditUser(string $name, string $email, string $password, string $isAdmin, string $status): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Old Name', 'old@example.com', 'oldhash');

        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_POST, '/admin/users/' . $user->getId() . '/update', [
            'id' => $user->getId(),
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'isAdmin' => $isAdmin,
            'status' => $status,
        ]);

        $this->assertResponseRedirects('/admin/users/');

        $this->em->clear();
        $updated = $this->em->getRepository(User::class)->find($user->getId());

        self::assertNotNull($updated);
        self::assertSame($name, $updated->getName());
        self::assertSame($email, $updated->getEmail());

        if ('' === $password) {
            self::assertSame('oldhash', $updated->getPassword());
        } else {
            self::assertNotSame('oldhash', $updated->getPassword());
        }

        self::assertSame(filter_var($isAdmin, \FILTER_VALIDATE_BOOLEAN), $updated->isAdmin());
        self::assertSame(filter_var($status, \FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $updated->getStatus());
    }

    private function createUser(string $name, string $email, string $password): User
    {
        $user = new User();
        $this->setPrivate($user, 'name', $name);
        $this->setPrivate($user, 'email', $email);
        $this->setPrivate($user, 'password', $password);
        $this->setPrivate($user, 'isAdmin', false);
        $this->setPrivate($user, 'status', 1);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testUserIndexView(): void
    {
        $admin = $this->createAdminUser();
        $this->createUser('Viewer', 'viewer@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/users/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('viewer@example.com', $this->client->getResponse()->getContent());
    }

    public function testUserEditView(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Edit User', 'edit@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/users/' . $user->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_users_update', ['id' => $user->getId()]);
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testUserDelete(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Delete User', 'delete@example.com', 'hash');
        $userId = $user->getId();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_POST, '/admin/users/' . $userId . '/delete');

        $this->assertResponseRedirects('/admin/users/');

        $this->em->clear();
        $deleted = $this->em->getRepository(User::class)->find($userId);
        self::assertNull($deleted);
    }

    public function testUserValidationErrorsReturnJson(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Invalid User', 'invalid@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_POST, '/admin/users/' . $user->getId() . '/update', [
            'id' => $user->getId(),
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('email', $this->client->getResponse()->getContent());
    }

    public function testUserEmailMustBeUnique(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('First', 'first@example.com', 'hash');
        $this->createUser('Second', 'second@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_POST, '/admin/users/' . $user->getId() . '/update', [
            'id' => $user->getId(),
            'name' => 'First',
            'email' => 'second@example.com',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertStringContainsString('email', $this->client->getResponse()->getContent());
    }

    public function testCreateUser(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_POST, '/admin/users/store', [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'secret123',
            'isAdmin' => '1',
            'status' => '1',
        ]);

        $this->assertResponseRedirects('/admin/users/');

        $this->em->clear();
        $created = $this->em->getRepository(User::class)->findOneBy(['email' => 'created@example.com']);
        self::assertNotNull($created);
        self::assertSame('Created User', $created->getName());
        self::assertTrue($created->isAdmin());
        self::assertSame(1, $created->getStatus());
    }

    public function testCreateUserRequiresPassword(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_POST, '/admin/users/store', [
            'name' => 'No Pass',
            'email' => 'nopass@example.com',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('password', $this->client->getResponse()->getContent());
    }
}
