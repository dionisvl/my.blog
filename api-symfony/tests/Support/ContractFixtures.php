<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Article\ArticleDocumentNormalizer;
use App\Article\Validation\ArticleDocumentValidator;
use App\Article\Validation\RichTextSanitizer;
use App\Article\Validation\UrlPolicy;

/**
 * Access to the golden fixtures of contracts/article-blocks/v1 and to hand-wired validation services
 * (no container needed). The test affiliate host matches the `when@test` parameter in services.yaml.
 */
final class ContractFixtures
{
    public const string AFFILIATE_HOST = 'partner.example.test';

    public static function contractDir(): string
    {
        return \dirname(__DIR__, 2).'/contracts/article-blocks/v1';
    }

    /**
     * @return array<mixed>
     */
    public static function validDocument(string $name): array
    {
        return self::readJson(self::contractDir().'/fixtures/valid/'.$name.'.json');
    }

    /**
     * @return list<string> fixture names without extension
     */
    public static function validNames(): array
    {
        return self::names('valid');
    }

    /**
     * @return list<string>
     */
    public static function invalidNames(): array
    {
        return self::names('invalid');
    }

    /**
     * @return array{description: string, mode: string, expect_codes: list<string>, document: array<mixed>}
     */
    public static function invalidCase(string $name): array
    {
        /** @var array{description: string, mode: string, expect_codes: list<string>, document: array<mixed>} $case */
        $case = self::readJson(self::contractDir().'/fixtures/invalid/'.$name.'.json');

        return $case;
    }

    public static function urlPolicy(): UrlPolicy
    {
        return new UrlPolicy([self::AFFILIATE_HOST]);
    }

    public static function sanitizer(): RichTextSanitizer
    {
        return new RichTextSanitizer(self::urlPolicy());
    }

    public static function normalizer(): ArticleDocumentNormalizer
    {
        return new ArticleDocumentNormalizer(self::sanitizer());
    }

    public static function validator(): ArticleDocumentValidator
    {
        return new ArticleDocumentValidator(self::urlPolicy(), self::sanitizer());
    }

    /**
     * @return array<mixed>
     */
    private static function readJson(string $path): array
    {
        $json = file_get_contents($path);

        if (false === $json) {
            throw new \RuntimeException(\sprintf('Cannot read %s', $path));
        }

        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('%s is not a JSON object', $path));
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private static function names(string $kind): array
    {
        $files = glob(self::contractDir().'/fixtures/'.$kind.'/*.json');

        if (false === $files || [] === $files) {
            throw new \RuntimeException(\sprintf('No %s fixtures found', $kind));
        }

        sort($files);

        return array_map(static fn (string $file): string => basename($file, '.json'), $files);
    }
}
