<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article;

use App\Article\Validation\ValidationIssue;
use App\Tests\Support\ContractFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArticleDocumentValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidFixtures(): iterable
    {
        foreach (ContractFixtures::validNames() as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFixtures(): iterable
    {
        foreach (ContractFixtures::invalidNames() as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('provideValidFixtures')]
    public function testValidFixtureHasNoIssuesEvenForPublication(string $name): void
    {
        $document = ContractFixtures::validDocument($name);
        $locale = \is_string($document['locale'] ?? null) ? $document['locale'] : null;

        $issues = ContractFixtures::validator()->validate($document, true, $locale);

        self::assertSame([], self::describe($issues));
    }

    #[DataProvider('provideValidFixtures')]
    public function testValidFixtureIsAFixedPointOfTheNormalizer(string $name): void
    {
        $document = ContractFixtures::validDocument($name);

        self::assertSame($document, ContractFixtures::normalizer()->normalize($document));
    }

    #[DataProvider('provideInvalidFixtures')]
    public function testInvalidFixtureReportsTheExpectedCodes(string $name): void
    {
        $case = ContractFixtures::invalidCase($name);

        self::assertContains($case['mode'], ['draft', 'publication'], $name);

        $issues = ContractFixtures::validator()->validate($case['document'], 'publication' === $case['mode']);
        $codes = array_map(static fn (ValidationIssue $issue): string => $issue->code, $issues);

        foreach ($case['expect_codes'] as $expected) {
            self::assertContains($expected, $codes, \sprintf('%s: %s (got: %s)', $name, $case['description'], implode(', ', $codes)));
        }
    }

    public function testDraftModeToleratesMissingRequiredPropertiesButPublicationDoesNot(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'faq', 'type' => 'faq', 'props' => ['heading' => 'FAQ']],
            ],
        ];

        self::assertSame([], self::describe(ContractFixtures::validator()->validate($document, false, 'en')));

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'en'),
        );

        self::assertContains('missing_field', $codes);
    }

    public function testPublicationRequiresAtLeastOneBlock(): void
    {
        $document = ['schema_version' => 1, 'locale' => 'ru', 'blocks' => []];

        self::assertSame([], self::describe(ContractFixtures::validator()->validate($document, false, 'ru')));

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'ru'),
        );

        self::assertContains('no_blocks', $codes);
    }

    public function testDocumentLocaleMustMatchTheLocale(): void
    {
        $document = ContractFixtures::validDocument('minimal-en');

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'ru'),
        );

        self::assertSame(['locale_mismatch'], $codes);
    }

    public function testMoreThanTheMaximumNumberOfBlocksIsRejected(): void
    {
        $blocks = [];

        for ($i = 0; $i < 61; ++$i) {
            $blocks[] = ['id' => 'b'.$i, 'type' => 'richText', 'props' => ['html' => '<p>x</p>']];
        }

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate(['schema_version' => 1, 'locale' => 'en', 'blocks' => $blocks], false),
        );

        self::assertContains('too_many_items', $codes);
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $document = ContractFixtures::validDocument('minimal-en');
        $document['verified_at'] = '2026-02-31';

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, false),
        );

        self::assertContains('invalid_date', $codes);
    }

    public function testOfficialHelpCenterSourceOnAffiliateHostDoesNotNeedSponsored(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'sources', 'type' => 'sources', 'props' => [
                    'heading' => 'Sources',
                    'items' => [['id' => 'issuer', 'label' => 'Issuer help', 'url' => 'https://partner.example.test/help-center/article/fees']],
                ]],
            ],
        ];

        self::assertSame([], self::describe(ContractFixtures::validator()->validate($document, true, 'en')));
    }

    public function testReferralSourceOnAffiliateHostStillNeedsSponsored(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'sources', 'type' => 'sources', 'props' => [
                    'heading' => 'Sources',
                    'items' => [['id' => 'issuer', 'label' => 'Referral link', 'url' => 'https://partner.example.test/help-center/article/fees?ref=fixture']],
                ]],
            ],
        ];
        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'en'),
        );

        self::assertContains('affiliate_not_sponsored', $codes);
    }

    public function testNonHelpSourceOnAffiliateHostStillNeedsSponsored(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'sources', 'type' => 'sources', 'props' => [
                    'heading' => 'Sources',
                    'items' => [['id' => 'issuer', 'label' => 'Application page', 'url' => 'https://partner.example.test/signup']],
                ]],
            ],
        ];
        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'en'),
        );

        self::assertContains('affiliate_not_sponsored', $codes);
    }

    public function testSourceOnAnAffiliateHostCanBeExplicitlySponsored(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'verified_at' => '2026-11-03',
            'blocks' => [
                ['id' => 'disclosure', 'type' => 'sponsoredDisclosure', 'props' => ['text' => 'This article contains sponsored links.']],
                ['id' => 'sources', 'type' => 'sources', 'props' => [
                    'heading' => 'Sources',
                    'items' => [['id' => 'issuer', 'label' => 'Issuer terms', 'url' => 'https://partner.example.test/help', 'sponsored' => true]],
                ]],
            ],
        ];

        self::assertSame([], self::describe(ContractFixtures::validator()->validate($document, true, 'en')));
    }

    public function testPublicationRejectsAnEmptySponsoredCtaUrl(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'go', 'type' => 'cta', 'props' => [
                    'label' => 'Go', 'url' => '', 'variant' => 'primary', 'sponsored' => true,
                ]],
            ],
        ];

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, true, 'en'),
        );
        self::assertContains('invalid_url', $codes);
    }

    public function testBlockIdWithATrailingNewlineIsRejected(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => "faq\n", 'type' => 'faq', 'props' => ['heading' => 'FAQ']],
            ],
        ];

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, false, 'en'),
        );

        self::assertContains('invalid_pattern', $codes);
    }

    public function testSourceItemIdWithATrailingNewlineIsRejected(): void
    {
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'sources', 'type' => 'sources', 'props' => [
                    'heading' => 'Sources',
                    'items' => [['id' => "issuer\n", 'label' => 'Issuer terms', 'url' => 'https://example.test/help']],
                ]],
            ],
        ];

        $codes = array_map(
            static fn (ValidationIssue $issue): string => $issue->code,
            ContractFixtures::validator()->validate($document, false, 'en'),
        );

        self::assertContains('invalid_pattern', $codes);
    }

    /**
     * @param list<ValidationIssue> $issues
     *
     * @return list<string>
     */
    private static function describe(array $issues): array
    {
        return array_map(static fn (ValidationIssue $issue): string => $issue->path.' '.$issue->code, $issues);
    }
}
