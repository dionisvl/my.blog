<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comments')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'author_name', type: Types::STRING, length: 255, options: ['default' => 'anon'])]
    private string $authorName = 'anon';

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id')]
    private Post $post;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private ?User $author = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $status = 0;

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

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): self
    {
        $this->authorName = $authorName;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function setPost(Post $post): self
    {
        $this->post = $post;
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

    public function toggleStatus(): void
    {
        if ($this->status == 1) {
            $this->disallow();
            return;
        }

        $this->allow();
    }

    public function disallow(): void
    {
        $this->status = 0;
    }

    public function allow(): void
    {
        $this->status = 1;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getAuthorImage(): string
    {
        if ($this->author === null) {
            return '/storage/blog_images/no-image.png';
        }

        return $this->author->getImage();
    }

    public function getDiffForHumans(): string
    {
        $now = new \DateTime();
        $diff = $this->createdAt->diff($now);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }

        return 'just now';
    }
}
