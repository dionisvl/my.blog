<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['title'], message: 'This title is already in use.', entityClass: Tag::class, errorPath: 'title', identifierFieldNames: ['id'])]
final class AdminTagPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[Assert\Positive]
        public ?int $id = null,
    ) {
    }
}
