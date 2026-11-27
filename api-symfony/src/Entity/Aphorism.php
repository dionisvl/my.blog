<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'aphorism')]
class Aphorism
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'detail_text_ru', type: Types::STRING, length: 255)]
    private string $detailTextRu;

    #[ORM\Column(name: 'detail_text_en', type: Types::TEXT, nullable: true)]
    private ?string $detailTextEn = null;

    #[ORM\Column(name: 'source_en', type: Types::TEXT, nullable: true)]
    private ?string $sourceEn = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDetailTextRu(): string
    {
        return $this->detailTextRu;
    }

    public function setDetailTextRu(string $detailTextRu): self
    {
        $this->detailTextRu = $detailTextRu;

        return $this;
    }

    public function getDetailTextEn(): ?string
    {
        return $this->detailTextEn;
    }

    public function setDetailTextEn(?string $detailTextEn): self
    {
        $this->detailTextEn = $detailTextEn;

        return $this;
    }

    public function getSourceEn(): ?string
    {
        return $this->sourceEn;
    }

    public function setSourceEn(?string $sourceEn): self
    {
        $this->sourceEn = $sourceEn;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
