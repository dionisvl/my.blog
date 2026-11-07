<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article;

use App\Article\SearchTextExtractor;
use App\Tests\Support\ContractFixtures;
use PHPUnit\Framework\TestCase;

final class SearchTextExtractorTest extends TestCase
{
    public function testExtractsReaderVisibleTextOnly(): void
    {
        $text = (new SearchTextExtractor())->extract(ContractFixtures::validDocument('cards-en'));

        self::assertStringContainsString('Fixture article comparing three fictional payment cards', $text);
        self::assertStringContainsString('Is verification required?', $text);
        self::assertStringContainsString('Placeholder answer with emphasis.', $text);
        self::assertStringContainsString('Beta Card', $text);
        self::assertStringContainsString('Includes a currency spread (placeholder)', $text);
        self::assertStringNotContainsString('<p>', $text);
        self::assertStringNotContainsString('https://', $text);
        self::assertStringNotContainsString('opt-beta', $text);
    }

    public function testMarkupIsStrippedAndEntitiesDecoded(): void
    {
        $document = [
            'blocks' => [
                ['id' => 'a', 'type' => 'richText', 'props' => ['html' => '<p>1 &lt; 2</p><p>next&amp;more</p>']],
            ],
        ];

        self::assertSame('1 < 2 next&more', (new SearchTextExtractor())->extract($document));
    }

    public function testOutputIsBounded(): void
    {
        $document = [
            'blocks' => [
                ['id' => 'a', 'type' => 'richText', 'props' => ['html' => '<p>'.str_repeat('word ', 40000).'</p>']],
            ],
        ];

        self::assertLessThanOrEqual(SearchTextExtractor::MAX_LENGTH, mb_strlen((new SearchTextExtractor())->extract($document)));
    }
}
