<?php

declare(strict_types=1);

namespace App\Article;

/**
 * Editable content of one locale: card/SEO metadata plus the typed block document.
 */
final readonly class ArticleDraft
{
    /**
     * @param array<mixed> $document
     */
    public function __construct(
        public string $title,
        public string $summary,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public array $document,
    ) {
    }
}
