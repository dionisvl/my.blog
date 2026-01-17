<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminFrontPartPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        public ?string $slug = null,
        #[SerializedName('category_name')]
        public ?string $categoryName = null,
        public ?string $type = null,
        #[SerializedName('preview_text')]
        public ?string $previewText = null,
        #[SerializedName('detail_text')]
        public ?string $detailText = null,
        public string|bool $status = false,
        public ?string $url = null,
    ) {
    }
}
