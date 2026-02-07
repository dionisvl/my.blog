<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminPortfolioPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        public ?string $content = null,
        public ?string $description = null,
        public string|bool $status = false,
        #[SerializedName('is_featured')]
        public string|bool $isFeatured = false,
        public ?UploadedFile $image = null,
    ) {
    }
}
