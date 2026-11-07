<?php

/*
 * Smoke test for Story 1 against a disposable, migrated (not SchemaTool-built) PostgreSQL database.
 *
 * Usage (from api-symfony/, database must be a disposable *_test database):
 *   php tests/Support/article_migrated_schema_smoke.php            seed + publish modular-publish (idempotent)
 *   php tests/Support/article_migrated_schema_smoke.php unpublish  withdraw the en locale, keep the rows
 *
 * Exit codes: 0 = ok (or already in the requested state), 1 = failure, 2 = bad usage.
 * Never resets the database, never runs DDL, never prints connection details.
 */

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/ContractFixtures.php';

use App\Article\ArticleDocumentNormalizer;
use App\Article\ArticleDraft;
use App\Article\ArticlePublisher;
use App\Article\SearchTextExtractor;
use App\Article\Validation\ArticleDocumentValidator;
use App\Entity\Post;
use App\Entity\PostLocale;
use App\Entity\User;
use App\Kernel;
use App\Tests\Support\ContractFixtures;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

const SMOKE_SLUG = 'modular-publish';
const SMOKE_LOCALE = 'en';
const SMOKE_TITLE = 'Card <b>title</b>';
const SMOKE_SUMMARY = '<script>alert(1)</script> & more';
const SMOKE_ESCAPED_SUMMARY = '&lt;script&gt;alert(1)&lt;/script&gt; &amp; more';
const SMOKE_AUTHOR_EMAIL = 'article-smoke-author@example.test';
const SMOKE_REQUIRED_TABLES = ['users', 'posts', 'post_locales'];

$mode = $argv[1] ?? 'publish';

if (!\in_array($mode, ['publish', 'unpublish'], true)) {
    fwrite(STDERR, "Usage: php tests/Support/article_migrated_schema_smoke.php [publish|unpublish]\n");

    exit(2);
}

function smokeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException('Assertion failed: '.$message);
    }
}

$kernel = new Kernel('test', false);
$kernel->boot();

