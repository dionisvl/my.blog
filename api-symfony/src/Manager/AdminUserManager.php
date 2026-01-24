<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminUserPayload;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AdminUserManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function update(User $user, AdminUserPayload $payload): User
    {
        $user->setName($payload->name);
        $user->setEmail($payload->email);
        $user->setIsAdmin($this->normalizeBool($payload->isAdmin));
        $user->setStatus($this->normalizeBool($payload->status) ? 1 : 0);

        if (null !== $payload->password && '' !== $payload->password) {
            $hash = $this->passwordHasher->hashPassword($user, $payload->password);
            $user->setPassword($hash);
        }

        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $user;
    }

    private function normalizeBool(string|bool $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }

    public function create(AdminUserPayload $payload): User
    {
        $user = new User();
        $user->setName($payload->name);
        $user->setEmail($payload->email);
        $user->setIsAdmin($this->normalizeBool($payload->isAdmin));
        $user->setStatus($this->normalizeBool($payload->status) ? 1 : 0);

        $hash = $this->passwordHasher->hashPassword($user, (string)$payload->password);
        $user->setPassword($hash);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
