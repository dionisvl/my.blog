<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['email'], entityClass: User::class, errorPath: 'email', identifierFieldNames: ['id'], message: 'This email is already in use.')]
final class AdminUserPayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $id = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $email,
        public ?string $password = null,
        public string|bool $isAdmin = false,
        public string|bool $status = false,
    ) {
    }
}
