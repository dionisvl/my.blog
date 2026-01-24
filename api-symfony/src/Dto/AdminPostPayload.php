<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminPostPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        public ?string $content = null,
        public ?string $description = null,
        #[Assert\Date]
        public ?string $date = null,
        #[SerializedName('category_id')]
        #[Assert\Positive]
        public ?int $categoryId = null,
        public string|bool $status = false,
        #[SerializedName('is_featured')]
        public string|bool $isFeatured = false,
        #[SerializedName('tags')]
        public array $tags = [],
        public ?UploadedFile $image = null,
    ) {
    }
}
