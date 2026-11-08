<?php

declare(strict_types=1);

namespace App\Article\Validation;

use App\Article\ArticleContract;

/**
 * Internal validator for the modular article document (contracts/article-blocks/v1/schema.json).
 *
 * Two modes:
 *  - draft (forPublication = false): everything that makes stored content unsafe or unrenderable
 *    is rejected (unknown blocks/fields, wrong types, bad URLs, unsanitized HTML, sizes, duplicate ids,
 *    affiliate-link rules), but required properties may still be missing.
 *  - publication (forPublication = true): additionally requires completeness (required props,
 *    min counts, cross-references and cell counts). verified_at is optional; when supplied it
 *    must be a valid calendar date.
 */
final readonly class ArticleDocumentValidator
{
    private const string DATE_FORMAT = '!Y-m-d';

    private const string ID_PATTERN = '/^[a-z][a-z0-9_-]{0,39}$/D';

    private const string TEMPLATE_PATTERN = '/^[^{}]*(\{(name|cta)\}[^{}]*)*$/u';

    private const string CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    private const int MAX_HTML_LENGTH = 20000;

    private const int MAX_URL_LENGTH = 2048;

    public function __construct(
        private UrlPolicy $urlPolicy,
        private RichTextSanitizer $sanitizer,
    ) {
    }

    /**
     * @param array<mixed> $document
     *
     * @return list<ValidationIssue>
     */
    public function validate(array $document, bool $forPublication, ?string $expectedLocale = null): array
    {
        $run = new ValidationRun($forPublication);

        $encoded = json_encode($document, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        if (false === $encoded) {
            $run->add('', 'invalid_encoding', 'Document is not valid UTF-8 JSON data.');

            return $run->issues();
        }

        if (\strlen($encoded) > ArticleContract::MAX_DOCUMENT_BYTES) {
            $run->add('', 'document_too_large', \sprintf('Document exceeds %d bytes.', ArticleContract::MAX_DOCUMENT_BYTES));

            return $run->issues();
        }

        $this->validateTopLevel($document, $expectedLocale, $run);

        return $run->issues();
    }

    /**
     * @param array<mixed> $document
     */
    private function validateTopLevel(array $document, ?string $expectedLocale, ValidationRun $run): void
    {
        foreach (array_keys($document) as $key) {
            if (!\in_array($key, ['schema_version', 'locale', 'verified_at', 'blocks'], true)) {
                $run->add('/'.$key, 'unknown_field', 'Unknown top-level property.');
            }
        }

        $this->validateSchemaVersion($document, $run);
        $this->validateLocale($document, $expectedLocale, $run);

        if (\array_key_exists('verified_at', $document)) {
            $verifiedAt = $document['verified_at'];

            if (!\is_string($verifiedAt)) {
                $run->add('/verified_at', 'invalid_type', 'verified_at must be a YYYY-MM-DD string.');
            } elseif (!self::isValidDate($verifiedAt)) {
                $run->add('/verified_at', 'invalid_date', 'verified_at must be a real calendar date (YYYY-MM-DD).');
            }
        }

        if (!\array_key_exists('blocks', $document)) {
            $run->add('/blocks', 'missing_field', 'blocks is required.');

            return;
        }

        $blocks = $document['blocks'];

        if (!\is_array($blocks) || !array_is_list($blocks)) {
            $run->add('/blocks', 'invalid_type', 'blocks must be a list.');

            return;
        }

        if ($run->strict && [] === $blocks) {
            $run->add('/blocks', 'no_blocks', 'A published article needs at least one block.');
        }

        if (\count($blocks) > ArticleContract::MAX_BLOCKS) {
            $run->add('/blocks', 'too_many_items', \sprintf('At most %d blocks are allowed.', ArticleContract::MAX_BLOCKS));

            return;
        }

        $seenIds = [];

        foreach ($blocks as $index => $block) {
            $this->validateBlock($block, $index, $seenIds, $run);
        }

        if ($run->strict) {
            $this->validateSelectorReferences($run);

        }
    }

    /**
     * @param array<mixed> $document
     */
    private function validateSchemaVersion(array $document, ValidationRun $run): void
    {
        if (!\array_key_exists('schema_version', $document)) {
            $run->add('/schema_version', 'missing_field', 'schema_version is required.');
        } elseif (!\is_int($document['schema_version'])) {
            $run->add('/schema_version', 'invalid_type', 'schema_version must be an integer.');
        } elseif (ArticleContract::SCHEMA_VERSION !== $document['schema_version']) {
            $run->add('/schema_version', 'unsupported_schema_version', \sprintf('Only schema_version %d is supported.', ArticleContract::SCHEMA_VERSION));
        }
    }

    /**
     * @param array<mixed> $document
     */
    private function validateLocale(array $document, ?string $expectedLocale, ValidationRun $run): void
    {
        if (!\array_key_exists('locale', $document)) {
            $run->add('/locale', 'missing_field', 'locale is required.');
        } elseif (!\is_string($document['locale'])) {
            $run->add('/locale', 'invalid_type', 'locale must be a string.');
        } elseif (!\in_array($document['locale'], ArticleContract::LOCALES, true)) {
            $run->add('/locale', 'unsupported_locale', 'locale must be one of: '.implode(', ', ArticleContract::LOCALES).'.');
        } elseif (null !== $expectedLocale && $document['locale'] !== $expectedLocale) {
            $run->add('/locale', 'locale_mismatch', \sprintf('Document locale "%s" does not match the locale "%s".', $document['locale'], $expectedLocale));
        }
    }

    /**
     * @param array<string, true> $seenIds
     */
    private function validateBlock(mixed $block, int $index, array &$seenIds, ValidationRun $run): void
    {
        $path = '/blocks/'.$index;

        if (!self::isObject($block)) {
            $run->add($path, 'invalid_type', 'A block must be an object.');

            return;
        }

        foreach (array_keys($block) as $key) {
            if (!\in_array($key, ['id', 'type', 'props'], true)) {
                $run->add($path.'/'.$key, 'unknown_field', 'Unknown block property.');
            }
        }

        $id = $this->validateBlockId($block, $path, $seenIds, $run);
        $type = $this->validateBlockType($block, $path, $run);
        $props = $block['props'] ?? null;

        if (!\array_key_exists('props', $block)) {
            $run->add($path.'/props', 'missing_field', 'props is required.');
        } elseif (!self::isObject($props)) {
            $run->add($path.'/props', 'invalid_type', 'props must be an object.');
        }

        if (null === $type || !self::isObject($props)) {
            return;
        }

        $run->currentBlock = $index;
        $run->currentBlockType = $type;

        if (null !== $id) {
            $run->blockTypes[$id] = $type;
        }

        $this->validateObject($props, self::blockSpec($type), $path.'/props', $run);

        if (ArticleContract::BLOCK_COMPARISON_TABLE === $type) {
            $this->validateComparisonTable($props, $id, $path.'/props', $run);
        }

        if (ArticleContract::BLOCK_CARD_SELECTOR === $type) {
            $run->selectors[] = [$index, $props];
        }
    }

    /**
     * @param array<mixed>        $block
     * @param array<string, true> $seenIds
     */
    private function validateBlockId(array $block, string $path, array &$seenIds, ValidationRun $run): ?string
    {
        if (!\array_key_exists('id', $block)) {
            $run->add($path.'/id', 'missing_field', 'id is required.');

            return null;
        }

        $id = $block['id'];

        if (!\is_string($id) || 1 !== preg_match(self::ID_PATTERN, $id)) {
            $run->add($path.'/id', 'invalid_pattern', 'Block id must match '.self::ID_PATTERN.'.');

            return null;
        }

        if (isset($seenIds[$id])) {
            $run->add($path.'/id', 'duplicate_id', \sprintf('Block id "%s" is used more than once.', $id));
        }

        $seenIds[$id] = true;

        return $id;
    }

    /**
     * @param array<mixed> $block
     */
    private function validateBlockType(array $block, string $path, ValidationRun $run): ?string
    {
        if (!\array_key_exists('type', $block)) {
            $run->add($path.'/type', 'missing_field', 'type is required.');

            return null;
        }

        $type = $block['type'];

        if (!\is_string($type)) {
            $run->add($path.'/type', 'invalid_type', 'type must be a string.');

            return null;
        }

        if (!\in_array($type, ArticleContract::BLOCK_TYPES, true)) {
            $run->add($path.'/type', 'unknown_block_type', \sprintf('Unknown block type "%s".', $type));

            return null;
        }

        return $type;
    }

    /**
     * @param array<mixed> $props
     */
    private function validateComparisonTable(array $props, ?string $id, string $path, ValidationRun $run): void
    {
        $columnIds = [];
        $columns = $props['columns'] ?? null;

        if (\is_array($columns) && array_is_list($columns)) {
            foreach ($columns as $column) {
                if (self::isObject($column) && \is_string($column['id'] ?? null)) {
                    $columnIds[] = $column['id'];
                }
            }
        }

        if (null !== $id) {
            $run->tableColumns[$id] = $columnIds;
        }

        if (!$run->strict) {
            return;
        }

        $rows = $props['rows'] ?? null;

        if (\is_array($rows) && array_is_list($rows)) {
            foreach ($rows as $rowIndex => $row) {
                $cells = self::isObject($row) ? ($row['cells'] ?? null) : null;

                if (\is_array($cells) && array_is_list($cells) && \count($cells) !== \count($columnIds)) {
                    $run->add(
                        $path.'/rows/'.$rowIndex.'/cells',
                        'cell_count_mismatch',
                        \sprintf('Expected %d cells (one per column), got %d.', \count($columnIds), \count($cells)),
                    );
                }
            }
        }

        $items = self::isObject($props['ctaRow'] ?? null) ? ($props['ctaRow']['items'] ?? null) : null;

        if (\is_array($items) && array_is_list($items)) {
            foreach ($items as $itemIndex => $item) {
                $columnId = self::isObject($item) ? ($item['columnId'] ?? null) : null;

                if (\is_string($columnId) && !\in_array($columnId, $columnIds, true)) {
                    $run->add($path.'/ctaRow/items/'.$itemIndex.'/columnId', 'bad_reference', \sprintf('Unknown column "%s".', $columnId));
                }
            }
        }
    }

    private function validateSelectorReferences(ValidationRun $run): void
    {
        foreach ($run->selectors as [$index, $props]) {
            $base = '/blocks/'.$index.'/props';
            $target = $props['targetBlockId'] ?? null;
            $columns = null;

            if (\is_string($target)) {
                if (ArticleContract::BLOCK_COMPARISON_TABLE === ($run->blockTypes[$target] ?? null)) {
                    $columns = $run->tableColumns[$target] ?? [];
                } else {
                    $run->add($base.'/targetBlockId', 'bad_reference', \sprintf('"%s" is not a comparisonTable block of this document.', $target));
                }
            }

            $optionIds = [];
            $options = $props['options'] ?? null;

            if (\is_array($options) && array_is_list($options)) {
                foreach ($options as $optionIndex => $option) {
                    if (!self::isObject($option)) {
                        continue;
                    }

                    if (\is_string($option['id'] ?? null)) {
                        $optionIds[] = $option['id'];
                    }

                    $columnId = $option['columnId'] ?? null;

                    if (null !== $columns && \is_string($columnId) && !\in_array($columnId, $columns, true)) {
                        $run->add($base.'/options/'.$optionIndex.'/columnId', 'bad_reference', \sprintf('Column "%s" does not exist in "%s".', $columnId, (string) $target));
                    }
                }
            }

            $default = $props['defaultOptionId'] ?? null;

            if (\is_string($default) && !\in_array($default, $optionIds, true)) {
                $run->add($base.'/defaultOptionId', 'bad_reference', \sprintf('Option "%s" does not exist.', $default));
            }
        }
    }


    /**
     * @param array<string, mixed> $spec
     */
    private function validateValue(mixed $value, array $spec, string $path, ValidationRun $run): void
    {
        switch ($spec['t']) {
            case 'text':
                $this->validateText($value, (int) $spec['max'], $path, $run);

                break;

            case 'template':
                if ($this->validateText($value, 200, $path, $run) && \is_string($value) && 1 !== preg_match(self::TEMPLATE_PATTERN, $value)) {
                    $run->add($path, 'bad_template', 'Only the {name} and {cta} placeholders are allowed.');
                }

                break;

            case 'html':
                $this->validateHtml($value, $path, $run);

                break;

            case 'raw':
                if (!\is_string($value)) {
                    $run->add($path, 'invalid_type', 'Must be a string.');
                } elseif (\strlen($value) > (int) $spec['max']) {
                    $run->add($path, 'too_long', \sprintf('At most %d bytes are allowed.', (int) $spec['max']));
                }

                break;

            case 'url':
                if (!\is_string($value)) {
                    $run->add($path, 'invalid_type', 'Expected a URL string.');
                } elseif ('' === $value) {
                    $run->add($path, UrlPolicy::CODE_INVALID, 'A URL cannot be empty.');
                } elseif (\strlen($value) > self::MAX_URL_LENGTH) {
                    $run->add($path, 'too_long', \sprintf('URL exceeds %d characters.', self::MAX_URL_LENGTH));
                }

                break;

            case 'bool':
                if (!\is_bool($value)) {
                    $run->add($path, 'invalid_type', 'Expected a boolean.');
                }

                break;

            case 'enum':
                if (!\is_string($value)) {
                    $run->add($path, 'invalid_type', 'Expected a string.');
                } elseif (!\in_array($value, $spec['values'], true)) {
                    $run->add($path, 'invalid_enum', 'Must be one of: '.implode(', ', $spec['values']).'.');
                }

                break;

            case 'id':
                if (!\is_string($value) || 1 !== preg_match(self::ID_PATTERN, $value)) {
                    $run->add($path, 'invalid_pattern', 'Must match '.self::ID_PATTERN.'.');
                }

                break;

            case 'date':
                if (!\is_string($value) || !self::isValidDate($value)) {
                    $run->add($path, 'invalid_date', 'Must be a real calendar date (YYYY-MM-DD).');
                }

                break;

            case 'list':
                $this->validateList($value, $spec, $path, $run);

                break;

            case 'object':
                $this->validateObject($value, $spec, $path, $run);

                break;
        }
    }

    /**
     * @return bool true when $value is a string within bounds (the caller may run further checks)
     */
    private function validateText(mixed $value, int $max, string $path, ValidationRun $run): bool
    {
        if (!\is_string($value)) {
            $run->add($path, 'invalid_type', 'Expected a string.');

            return false;
        }

        if (1 === preg_match(self::CONTROL_CHARS, $value)) {
            $run->add($path, 'control_characters', 'Control characters are not allowed.');

            return false;
        }

        if (mb_strlen($value) > $max) {
            $run->add($path, 'too_long', \sprintf('At most %d characters are allowed.', $max));

            return false;
        }

        if ($run->strict && '' === trim($value)) {
            $run->add($path, 'empty_value', 'Must not be empty.');

            return false;
        }

        return true;
    }

    private function validateHtml(mixed $value, string $path, ValidationRun $run): void
    {
        if (!\is_string($value)) {
            $run->add($path, 'invalid_type', 'Expected an HTML string.');

            return;
        }

        if (mb_strlen($value) > self::MAX_HTML_LENGTH) {
            $run->add($path, 'too_long', \sprintf('At most %d characters are allowed.', self::MAX_HTML_LENGTH));

            return;
        }

        if ($run->strict && '' === trim($value)) {
            $run->add($path, 'empty_value', 'Must not be empty.');

            return;
        }

        if ($this->sanitizer->sanitize($value) !== $value) {
            $run->add($path, 'html_not_sanitized', 'HTML contains markup outside the allow-list; run it through the sanitizer first.');

            return;
        }

        // Sanitized output has one fixed shape: <a href="..." rel="..."[ target="_blank"]>.
        $count = preg_match_all('/<a href="([^"]*)" rel="([^"]*)"/', $value, $matches, \PREG_SET_ORDER);

        if (false === $count || 0 === $count) {
            return;
        }

        foreach ($matches as $match) {
            $url = html_entity_decode($match[1], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $sponsored = \in_array('sponsored', explode(' ', $match[2]), true);

            $this->checkLink($url, $sponsored, $path, $run);
        }
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function validateList(mixed $value, array $spec, string $path, ValidationRun $run): void
    {
        if (!\is_array($value) || !array_is_list($value)) {
            $run->add($path, 'invalid_type', 'Expected a list.');

            return;
        }

        if (\count($value) > (int) $spec['max']) {
            $run->add($path, 'too_many_items', \sprintf('At most %d items are allowed.', (int) $spec['max']));

            return;
        }

        if ($run->strict && \count($value) < (int) $spec['min']) {
            $run->add($path, 'too_few_items', \sprintf('At least %d items are required.', (int) $spec['min']));
        }

        $seen = [];

        foreach ($value as $index => $item) {
            $this->validateValue($item, $spec['item'], $path.'/'.$index, $run);

            if (true === $spec['unique'] && self::isObject($item) && \is_string($item['id'] ?? null)) {
                if (isset($seen[$item['id']])) {
                    $run->add($path.'/'.$index.'/id', 'duplicate_id', \sprintf('Id "%s" is used more than once in this list.', $item['id']));
                }

                $seen[$item['id']] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function validateObject(mixed $value, array $spec, string $path, ValidationRun $run): void
    {
        if (!self::isObject($value)) {
            $run->add($path, 'invalid_type', 'Expected an object.');

            return;
        }

        /** @var array<string, array{0: array<string, mixed>, 1: bool}> $fields */
        $fields = $spec['fields'];

        foreach (array_keys($value) as $key) {
            if (!isset($fields[$key])) {
                $run->add($path.'/'.$key, 'unknown_field', 'Unknown property.');
            }
        }

        foreach ($fields as $name => [$fieldSpec, $required]) {
            if (!\array_key_exists($name, $value)) {
                if ($required && $run->strict) {
                    $run->add($path.'/'.$name, 'missing_field', \sprintf('"%s" is required.', $name));
                }

                continue;
            }

            $this->validateValue($value[$name], $fieldSpec, $path.'/'.$name, $run);
        }

        $linkField = $spec['link'] ?? null;

        if (\is_string($linkField)) {
            $url = $value[$linkField] ?? null;

            if (\is_string($url) && '' !== $url) {
                $this->checkLink($url, true === ($value['sponsored'] ?? false), $path.'/'.$linkField, $run);
            }
        }
    }

    private function checkLink(string $url, bool $sponsored, string $path, ValidationRun $run): void
    {
        $code = $this->urlPolicy->check($url);

        if (null !== $code) {
            $run->add($path, $code, UrlPolicy::CODE_SCHEME_NOT_ALLOWED === $code
                ? 'Only http(s) and site-relative URLs are allowed.'
                : 'Not a valid http(s) or site-relative URL.');

            return;
        }

        $affiliate = $this->urlPolicy->isAffiliateHost($this->urlPolicy->host($url));

        if ($sponsored) {
            $run->noteSponsoredLink();

            if (!$affiliate) {
                $run->add($path, 'affiliate_host_not_allowed', 'A sponsored link must point at an allow-listed affiliate host.');
            }
        } elseif ($affiliate && !$this->isUnmarkedHelpCenterCitation($url, $run)) {
            $run->add($path, 'affiliate_not_sponsored', 'Links to affiliate hosts must be marked as sponsored unless they are clean help-center citations.');
        }
    }

    private function isUnmarkedHelpCenterCitation(string $url, ValidationRun $run): bool
    {
        if (ArticleContract::BLOCK_SOURCES !== $run->currentBlockType) {
            return false;
        }

        $parts = parse_url($url);

        if (!\is_array($parts) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $path = $parts['path'] ?? '';

        return \is_string($path) && 1 === preg_match('~(?:^|/)help-center/article/[A-Za-z0-9][A-Za-z0-9-]*/?$~i', $path);
    }

    /**
     * @phpstan-assert-if-true array<mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return \is_array($value) && ([] === $value || !array_is_list($value));
    }

    private static function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value);

        return false !== $date && $date->format('Y-m-d') === $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function blockSpec(string $type): array
    {
        $ctaFields = [
            'label' => [self::text(200), true],
            'url' => [['t' => 'url'], true],
            'variant' => [self::enum(['primary', 'ghost']), true],
            'sponsored' => [['t' => 'bool'], true],
        ];
        $cta = self::obj($ctaFields, 'url');
        $rating = self::enum(['good', 'mixed', 'bad', 'neutral']);

        return match ($type) {
            ArticleContract::BLOCK_CUSTOM_CODE => self::obj([
                'html' => [['t' => 'raw', 'max' => ArticleContract::MAX_CUSTOM_CODE_BYTES], false],
                'css' => [['t' => 'raw', 'max' => ArticleContract::MAX_CUSTOM_CODE_BYTES], false],
                'js' => [['t' => 'raw', 'max' => ArticleContract::MAX_CUSTOM_CODE_BYTES], false],
            ]),
            ArticleContract::BLOCK_RICH_TEXT => self::obj([
                'html' => [['t' => 'html'], true],
                'variant' => [self::enum(['default', 'lead', 'callout']), false],
            ]),
            ArticleContract::BLOCK_COMPARISON_TABLE => self::obj([
                'caption' => [self::text(500), true],
                'criteriaLabel' => [self::text(200), true],
                'legend' => [self::list(self::obj([
                    'rating' => [$rating, true],
                    'label' => [self::text(200), true],
                ]), 0, 4), false],
                'columns' => [self::list(self::obj([
                    'id' => [['t' => 'id'], true],
                    'name' => [self::text(200), true],
                    'subtitle' => [self::text(500), false],
                    'link' => [self::obj([
                        'url' => [['t' => 'url'], true],
                        'sponsored' => [['t' => 'bool'], true],
                    ], 'url'), false],
                ]), 2, 8, true), true],
                'rows' => [self::list(self::obj([
                    'id' => [['t' => 'id'], true],
                    'label' => [self::text(200), true],
                    'cells' => [self::list(self::obj([
                        'rating' => [$rating, false],
                        'value' => [self::text(500), true],
                        'note' => [self::text(2000), false],
                    ]), 2, 8), true],
                ]), 1, 30, true), true],
                'ctaRow' => [self::obj([
                    'label' => [self::text(200), true],
                    'items' => [self::list(self::obj([
                        'columnId' => [['t' => 'id'], true],
                        ...$ctaFields,
                    ], 'url'), 1, 8), true],
                ]), false],
            ]),
            ArticleContract::BLOCK_PRODUCT_CARD => self::obj([
                'title' => [self::text(200), true],
                'badge' => [self::text(200), false],
                'tag' => [self::text(200), false],
                'summary' => [self::text(2000), false],
                'bullets' => [self::list(self::text(500), 0, 10), false],
                'rating' => [$rating, false],
                'cta' => [$cta, false],
                'secondaryCta' => [$cta, false],
                'note' => [self::text(500), false],
            ]),
            ArticleContract::BLOCK_FAQ => self::obj([
                'heading' => [self::text(200), true],
                'items' => [self::list(self::obj([
                    'id' => [['t' => 'id'], true],
                    'question' => [self::text(500), true],
                    'answerHtml' => [['t' => 'html'], true],
                ]), 1, 30, true), true],
            ]),
            ArticleContract::BLOCK_CTA => self::obj([
                ...$ctaFields,
                'title' => [self::text(200), false],
                'text' => [self::text(500), false],
                'sticky' => [['t' => 'bool'], false],
            ], 'url'),
            ArticleContract::BLOCK_CARD_SELECTOR => self::obj([
                'heading' => [self::text(200), true],
                'groupLabel' => [self::text(200), true],
                'targetBlockId' => [['t' => 'id'], true],
                'defaultOptionId' => [['t' => 'id'], true],
                'options' => [self::list(self::obj([
                    'id' => [['t' => 'id'], true],
                    'label' => [self::text(200), true],
                    'columnId' => [['t' => 'id'], true],
                ]), 2, 8, true), true],
                'labels' => [self::obj([
                    'selectedTable' => [['t' => 'template'], true],
                    'stickyCaption' => [['t' => 'template'], true],
                    'ctaAria' => [['t' => 'template'], true],
                    'mobileHint' => [['t' => 'template'], true],
                ]), true],
            ]),
            ArticleContract::BLOCK_SOURCES => self::obj([
                'heading' => [self::text(200), true],
                'items' => [self::list(self::obj([
                    'id' => [['t' => 'id'], true],
                    'label' => [self::text(500), true],
                    'url' => [['t' => 'url'], true],
                    'sponsored' => [['t' => 'bool'], false],
                    'publisher' => [self::text(200), false],
                    'accessed_at' => [['t' => 'date'], false],
                ], 'url'), 1, 40, true), true],
            ]),
            ArticleContract::BLOCK_SPONSORED_DISCLOSURE => self::obj([
                'text' => [self::text(2000), true],
                'learnMoreLabel' => [self::text(200), false],
                'learnMoreUrl' => [['t' => 'url'], false],
            ], 'learnMoreUrl'),
            default => throw new \LogicException(\sprintf('No spec for block type "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(int $max): array
    {
        return ['t' => 'text', 'max' => $max];
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, mixed>
     */
    private static function enum(array $values): array
    {
        return ['t' => 'enum', 'values' => $values];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function list(array $item, int $min, int $max, bool $unique = false): array
    {
        return ['t' => 'list', 'item' => $item, 'min' => $min, 'max' => $max, 'unique' => $unique];
    }

    /**
     * @param array<string, array{0: array<string, mixed>, 1: bool}> $fields
     *
     * @return array<string, mixed>
     */
    private static function obj(array $fields, ?string $link = null): array
    {
        return ['t' => 'object', 'fields' => $fields, 'link' => $link];
    }
}
