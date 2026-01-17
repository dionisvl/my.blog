<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['title'], entityClass: Category::class, errorPath: 'title', identifierFieldNames: ['id'], message: 'This title is already in use.')]
final class AdminCategoryPayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $id = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[SerializedName('detail_text')]
        public ?string $detailText = null,
        #[SerializedName('preview_text')]
        public ?string $previewText = null,
    ) {
    }
}
