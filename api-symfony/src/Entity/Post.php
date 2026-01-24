<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'posts')]
class Post
{
    public const int STATUS_PUBLISHED = 0;
    public const int STATUS_DRAFT = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $status = false;

    #[ORM\Column(name: 'is_featured', type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $isFeatured = false;

    #[ORM\Column(name: 'views_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $viewsCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private User $author;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?Category $category = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'post_tags')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: PostLike::class, mappedBy: 'post')]
    private Collection $likes;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post')]
    private Collection $comments;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->comments = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): string
    {
        if (null === $this->image) {
            return '/storage/blog_images/no-image.png';
        }

        return '/storage/uploads/' . $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function incrementViewsCount(): void
    {
        ++$this->viewsCount;
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

    public function getFormattedDate(): string
    {
        return $this->createdAt->format('F d, Y');
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

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

    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param iterable<Tag> $tags
     */
    public function setTags(iterable $tags): self
    {
        foreach ($this->tags as $existing) {
            $this->tags->removeElement($existing);
        }

        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }

        return $this;
    }

    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getComments(): Collection
    {
        return $this->comments->filter(static fn($comment) => 1 === $comment->getStatus());
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function uploadImage($imageFile, string $uploadDir): void
    {
        if (null === $imageFile) {
            return;
        }

        $this->removeImage($uploadDir);

        $timestamp = (new \DateTime())->format('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(2));
        $extension = $imageFile->guessExtension() ?? 'png';
        $filename = $timestamp . '_' . $randomString . '.' . $extension;

        $imageFile->move($uploadDir, $filename);
        $this->image = $filename;
    }

    public function removeImage(string $uploadDir): void
    {
        if (null !== $this->image) {
            $filepath = $uploadDir . '/' . $this->image;

            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}
