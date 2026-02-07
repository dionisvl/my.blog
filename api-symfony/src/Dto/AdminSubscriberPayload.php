<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Subscription;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['email'], message: 'This email is already subscribed.', entityClass: Subscription::class, errorPath: 'email', identifierFieldNames: ['id'])]
final class AdminSubscriberPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\Positive]
        public ?int $id = null,
    ) {
    }
}
