<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article;

use App\Tests\Support\ContractFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RichTextSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideCases(): iterable
    {
        yield 'plain markup is kept' => ['<p>Hi <strong>there</strong></p>', '<p>Hi <strong>there</strong></p>'];

        yield 'script is dropped with its content' => ['<p>a</p><script>alert(1)</script>', '<p>a</p>'];

        yield 'event handler attributes are stripped' => ['<p onclick="x()" style="color:red">a</p>', '<p>a</p>'];

        yield 'unknown wrapper is unwrapped' => ['<div><span>text</span></div>', 'text'];

        yield 'iframe is dropped' => ['<p>a</p><iframe src="https://evil.example.test"></iframe>', '<p>a</p>'];

        yield 'javascript href unwraps the anchor' => ['<a href="javascript:alert(1)">click</a>', 'click'];

        yield 'entity-obfuscated javascript href unwraps the anchor' => ['<a href="jav&#x09;ascript:alert(1)">click</a>', 'click'];

        yield 'data url unwraps the anchor' => ['<a href="data:text/html;base64,AAAA">x</a>', 'x'];

        yield 'protocol relative href is refused' => ['<a href="//evil.example.test/">x</a>', 'x'];

        yield 'external link gets noopener' => [
            '<a href="https://docs.example.test/a">x</a>',
            '<a href="https://docs.example.test/a" rel="noopener">x</a>',
        ];

        yield 'sponsored relation survives, canonical order' => [
            '<a rel="sponsored nofollow" target="_BLANK" href="https://partner.example.test/a?x=1&amp;y=2">x</a>',
            '<a href="https://partner.example.test/a?x=1&amp;y=2" rel="noopener nofollow sponsored" target="_blank">x</a>',
        ];

        yield 'foreign target is dropped' => [
            '<a href="/contacts" target="evil">x</a>',
            '<a href="/contacts" rel="noopener">x</a>',
        ];

        yield 'text is escaped' => ['<p>1 &lt; 2 &amp; "3"</p>', '<p>1 &lt; 2 &amp; &quot;3&quot;</p>'];

        yield 'comments are removed' => ['<p>a<!-- hidden --></p>', '<p>a</p>'];

        yield 'forms are dropped' => ['<form action="/x"><input name="a"></form><p>b</p>', '<p>b</p>'];

        yield 'headings and lists are allowed' => ['<h2>T</h2><ul><li>a</li></ul>', '<h2>T</h2><ul><li>a</li></ul>'];

        yield 'empty input' => ['', ''];
    }

    #[DataProvider('provideCases')]
    public function testSanitize(string $input, string $expected): void
    {
        $sanitizer = ContractFixtures::sanitizer();

        self::assertSame($expected, $sanitizer->sanitize($input));
    }

    #[DataProvider('provideCases')]
    public function testSanitizeIsIdempotent(string $input, string $_expected): void
    {
        $sanitizer = ContractFixtures::sanitizer();
        $once = $sanitizer->sanitize($input);

        self::assertSame($once, $sanitizer->sanitize($once));
    }

    public function testNeverEmitsDisallowedMarkupForHostileInput(): void
    {
        $hostile = '<img src=x onerror=alert(1)><svg onload=alert(1)><math><mi xlink:href="javascript:alert(1)"></mi></math>'
            .'<p style="x" class="y" id="z" data-a="b">t</p><a href=" JaVaScRiPt:alert(1)">l</a><object data="x"></object>';

        $out = ContractFixtures::sanitizer()->sanitize($hostile);

        foreach (['onerror', 'onload', 'javascript', '<img', '<svg', '<object', 'style=', 'class=', 'id=', 'data-'] as $needle) {
            self::assertStringNotContainsString($needle, strtolower($out), $needle);
        }
    }
}
