<?php

declare(strict_types=1);

namespace App\Article;

use App\Article\Validation\RichTextSanitizer;

/**
 * Sanitizes every HTML-carrying property of a draft document before it is validated and stored.
 * Everything else is left untouched; the validator decides whether the result is acceptable.
 */
final readonly class ArticleDocumentNormalizer
{
    public function __construct(private RichTextSanitizer $sanitizer)
    {
    }

    /**
     * @param array<mixed> $document
     *
     * @return array<mixed>
     */
    public function normalize(array $document): array
    {
        $blocks = $document['blocks'] ?? null;

        if (!\is_array($blocks) || !array_is_list($blocks)) {
            return $document;
        }

        foreach ($blocks as $index => $block) {
            if (!\is_array($block) || !\is_array($block['props'] ?? null)) {
                continue;
            }

            $props = $block['props'];

            // Keep an empty customCode props value JSON-object-shaped after associative decoding.
            if (ArticleContract::BLOCK_CUSTOM_CODE === ($block['type'] ?? null) && [] === $props) {
                $props['html'] = '';
            }

            if (ArticleContract::BLOCK_RICH_TEXT === ($block['type'] ?? null) && \is_string($props['html'] ?? null)) {
                $props['html'] = $this->sanitizer->sanitize($props['html']);
            }

            if (ArticleContract::BLOCK_FAQ === ($block['type'] ?? null) && \is_array($props['items'] ?? null) && array_is_list($props['items'])) {
                foreach ($props['items'] as $itemIndex => $item) {
                    if (\is_array($item) && \is_string($item['answerHtml'] ?? null)) {
                        $props['items'][$itemIndex]['answerHtml'] = $this->sanitizer->sanitize($item['answerHtml']);
                    }
                }
            }

            $blocks[$index]['props'] = $props;
        }

        $document['blocks'] = $blocks;

        return $document;
    }
}
