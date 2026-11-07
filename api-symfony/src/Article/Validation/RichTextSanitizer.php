<?php

declare(strict_types=1);

namespace App\Article\Validation;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Allow-list HTML sanitizer for richText and FAQ answers.
 *
 * The input is parsed with the HTML5 parser and the output is rebuilt from scratch, so only
 * allow-listed tags and attributes can ever appear. sanitize() is idempotent: stored documents are
 * valid only if sanitize($html) === $html (see ArticleDocumentValidator).
 */
final readonly class RichTextSanitizer
{
    private const int MAX_INPUT_BYTES = 200000;

    private const int MAX_DEPTH = 12;

    private const int MAX_PASSES = 3;

    /** @var list<string> */
    private const array ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a',
        'h2', 'h3', 'h4', 'blockquote', 'code', 'pre',
    ];

    /** @var list<string> */
    private const array VOID_TAGS = ['br'];

    /** @var list<string> elements removed together with their content */
    private const array DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noscript',
        'template', 'svg', 'math', 'head', 'title', 'link', 'meta', 'base', 'form', 'input',
        'button', 'select', 'textarea', 'audio', 'video', 'canvas', 'dialog',
    ];

    /** @var list<string> kept link relations, in canonical output order (noopener is always emitted) */
    private const array EXTRA_REL_TOKENS = ['noreferrer', 'nofollow', 'sponsored'];

    public function __construct(private UrlPolicy $urlPolicy)
    {
    }

    public function sanitize(string $html): string
    {
        $current = $html;

        for ($pass = 0; $pass < self::MAX_PASSES; ++$pass) {
            $next = $this->sanitizeOnce($current);

            if ($next === $current) {
                return $next;
            }

            $current = $next;
        }

        return $current;
    }

    private function sanitizeOnce(string $html): string
    {
        if ('' === trim($html) || \strlen($html) > self::MAX_INPUT_BYTES) {
            return '';
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>'.$html.'</body></html>',
            \LIBXML_NOERROR,
            'UTF-8',
        );

        $body = $document->body;

        if (null === $body) {
            return '';
        }

        return trim($this->renderChildren($body, 0));
    }

    private function renderChildren(Node $parent, int $depth): string
    {
        $out = '';

        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $out .= self::escape($node->data);

                continue;
            }

            if ($node instanceof Element) {
                $out .= $this->renderElement($node, $depth);
            }
        }

        return $out;
    }

    private function renderElement(Element $element, int $depth): string
    {
        $tag = strtolower($element->localName);

        if (\in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return '';
        }

        if ($depth >= self::MAX_DEPTH) {
            return self::escape($element->textContent);
        }

        $inner = $this->renderChildren($element, $depth + 1);

        if ('a' === $tag) {
            return $this->renderLink($element, $inner);
        }

        if (!\in_array($tag, self::ALLOWED_TAGS, true)) {
            return $inner;
        }

        if (\in_array($tag, self::VOID_TAGS, true)) {
            return '<'.$tag.'>';
        }

        return '<'.$tag.'>'.$inner.'</'.$tag.'>';
    }

    private function renderLink(Element $element, string $inner): string
    {
        $href = trim((string) $element->getAttribute('href'));

        if ('' === $href || null !== $this->urlPolicy->check($href)) {
            return $inner;
        }

        $given = preg_split('/\s+/', strtolower((string) $element->getAttribute('rel')), -1, \PREG_SPLIT_NO_EMPTY);
        $given = false === $given ? [] : $given;

        $rel = ['noopener'];

        foreach (self::EXTRA_REL_TOKENS as $token) {
            if (\in_array($token, $given, true)) {
                $rel[] = $token;
            }
        }

        $attributes = ' href="'.self::escape($href).'" rel="'.implode(' ', $rel).'"';

        if ('_blank' === strtolower(trim((string) $element->getAttribute('target')))) {
            $attributes .= ' target="_blank"';
        }

        return '<a'.$attributes.'>'.$inner.'</a>';
    }

    private static function escape(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return htmlspecialchars($clean ?? '', \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
