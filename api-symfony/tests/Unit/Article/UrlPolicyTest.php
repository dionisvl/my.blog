<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article;

use App\Article\Validation\UrlPolicy;
use App\Tests\Support\ContractFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideUrls(): iterable
    {
        yield 'https' => ['https://docs.example.test/a?b=1#c', null];

        yield 'http with port' => ['http://docs.example.test:8080/a', null];

        yield 'root relative' => ['/ru/post/some-slug', null];

        yield 'bare slash' => ['/', null];

        yield 'javascript' => ['javascript:alert(1)', UrlPolicy::CODE_SCHEME_NOT_ALLOWED];

        yield 'mixed case javascript' => ['JaVaScRiPt:alert(1)', UrlPolicy::CODE_SCHEME_NOT_ALLOWED];

        yield 'data' => ['data:text/html,x', UrlPolicy::CODE_SCHEME_NOT_ALLOWED];

        yield 'mailto' => ['mailto:a@example.test', UrlPolicy::CODE_SCHEME_NOT_ALLOWED];

        yield 'ftp' => ['ftp://example.test/x', UrlPolicy::CODE_SCHEME_NOT_ALLOWED];

        yield 'protocol relative' => ['//evil.example.test/x', UrlPolicy::CODE_INVALID];

        yield 'userinfo' => ['https://user:pw@docs.example.test/', UrlPolicy::CODE_INVALID];

        yield 'whitespace' => ['https://docs.example.test/a b', UrlPolicy::CODE_INVALID];

        yield 'tab inside scheme' => ["java\tscript:alert(1)", UrlPolicy::CODE_INVALID];

        yield 'backslash' => ['/\\evil.example.test', UrlPolicy::CODE_INVALID];

        yield 'no host' => ['https:///path', UrlPolicy::CODE_INVALID];

        yield 'bare word' => ['example.test/x', UrlPolicy::CODE_INVALID];

        yield 'empty' => ['', UrlPolicy::CODE_INVALID];

        yield 'fragment only' => ['#top', UrlPolicy::CODE_INVALID];

        yield 'uppercase scheme' => ['HTTPS://docs.example.test/a', UrlPolicy::CODE_INVALID];

        yield 'mixed case scheme' => ['HtTpS://docs.example.test/a', UrlPolicy::CODE_INVALID];

        yield 'nbsp inside absolute url' => ["https://docs.example.test/a\u{00A0}b", UrlPolicy::CODE_INVALID];

        yield 'ideographic space inside absolute url' => ["https://docs.example.test/a\u{3000}b", UrlPolicy::CODE_INVALID];

        yield 'bom inside absolute url' => ["https://docs.example.test/a\u{FEFF}", UrlPolicy::CODE_INVALID];

        yield 'nbsp inside relative url' => ["/ru/post/\u{00A0}slug", UrlPolicy::CODE_INVALID];

        yield 'nbsp after slash' => ["/\u{00A0}", UrlPolicy::CODE_INVALID];

        yield 'ideographic space inside relative url' => ["/a\u{3000}b", UrlPolicy::CODE_INVALID];

        yield 'invalid utf-8' => ["https://docs.example.test/a\xC2", UrlPolicy::CODE_INVALID];
    }

    #[DataProvider('provideUrls')]
    public function testCheck(string $url, ?string $expected): void
    {
        self::assertSame($expected, ContractFixtures::urlPolicy()->check($url));
    }

    public function testOverlongUrlIsRejected(): void
    {
        self::assertSame(UrlPolicy::CODE_INVALID, ContractFixtures::urlPolicy()->check('https://docs.example.test/'.str_repeat('a', 2100)));
    }

    public function testAffiliateHostMatchesExactHostAndSubdomainsOnly(): void
    {
        $policy = ContractFixtures::urlPolicy();

        self::assertTrue($policy->isAffiliateHost($policy->host('https://partner.example.test/x')));
        self::assertTrue($policy->isAffiliateHost($policy->host('https://www.PARTNER.example.test/x')));
        self::assertFalse($policy->isAffiliateHost($policy->host('https://notpartner.example.test/x')));
        self::assertFalse($policy->isAffiliateHost($policy->host('https://partner.example.test.evil.test/x')));
        self::assertFalse($policy->isAffiliateHost($policy->host('/relative')));
        self::assertFalse($policy->isAffiliateHost(null));
    }

    public function testAffiliateHostMatchesFullyQualifiedTrailingDot(): void
    {
        $policy = ContractFixtures::urlPolicy();

        self::assertTrue($policy->isAffiliateHost($policy->host('https://partner.example.test./x')));
        self::assertTrue($policy->isAffiliateHost($policy->host('https://www.PARTNER.example.test./x')));
        self::assertFalse($policy->isAffiliateHost($policy->host('https://partner.example.test.evil.test./x')));
    }

    public function testHostIsNullForRelativeAndInvalidUrls(): void
    {
        $policy = ContractFixtures::urlPolicy();

        self::assertNull($policy->host('/relative'));
        self::assertNull($policy->host('javascript:alert(1)'));
        self::assertNull($policy->host('HTTPS://Docs.Example.test/x'));
        self::assertSame('docs.example.test', $policy->host('https://Docs.Example.test/x'));
        self::assertSame('bybit.com', $policy->host('https://Bybit.com./x'));
    }

    public function testPercentEncodedAffiliateHostCannotBypassSponsoredPolicy(): void
    {
        $policy = new UrlPolicy(['bybit.com']);

        self::assertSame(UrlPolicy::CODE_INVALID, $policy->check('https://by%62it.com/x'));
        self::assertNull($policy->host('https://by%62it.com/x'));
    }

    public function testFullwidthDotAffiliateHostCannotBypassSponsoredPolicy(): void
    {
        $policy = new UrlPolicy(['bybit.com']);

        self::assertSame(UrlPolicy::CODE_INVALID, $policy->check("https://bybit\u{FF0E}com/x"));
        self::assertNull($policy->host("https://bybit\u{FF0E}com/x"));
    }
}
