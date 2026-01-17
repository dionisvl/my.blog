<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Subscription;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['email'], entityClass: Subscription::class, errorPath: 'email', identifierFieldNames: ['id'], message: 'This email is already subscribed.')]
final class AdminSubscriberPayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $id = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}