try {
    $container = $kernel->getContainer()->get('test.service_container');
    $entityManager = $container->get('doctrine')->getManager();
    \assert($entityManager instanceof EntityManagerInterface);

    $connection = $entityManager->getConnection();
    smokeCheck($connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'the smoke test only runs against PostgreSQL');
    smokeCheck(str_ends_with((string) $connection->getDatabase(), '_test'), 'refusing to run against a database whose name does not end in _test');

    $connection->executeStatement("SET application_name = 'article-migrated-schema-smoke'");

    $existingTables = $connection->createSchemaManager()->listTableNames();
    foreach (SMOKE_REQUIRED_TABLES as $table) {
        smokeCheck(\in_array($table, $existingTables, true), \sprintf('migrated schema is missing table "%s"; run the migrated database first', $table));
    }

    $sanitizer = ContractFixtures::sanitizer();
    $publisher = new ArticlePublisher(
        $entityManager,
        new ArticleDocumentNormalizer($sanitizer),
        new ArticleDocumentValidator(ContractFixtures::urlPolicy(), $sanitizer),
        new SearchTextExtractor(),
    );

    $postRepository = $entityManager->getRepository(Post::class);
    $localeRepository = $entityManager->getRepository(PostLocale::class);
    $anchor = $postRepository->findOneBy(['slug' => SMOKE_SLUG]);

    if ('unpublish' === $mode) {
        smokeCheck($anchor instanceof Post, \sprintf('no post with slug "%s" exists; nothing to unpublish', SMOKE_SLUG));
        smokeCheck($anchor->isModular(), 'the smoke anchor must be a modular article');

        $locale = $entityManager->find(PostLocale::class, ['postId' => (int) $anchor->getId(), 'locale' => SMOKE_LOCALE]);
        if (!$locale instanceof PostLocale || !$locale->isPublished()) {
            echo \sprintf("already unpublished: %s (%s)\n", SMOKE_SLUG, SMOKE_LOCALE);

            exit(0);
        }

        $publisher->unpublish($anchor, SMOKE_LOCALE);

        $entityManager->clear();
        $anchor = $postRepository->find((int) $anchor->getId());
        smokeCheck($anchor instanceof Post && $anchor->getStatus(), 'anchor must be hidden (status = true) after the last locale is unpublished');
        smokeCheck(null === $postRepository->findPublishedBySlug(SMOKE_SLUG), 'slug must no longer resolve to a published post');

        echo \sprintf("unpublished: %s (%s), post id %d\n", SMOKE_SLUG, SMOKE_LOCALE, (int) $anchor->getId());

        exit(0);
    }

    // publish mode: idempotent. If the fixture is already there in the expected state, report and stop.
    if ($anchor instanceof Post) {
        $locale = $entityManager->find(PostLocale::class, ['postId' => (int) $anchor->getId(), 'locale' => SMOKE_LOCALE]);
        smokeCheck(
            $anchor->isModular()
            && !$anchor->getStatus()
            && SMOKE_TITLE === $anchor->getTitle()
            && $locale instanceof PostLocale
            && $locale->isPublished(),
            \sprintf('slug "%s" already exists in an unexpected state; reset the disposable database and rerun', SMOKE_SLUG),
        );

        echo \sprintf("already seeded: %s (%s), post id %d\n", SMOKE_SLUG, SMOKE_LOCALE, (int) $anchor->getId());

        exit(0);
    }

    $author = $entityManager->getRepository(User::class)->findOneBy(['email' => SMOKE_AUTHOR_EMAIL]);
    if (!$author instanceof User) {
        $author = new User();
        $author->setName('Smoke Author')
            ->setEmail(SMOKE_AUTHOR_EMAIL)
            // Placeholder only: this account is fictional and cannot be used to log in.
            ->setPassword('smoke-test-disabled')
            ->setIsAdmin(true)
            ->setStatus(1);
        $entityManager->persist($author);
        $entityManager->flush();
    }

    $anchor = $publisher->createAnchor(SMOKE_SLUG, SMOKE_LOCALE, $author, 'Working title');
    $publisher->saveDraft(
        $anchor,
        SMOKE_LOCALE,
        new ArticleDraft(SMOKE_TITLE, SMOKE_SUMMARY, null, null, ContractFixtures::validDocument('minimal-en')),
        $author,
    );
    $publisher->publish($anchor, SMOKE_LOCALE);

    // Re-read from the database so the assertions check what was actually stored.
    $entityManager->clear();
    $stored = $postRepository->findPublishedBySlug(SMOKE_SLUG);
    smokeCheck($stored instanceof Post, 'published post is not visible through findPublishedBySlug');
    smokeCheck(false === $stored->getStatus(), 'posts.status must be false (visible) after publish');
    smokeCheck($stored->isModular(), 'anchor must keep the modular route mode');
    smokeCheck(SMOKE_LOCALE === $stored->getPrimaryLocale(), 'primary locale must be en');
    smokeCheck(SMOKE_TITLE === $stored->getTitle(), 'posts.title must hold the plain-text card title');
    smokeCheck(SMOKE_ESCAPED_SUMMARY === $stored->getDescription(), 'posts.description must hold the escaped summary');
    smokeCheck('<p>'.SMOKE_ESCAPED_SUMMARY.'</p>' === $stored->getContent(), 'posts.content must wrap the escaped summary in a paragraph');

    $locale = $entityManager->find(PostLocale::class, ['postId' => (int) $stored->getId(), 'locale' => SMOKE_LOCALE]);
    smokeCheck($locale instanceof PostLocale, 'post_locales row for en is missing');
    smokeCheck($locale->isPublished(), 'en locale must be published');
    smokeCheck(null !== $locale->getPublishedAt(), 'published_at must be set');
    smokeCheck(SMOKE_TITLE === $locale->getTitle(), 'locale title must be stored unescaped');
    smokeCheck(SMOKE_SUMMARY === $locale->getSummary(), 'locale summary must be stored unescaped');

    echo \sprintf(
        "published: %s (%s), post id %d\n",
        SMOKE_SLUG,
        SMOKE_LOCALE,
        (int) $stored->getId(),
    );

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, \get_class($e).': '.$e->getMessage()."\n");

    exit(1);
} finally {
    $kernel->shutdown();
}
