<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PortfolioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioRepository::class)]
#[ORM\Table(name: 'portfolios')]
class Portfolio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'category_id', type: Types::INTEGER, nullable: true)]
    private ?int $categoryId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $author = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $status = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(name: 'is_featured', type: Types::INTEGER, options: ['default' => 0])]
    private int $isFeatured = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getImagePath(): string
    {
        if ($this->image === null) {
            return '/storage/blog_images/no-image.png';
        }

        return '/storage/uploads/portfolio/' . $this->image;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(?int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;
        return $this;
    }

    public function getIsFeatured(): int
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(int $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function uploadImage(\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile, string $uploadDir): void
    {
        $timestamp = (new \DateTime())->format('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(2));
        $extension = $imageFile->guessExtension() ?? 'png';
        $filename = $timestamp . '_' . $randomString . '.' . $extension;

        $imageFile->move($uploadDir, $filename);
        $this->image = $filename;
    }

    public function removeImage(string $uploadDir): void
    {
        if ($this->image === null) {
            return;
        }

        $filepath = $uploadDir . '/' . $this->image;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
