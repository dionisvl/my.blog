<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One locale of a modular article (or a classified legacy translation): localized metadata, search
 * text and the typed block document. `published_at IS NULL` means draft; Go reads published rows
 * only, so unpublished content is unreachable through the public API.
 */
#[ORM\Entity]
#[ORM\Table(name: 'post_locales')]
class PostLocale
{
    #[ORM\Id]
    #[ORM\Column(name: 'post_id', type: Types::INTEGER)]
    private int $postId;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $locale;

    #[ORM\Column(name: 'schema_version', type: Types::SMALLINT)]
    private int $schemaVersion;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $summary;

    #[ORM\Column(name: 'meta_title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metaTitle;

    #[ORM\Column(name: 'meta_description', type: Types::STRING, length: 500, nullable: true)]
    private ?string $metaDescription;

    #[ORM\Column(name: 'search_text', type: Types::TEXT)]
    private string $searchText;

    /** @var array<mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $document;

    #[ORM\Column(name: 'published_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'created_by', type: Types::INTEGER, nullable: true)]
    private ?int $createdBy;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<mixed> $document
     */
    public function __construct(
        int $postId,
        string $locale,
        string $title,
        string $summary,
        ?string $metaTitle,
        ?string $metaDescription,
        string $searchText,
        array $document,
        int $schemaVersion,
        ?int $createdBy,
    ) {
        $this->postId = $postId;
        $this->locale = $locale;
        $this->title = $title;
        $this->summary = $summary;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->searchText = $searchText;
        $this->document = $document;
        $this->schemaVersion = $schemaVersion;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * Replaces the content of this locale. Publication state is untouched.
     *
     * @param array<mixed> $document
     */
    public function replaceContent(
        string $title,
        string $summary,
        ?string $metaTitle,
        ?string $metaDescription,
        string $searchText,
        array $document,
        int $schemaVersion,
    ): void {
        $this->title = $title;
        $this->summary = $summary;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->searchText = $searchText;
        $this->document = $document;
        $this->schemaVersion = $schemaVersion;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getSearchText(): string
    {
        return $this->searchText;
    }

    /**
     * @return array<mixed>
     */
    public function getDocument(): array
    {
        return $this->document;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function publish(\DateTimeImmutable $at): void
    {
        $this->publishedAt = $at;
        $this->updatedAt = $at;
    }

    public function unpublish(): void
    {
        $this->publishedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
