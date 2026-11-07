<?php

declare(strict_types=1);

namespace App\Article;

/**
 * Constants of the modular article document contract. The machine-readable form lives in
 * contracts/article-blocks/v1/schema.json; ArticleContractTest keeps the two in sync.
 */
final class ArticleContract
{
    public const int SCHEMA_VERSION = 1;

    public const string LOCALE_RU = 'ru';

    public const string LOCALE_EN = 'en';

    /** @var list<string> */
    public const array LOCALES = [self::LOCALE_RU, self::LOCALE_EN];

    public const string BLOCK_RICH_TEXT = 'richText';

    public const string BLOCK_COMPARISON_TABLE = 'comparisonTable';

    public const string BLOCK_PRODUCT_CARD = 'productCard';

    public const string BLOCK_FAQ = 'faq';

    public const string BLOCK_CTA = 'cta';

    public const string BLOCK_CARD_SELECTOR = 'cardSelector';

    public const string BLOCK_SOURCES = 'sources';

    public const string BLOCK_SPONSORED_DISCLOSURE = 'sponsoredDisclosure';

    /** Owner-authored raw HTML/CSS/JS. Stored verbatim, NOT sanitized (single trusted author, first release). */
    public const string BLOCK_CUSTOM_CODE = 'customCode';

    /** @var list<string> */
    public const array BLOCK_TYPES = [
        self::BLOCK_RICH_TEXT,
        self::BLOCK_COMPARISON_TABLE,
        self::BLOCK_PRODUCT_CARD,
        self::BLOCK_FAQ,
        self::BLOCK_CTA,
        self::BLOCK_CARD_SELECTOR,
        self::BLOCK_SOURCES,
        self::BLOCK_SPONSORED_DISCLOSURE,
        self::BLOCK_CUSTOM_CODE,
    ];

    public const int MAX_BLOCKS = 60;

    public const int MAX_CUSTOM_CODE_BYTES = 100000;

    public const int MAX_DOCUMENT_BYTES = 262144;

    public const int MAX_TITLE_LENGTH = 255;

    public const int MAX_SUMMARY_LENGTH = 2000;

    public const int MAX_META_TITLE_LENGTH = 255;

    public const int MAX_META_DESCRIPTION_LENGTH = 500;

    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';
}
