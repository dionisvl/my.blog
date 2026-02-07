<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use App\Service\FileNameGenerator;
use App\Service\FileValidator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $slug;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2500])]
    private int $price = 2500;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1000])]
    private int $balance = 1000;

    #[ORM\Column(name: 'detail_text', type: Types::TEXT, nullable: true)]
    private ?string $detailText = null;

    #[ORM\Column(name: 'preview_text', type: Types::TEXT, nullable: true)]
    private ?string $previewText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $composition = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $features = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $size = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'manufacturer_id', type: Types::INTEGER, nullable: true)]
    private ?int $manufacturerId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $delivery = null;

    #[ORM\Column(name: 'delivery_id', type: Types::INTEGER, nullable: true)]
    private ?int $deliveryId = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $author = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $status = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(name: 'is_featured', type: Types::INTEGER, options: ['default' => 0])]
    private int $isFeatured = 0;

    #[ORM\Column(name: 'views_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $viewsCount = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 100])]
    private float $stars = 100.0;

    #[ORM\Column(name: 'preview_picture', type: Types::STRING, length: 255, nullable: true)]
    private ?string $previewPicture = null;

    #[ORM\Column(name: 'detail_picture', type: Types::STRING, length: 255, nullable: true)]
    private ?string $detailPicture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

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

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): self
    {
        $this->balance = $balance;

        return $this;
    }

    public function getDetailText(): ?string
    {
        return $this->detailText;
    }

    public function setDetailText(?string $detailText): self
    {
        $this->detailText = $detailText;

        return $this;
    }

    public function getPreviewText(): ?string
    {
        return $this->previewText;
    }

    public function setPreviewText(?string $previewText): self
    {
        $this->previewText = $previewText;

        return $this;
    }

    public function getComposition(): ?string
    {
        return $this->composition;
    }

    public function setComposition(?string $composition): self
    {
        $this->composition = $composition;

        return $this;
    }

    public function getFeatures(): ?string
    {
        return $this->features;
    }

    public function setFeatures(?string $features): self
    {
        $this->features = $features;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getManufacturerId(): ?int
    {
        return $this->manufacturerId;
    }

    public function setManufacturerId(?int $manufacturerId): self
    {
        $this->manufacturerId = $manufacturerId;

        return $this;
    }

    public function getDelivery(): ?string
    {
        return $this->delivery;
    }

    public function setDelivery(?string $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getDeliveryId(): ?int
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(?int $deliveryId): self
    {
        $this->deliveryId = $deliveryId;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

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

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): self
    {
        $this->viewsCount = $viewsCount;

        return $this;
    }

    public function getStars(): float
    {
        return $this->stars;
    }

    public function setStars(float $stars): self
    {
        $this->stars = $stars;

        return $this;
    }

    public function getPreviewPicture(): ?string
    {
        return $this->previewPicture;
    }

    public function setPreviewPicture(?string $previewPicture): self
    {
        $this->previewPicture = $previewPicture;

        return $this;
    }

    public function getDetailPicture(): ?string
    {
        return $this->detailPicture;
    }

    public function setDetailPicture(?string $detailPicture): self
    {
        $this->detailPicture = $detailPicture;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

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

    public function getImage(string $type): string
    {
        $value = 'detail_picture' === $type ? $this->detailPicture : $this->previewPicture;

        if (null === $value) {
            return '/storage/shop_uploads/no-image.png';
        }

        return '/storage/shop_uploads/' . $value;
    }

    public function uploadImage(
        UploadedFile $imageFile,
        string $type,
        string $uploadDir,
        FileValidator $fileValidator,
        FileNameGenerator $fileNameGenerator,
    ): void {
        $fileValidator->validate($imageFile);

        $filename = $fileNameGenerator->generate($imageFile);
        $imageFile->move($uploadDir, $filename);

        if ('detail_picture' === $type) {
            $this->detailPicture = $filename;
        } else {
            $this->previewPicture = $filename;
        }
    }

    public function removeImage(string $type, string $uploadDir): void
    {
        $value = 'detail_picture' === $type ? $this->detailPicture : $this->previewPicture;

        if (null === $value) {
            return;
        }

        $filepath = $uploadDir . '/' . $value;

        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
