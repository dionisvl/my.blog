<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminOrderPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        public ?int $price = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $notes = null,
        public ?string $contents = null,
        #[SerializedName('contents_json')]
        public ?string $contentsJson = null,
        public ?string $manager = null,
        public ?int $status = null,
    ) {
    }
}
