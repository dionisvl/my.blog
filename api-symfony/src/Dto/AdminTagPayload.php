<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['title'], entityClass: Tag::class, errorPath: 'title', identifierFieldNames: ['id'], message: 'This title is already in use.')]
final class AdminTagPayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $id = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
    ) {
    }
}
