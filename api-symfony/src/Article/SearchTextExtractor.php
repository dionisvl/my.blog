<?php

declare(strict_types=1);

namespace App\Article;

/**
 * Flattens the human-readable text of a (sanitized) article document into one plain-text string
 * stored on the locale row, so the Go search can match block content without parsing JSON.
 */
final readonly class SearchTextExtractor
{
    public const int MAX_LENGTH = 100000;

    /** @var list<string> keys whose string values are markup, not prose */
    private const array HTML_KEYS = ['html', 'answerHtml'];

    /** @var list<string> keys that never carry reader-visible text */
    private const array SKIPPED_KEYS = [
        'css', 'js', 'id', 'type', 'url', 'learnMoreUrl', 'variant', 'rating', 'sponsored', 'sticky',
        'columnId', 'targetBlockId', 'defaultOptionId', 'schema_version', 'locale', 'verified_at', 'accessed_at',
    ];

    /**
     * @param array<string, mixed> $document
     */
    public function extract(array $document): string
    {
        $parts = [];
        $this->collect($document['blocks'] ?? [], $parts);

        $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));

        return mb_substr($text, 0, self::MAX_LENGTH);
    }

    /**
     * @param list<string> $parts
     */
    private function collect(mixed $value, array &$parts): void
    {
        if (!\is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (\is_string($key) && \in_array($key, self::SKIPPED_KEYS, true)) {
                continue;
            }

            if (\is_string($item)) {
                $parts[] = \is_string($key) && \in_array($key, self::HTML_KEYS, true) ? self::htmlToText($item) : $item;

                continue;
            }

            $this->collect($item, $parts);
        }
    }

    /**
     * Valid only for already-sanitized HTML: every literal "<" in text is stored as "&lt;".
     */
    private static function htmlToText(string $html): string
    {
        // Block-level tags separate words; inline tags (strong, em, a, ...) must not split them.
        $spaced = (string) preg_replace('~</?(?:p|br|ul|ol|li|h[2-4]|blockquote|pre)>~', ' ', $html);

        return html_entity_decode(strip_tags($spaced), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
