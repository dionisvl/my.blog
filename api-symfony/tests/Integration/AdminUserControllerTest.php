<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $this->client->request('POST', '/admin/users/' . $user->getId() . '/update', [
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

        $this->assertNotNull($updated);
        $this->assertSame($name, $updated->getName());
        $this->assertSame($email, $updated->getEmail());

        if ($password === '') {
            $this->assertSame('oldhash', $updated->getPassword());
        } else {
            $this->assertNotSame('oldhash', $updated->getPassword());
        }

        $this->assertSame((bool)filter_var($isAdmin, FILTER_VALIDATE_BOOLEAN), $updated->isAdmin());
        $this->assertSame((bool)filter_var($status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $updated->getStatus());
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
        $this->client->request('GET', '/admin/users/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('viewer@example.com', $this->client->getResponse()->getContent());
    }

    public function testUserEditView(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Edit User', 'edit@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users/' . $user->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_users_update', ['id' => $user->getId()]);
        $this->assertSelectorExists(sprintf('form[action="%s"]', $action));
    }

    public function testUserDelete(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Delete User', 'delete@example.com', 'hash');
        $userId = $user->getId();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/admin/users/' . $userId . '/delete');

        $this->assertResponseRedirects('/admin/users/');

        $this->em->clear();
        $deleted = $this->em->getRepository(User::class)->find($userId);
        $this->assertNull($deleted);
    }

    public function testUserValidationErrorsReturnJson(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('Invalid User', 'invalid@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request('POST', '/admin/users/' . $user->getId() . '/update', [
            'id' => $user->getId(),
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('email', $this->client->getResponse()->getContent());
    }

    public function testUserEmailMustBeUnique(): void
    {
        $admin = $this->createAdminUser();
        $user = $this->createUser('First', 'first@example.com', 'hash');
        $this->createUser('Second', 'second@example.com', 'hash');

        $this->client->loginUser($admin);
        $this->client->request('POST', '/admin/users/' . $user->getId() . '/update', [
            'id' => $user->getId(),
            'name' => 'First',
            'email' => 'second@example.com',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('email', $this->client->getResponse()->getContent());
    }

    public function testCreateUser(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/admin/users/store', [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'secret123',
            'isAdmin' => '1',
            'status' => '1',
        ]);

        $this->assertResponseRedirects('/admin/users/');

        $this->em->clear();
        $created = $this->em->getRepository(User::class)->findOneBy(['email' => 'created@example.com']);
        $this->assertNotNull($created);
        $this->assertSame('Created User', $created->getName());
        $this->assertTrue($created->isAdmin());
        $this->assertSame(1, $created->getStatus());
    }

    public function testCreateUserRequiresPassword(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/admin/users/store', [
            'name' => 'No Pass',
            'email' => 'nopass@example.com',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJson($this->client->getResponse()->getContent());
        $this->assertStringContainsString('password', $this->client->getResponse()->getContent());
    }
}
