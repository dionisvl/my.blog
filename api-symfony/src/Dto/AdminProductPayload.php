<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminProductPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[SerializedName('detail_text')]
        #[Assert\NotBlank]
        public ?string $detailText = null,
        #[SerializedName('preview_text')]
        public ?string $previewText = null,
        #[Assert\PositiveOrZero]
        public ?int $price = null,
        #[Assert\PositiveOrZero]
        public ?int $balance = null,
        public ?string $composition = null,
        public ?string $features = null,
        public ?string $size = null,
        public ?string $manufacturer = null,
        public ?string $delivery = null,
        #[Assert\Positive]
        #[SerializedName('category_id')]
        public ?int $categoryId = null,
        #[Assert\Date]
        public ?string $date = null,
        public ?float $stars = null,
        public ?UploadedFile $previewPicture = null,
        public ?UploadedFile $detailPicture = null,
    ) {
    }
}
