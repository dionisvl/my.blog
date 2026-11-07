<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article;

use App\Article\ArticleContract;
use App\Tests\Support\ContractFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the PHP constants, the JSON Schema, the MANIFEST and the copy vendored into the Astro
 * frontend in sync. The Go tests verify the same MANIFEST against the same files.
 */
final class ArticleContractTest extends TestCase
{
    public function testPhpConstantsMatchTheJsonSchema(): void
    {
        $schema = self::schema();

        self::assertSame(ArticleContract::SCHEMA_VERSION, $schema['properties']['schema_version']['const']);
        self::assertSame(ArticleContract::LOCALES, $schema['properties']['locale']['enum']);
        self::assertSame(ArticleContract::MAX_BLOCKS, $schema['properties']['blocks']['maxItems']);
        self::assertSame(ArticleContract::BLOCK_TYPES, $schema['$defs']['block']['properties']['type']['enum']);
    }

    public function testEveryBlockTypeHasASchemaDefinitionAndAFixtureUsingIt(): void
    {
        $schema = self::schema();
        $definedTypes = [];

        foreach ($schema['$defs']['block']['oneOf'] as $branch) {
            $name = basename((string) $branch['$ref']);
            $definedTypes[] = $schema['$defs'][$name]['properties']['type']['const'];
        }

        self::assertEqualsCanonicalizing(ArticleContract::BLOCK_TYPES, $definedTypes);

        $usedTypes = [];

        foreach (ContractFixtures::validNames() as $name) {
            foreach (ContractFixtures::validDocument($name)['blocks'] as $block) {
                $usedTypes[] = $block['type'];
            }
        }

        foreach (ArticleContract::BLOCK_TYPES as $type) {
            self::assertContains($type, $usedTypes, \sprintf('No valid fixture exercises block type "%s".', $type));
        }
    }

    public function testRuAndEnFixturesShareTheSameBlockStructure(): void
    {
        $shape = static fn (array $document): array => array_map(
            static fn (array $block): string => $block['id'].':'.$block['type'],
            $document['blocks'],
        );

        self::assertSame(
            $shape(ContractFixtures::validDocument('cards-ru')),
            $shape(ContractFixtures::validDocument('cards-en')),
        );
    }

    public function testManifestMatchesTheContractFiles(): void
    {
        $dir = ContractFixtures::contractDir();
        $manifest = self::parseManifest($dir.'/MANIFEST.sha256');

        self::assertSame(array_keys($manifest), self::contractFiles($dir), 'MANIFEST.sha256 must list exactly the contract files (regenerate it).');

        foreach ($manifest as $relative => $hash) {
            self::assertSame($hash, hash_file('sha256', $dir.'/'.$relative), $relative);
        }
    }

    public function testVendoredFrontendCopyIsIdentical(): void
    {
        $source = ContractFixtures::contractDir();
        $vendored = \dirname(__DIR__, 4).'/frontend-astro/src/contracts/article-blocks/v1';

        if (!is_dir($vendored)) {
            self::markTestSkipped('frontend-astro is not part of this checkout.');
        }

        self::assertSame(
            file_get_contents($source.'/MANIFEST.sha256'),
            file_get_contents($vendored.'/MANIFEST.sha256'),
            'Vendored MANIFEST.sha256 differs; copy contracts/article-blocks/v1 into frontend-astro/src/contracts.',
        );

        foreach (array_keys(self::parseManifest($source.'/MANIFEST.sha256')) as $relative) {
            self::assertFileExists($vendored.'/'.$relative);
            self::assertSame(hash_file('sha256', $source.'/'.$relative), hash_file('sha256', $vendored.'/'.$relative), $relative);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        $schema = json_decode((string) file_get_contents(ContractFixtures::contractDir().'/schema.json'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($schema));

        return $schema;
    }

    /**
     * @return array<string, string> relative path => sha256
     */
    private static function parseManifest(string $path): array
    {
        $entries = [];

        foreach (explode("\n", trim((string) file_get_contents($path))) as $line) {
            if (1 !== preg_match('/^([0-9a-f]{64})  (\S+)$/', $line, $match)) {
                self::fail(\sprintf('Malformed manifest line: %s', $line));
            }

            $entries[$match[2]] = $match[1];
        }

        return $entries;
    }

    /**
     * @return list<string> sorted relative paths of schema.json and all fixtures
     */
    private static function contractFiles(string $dir): array
    {
        $files = ['schema.json'];

        foreach (['valid', 'invalid'] as $kind) {
            foreach (glob($dir.'/fixtures/'.$kind.'/*.json') ?: [] as $file) {
                $files[] = 'fixtures/'.$kind.'/'.basename($file);
            }
        }

        sort($files);

        return $files;
    }
}
