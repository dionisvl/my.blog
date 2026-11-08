<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Article\ArticleDocumentNormalizer;
use App\Article\ArticleDraft;
use App\Article\ArticlePublisher;
use App\Article\Exception\ArticleConflictException;
use App\Article\Exception\ArticleStateException;
use App\Article\Exception\ArticleValidationException;
use App\Article\SearchTextExtractor;
use App\Article\Validation\ArticleDocumentValidator;
use App\Entity\Post;
use App\Entity\PostLocale;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Tests\Support\ContractFixtures;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs the real ORM mapping via SchemaTool on SQLite and PostgreSQL. Migration-specific CHECK
 * constraints and triggers are covered by Go's Postgres-backed tests.
 */
final class ArticlePublisherTest extends DatabaseWebTestCase
{
    private ArticlePublisher $publisher;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $urlPolicy = ContractFixtures::urlPolicy();
        $sanitizer = ContractFixtures::sanitizer();

        $this->publisher = new ArticlePublisher(
            $this->em,
            new ArticleDocumentNormalizer($sanitizer),
            new ArticleDocumentValidator($urlPolicy, $sanitizer),
            new SearchTextExtractor(),
        );
        $this->author = $this->createAdminUser();
    }

    public function testAnchorIsCreatedAsAHiddenModularDraft(): void
    {
        $anchor = $this->publisher->createAnchor('modular-one', 'ru', $this->author, 'Working title');

        self::assertTrue($anchor->isModular());
        self::assertSame(Post::ROUTE_NEUTRAL, $anchor->getRouteMode());
        self::assertSame('ru', $anchor->getPrimaryLocale());
        self::assertTrue($anchor->getStatus(), 'posts.status is inverted: true = draft');
        self::assertNull(self::postRepository()->findPublishedBySlug('modular-one'));
        self::assertNull(self::postRepository()->findPublishedById((int) $anchor->getId()));
    }

    public function testSavingADraftNeverPublishesAnything(): void
    {
        $anchor = $this->publisher->createAnchor('modular-draft', 'en', $this->author, 'Working title');

        $locale = $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Draft title'), $this->author);

        self::assertSame(1, $locale->getSchemaVersion());
        self::assertFalse($locale->isPublished());
        self::assertNull($locale->getPublishedAt());
        self::assertTrue($anchor->getStatus());
        self::assertNull(self::postRepository()->findPublishedBySlug('modular-draft'));
    }

    public function testPublishMakesTheAnchorVisibleWithEscapedCardFields(): void
    {
        $anchor = $this->publisher->createAnchor('modular-publish', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Card <b>title</b>', '<script>alert(1)</script> & more'));

        $this->publisher->publish($anchor, 'en');

        $this->em->clear();
        $stored = self::postRepository()->findPublishedBySlug('modular-publish');

        self::assertInstanceOf(Post::class, $stored);
        self::assertFalse($stored->getStatus());
        self::assertSame('Card <b>title</b>', $stored->getTitle(), 'titles are plain text and escaped by the renderer');
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt; &amp; more', $stored->getDescription());
        self::assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; more</p>', $stored->getContent());

        $locale = $this->localeRow($stored, 'en');
        self::assertTrue($locale->isPublished());
        self::assertNotNull($locale->getPublishedAt());
    }

    public function testEditingAPublishedLocaleUpdatesTheLiveContentAndCard(): void
    {
        $anchor = $this->publisher->createAnchor('modular-live', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Live title'));
        $this->publisher->publish($anchor, 'en');

        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Next title'));

        $locale = $this->localeRow($anchor, 'en');
        self::assertTrue($locale->isPublished(), 'there is no separate draft: the locale stays published');
        self::assertSame('Next title', $locale->getTitle());
        self::assertSame('Next title', $anchor->getTitle());
        self::assertFalse($anchor->getStatus());
    }

    public function testEditingAPublishedLocaleIsValidatedForPublication(): void
    {
        $anchor = $this->publisher->createAnchor('modular-live-invalid', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Live title'));
        $this->publisher->publish($anchor, 'en');

        try {
            $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Live title', ''));
            self::fail('Live content must not lose its required summary.');
        } catch (ArticleValidationException $e) {
            self::assertContains('empty_value', $e->codes());
        }

        $localeState = $this->em->getConnection()->fetchAssociative(
            'SELECT title, published_at FROM post_locales WHERE post_id = ? AND locale = ?',
            [(int) $anchor->getId(), 'en'],
        );
        self::assertIsArray($localeState);
        self::assertSame('Live title', $localeState['title']);
        self::assertNotNull($localeState['published_at']);
    }

    public function testSingleLocalePublicationIsValidAndLastUnpublishHidesTheAnchor(): void
    {
        $anchor = $this->publisher->createAnchor('modular-single', 'ru', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Only English'));
        $this->publisher->publish($anchor, 'en');

        self::assertFalse($anchor->getStatus(), 'one published locale is enough, even if it is not the primary one');
        self::assertSame('Only English', $anchor->getTitle());

        $this->publisher->unpublish($anchor, 'en');

        self::assertTrue($anchor->getStatus());
        self::assertFalse($this->localeRow($anchor, 'en')->isPublished());
        self::assertNull(self::postRepository()->findPublishedBySlug('modular-single'));
    }

    public function testCardFieldsFollowThePrimaryLocaleAndSurviveUnpublishingTheOther(): void
    {
        $anchor = $this->publisher->createAnchor('modular-two', 'ru', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'ru', $this->draft('ru', 'Русский заголовок'));
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'English title'));
        $this->publisher->publish($anchor, 'en');
        self::assertSame('English title', $anchor->getTitle());

        $this->publisher->publish($anchor, 'ru');
        self::assertSame('Русский заголовок', $anchor->getTitle(), 'the primary locale wins once published');

        $this->publisher->unpublish($anchor, 'ru');
        self::assertFalse($anchor->getStatus());
        self::assertSame('English title', $anchor->getTitle());
    }

    /**
     * @param array<mixed> $document
     * @param list<string> $expectedCodes
     */
    #[DataProvider('refusedPublications')]
    public function testPublicationValidationBlocksPublishAndLeavesTheAnchorHidden(string $summary, array $document, array $expectedCodes): void
    {
        $anchor = $this->publisher->createAnchor('modular-invalid', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', new ArticleDraft('Title', $summary, null, null, $document));

        try {
            $this->publisher->publish($anchor, 'en');
            self::fail('Publication must be refused.');
        } catch (ArticleValidationException $e) {
            self::assertEqualsCanonicalizing($expectedCodes, $e->codes());
        }

        $connection = $this->em->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT status FROM posts WHERE id = ?', [(int) $anchor->getId()]));
        self::assertNull($connection->fetchOne(
            'SELECT published_at FROM post_locales WHERE post_id = ? AND locale = ?',
            [(int) $anchor->getId(), 'en'],
        ));
    }

    /**
     * @return iterable<string, array{string, array<mixed>, list<string>}>
     */
    public static function refusedPublications(): iterable
    {
        yield 'empty summary' => ['', ContractFixtures::validDocument('minimal-en'), ['empty_value']];
    }

    public function testSponsoredArticlePublishesWithoutVerificationDate(): void
    {
        $anchor = $this->publisher->createAnchor('modular-sponsored-no-date', 'en', $this->author, 'Working title');
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [
                ['id' => 'bar', 'type' => 'cta', 'props' => ['label' => 'Go', 'url' => 'https://partner.example.test/x', 'variant' => 'primary', 'sponsored' => true]],
            ],
        ];
        $this->publisher->saveDraft($anchor, 'en', new ArticleDraft('Title', 'Summary', null, null, $document));

        $published = $this->publisher->publish($anchor, 'en');

        self::assertArrayNotHasKey('verified_at', $published->getDocument());
        self::assertNotNull($published->getPublishedAt());
    }

    public function testUnknownBlockTypeCannotBeSaved(): void
    {
        $anchor = $this->publisher->createAnchor('modular-unknown', 'en', $this->author, 'Working title');
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [['id' => 'x', 'type' => 'customScript', 'props' => ['src' => 'https://evil.example.test/x.js']]],
        ];

        $this->expectException(ArticleValidationException::class);
        $this->publisher->saveDraft($anchor, 'en', new ArticleDraft('Title', 'Summary', null, null, $document));
    }

    public function testRichTextIsSanitizedBeforeItIsStored(): void
    {
        $anchor = $this->publisher->createAnchor('modular-sanitize', 'en', $this->author, 'Working title');
        $document = [
            'schema_version' => 1,
            'locale' => 'en',
            'blocks' => [['id' => 'body', 'type' => 'richText', 'props' => ['html' => '<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">l</a>']]],
        ];

        $saved = $this->publisher->saveDraft($anchor, 'en', new ArticleDraft('Title', 'Summary', null, null, $document));

        self::assertSame('<p>Hi</p>l', $saved->getDocument()['blocks'][0]['props']['html']);
    }

    public function testLocaleTextIsSearchable(): void
    {
        $anchor = $this->publisher->createAnchor('modular-search', 'en', $this->author, 'Working title');
        $locale = $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'T'));

        self::assertStringContainsString('English-only article', $locale->getSearchText());
    }

    public function testPublishWithoutDraftAndLegacyPostsAreRefused(): void
    {
        $anchor = $this->publisher->createAnchor('modular-nodraft', 'en', $this->author, 'Working title');

        $legacy = new Post();
        $legacy->setTitle('Legacy')->setSlug('legacy-one')->setContent('c')->setDescription('d')->setAuthor($this->author);
        $this->em->persist($legacy);
        $this->em->flush();

        try {
            $this->publisher->publish($anchor, 'en');
            self::fail('Nothing to publish.');
        } catch (ArticleStateException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(ArticleStateException::class);
        $this->publisher->saveDraft($legacy, 'en', $this->draft('en', 'x'));
    }

    public function testSlugCollisionWithAnyExistingPostIsAConflict(): void
    {
        $legacy = new Post();
        $legacy->setTitle('Legacy')->setSlug('taken-slug')->setContent('c')->setDescription('d')->setAuthor($this->author);
        $this->em->persist($legacy);
        $this->em->flush();

        try {
            $this->publisher->createAnchor('taken-slug', 'en', $this->author, 'Working title');
            self::fail('Expected a slug conflict.');
        } catch (ArticleConflictException $e) {
            self::assertSame(ArticleConflictException::CODE_SLUG_TAKEN, $e->reason);
        }

        $this->expectException(ArticleValidationException::class);
        $this->publisher->createAnchor('Not A Slug!', 'en', $this->author, 'Working title');
    }

    public function testSlugWithATrailingNewlineIsRejected(): void
    {
        $this->expectException(ArticleValidationException::class);
        $this->publisher->createAnchor("modular-newline\n", 'en', $this->author, 'Working title');
    }

    public function testVerificationWarningsFlagDivergingDates(): void
    {
        $anchor = $this->publisher->createAnchor('modular-dates', 'ru', $this->author, 'Working title');

        foreach (['ru' => '2026-10-29', 'en' => '2026-10-01'] as $locale => $date) {
            $document = ContractFixtures::validDocument('minimal-en');
            $document['locale'] = $locale;
            $document['verified_at'] = $date;
            $this->publisher->saveDraft($anchor, $locale, new ArticleDraft('T '.$locale, 'S', null, null, $document));
            $this->publisher->publish($anchor, $locale);
        }

        self::assertCount(1, $this->publisher->verificationWarnings($anchor));
    }

    public function testDraftAnchorIsNotPublicOnTheSymfonySiteUntilPublished(): void
    {
        $anchor = $this->publisher->createAnchor('modular-site', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Visible after publish'));

        $this->client->request(Request::METHOD_GET, '/post/modular-site');
        $this->assertResponseStatusCodeSame(404);

        $this->publisher->publish($anchor, 'en');

        $this->client->request(Request::METHOD_GET, '/post/modular-site');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Visible after publish', (string) $this->client->getResponse()->getContent());
    }

    public function testLegacyAdminFormRefusesModularAnchors(): void
    {
        $anchor = $this->publisher->createAnchor('modular-guard', 'en', $this->author, 'Working title');
        $this->publisher->saveDraft($anchor, 'en', $this->draft('en', 'Guarded'));
        $this->publisher->publish($anchor, 'en');
        $id = (int) $anchor->getId();

        $this->client->loginUser($this->author);

        $this->client->request(Request::METHOD_GET, '/admin/posts/'.$id.'/edit');
        $this->assertResponseStatusCodeSame(409);

        $this->client->request(Request::METHOD_POST, '/admin/posts/'.$id.'/update', [
            'title' => 'Overwritten',
            'content' => 'Overwritten',
            'description' => 'Overwritten',
            'date' => '2024-01-01',
            'status' => '1',
            'is_featured' => '0',
        ]);
        $this->assertResponseStatusCodeSame(409);

        $this->client->request(Request::METHOD_POST, '/admin/posts/'.$id.'/delete');
        $this->assertResponseStatusCodeSame(409);

        $this->em->clear();
        $stored = $this->em->find(Post::class, $id);
        self::assertInstanceOf(Post::class, $stored);
        self::assertSame('Guarded', $stored->getTitle());
        self::assertFalse($stored->getStatus());
    }

    public function testLegacyPostsAreUnaffected(): void
    {
        $legacy = new Post();
        $legacy->setTitle('Legacy')->setSlug('legacy-two')->setContent('c')->setDescription('d')->setAuthor($this->author);
        $this->em->persist($legacy);
        $this->em->flush();
        $this->em->clear();

        $stored = $this->em->find(Post::class, (int) $legacy->getId());

        self::assertInstanceOf(Post::class, $stored);
        self::assertFalse($stored->isModular());
        self::assertSame(Post::ROUTE_LEGACY, $stored->getRouteMode());
        self::assertNull($stored->getOriginalLocale());
        self::assertNull($stored->getPrimaryLocale());
        self::assertFalse($stored->getStatus(), 'legacy default (published) is unchanged');

        $this->client->loginUser($this->author);
        $this->client->request(Request::METHOD_GET, '/admin/posts/'.$legacy->getId().'/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testClassifiedLegacyPostCanPublishTranslationWithoutReplacingItsOriginal(): void
    {
        $legacy = new Post();
        $legacy->setTitle('Original title')->setSlug('translated-legacy')->setContent('<p>Original body</p>')
            ->setDescription('Original summary')->setAuthor($this->author)->setOriginalLocale('ru');
        $this->em->persist($legacy);
        $this->em->flush();

        $this->publisher->saveDraft($legacy, 'en', $this->draft('en', 'Translated title'), $this->author);
        $this->publisher->publish($legacy, 'en');

        self::assertTrue($this->localeRow($legacy, 'en')->isPublished());
        self::assertSame('Original title', $legacy->getTitle());
        self::assertSame('<p>Original body</p>', $legacy->getContent());
        self::assertSame(Post::ROUTE_LEGACY, $legacy->getRouteMode());
        self::assertFalse($legacy->getStatus());

        $this->publisher->unpublish($legacy, 'en');
        self::assertFalse($this->localeRow($legacy, 'en')->isPublished());
        self::assertFalse($legacy->getStatus(), 'the legacy original remains public');
        self::assertSame('Original title', $legacy->getTitle());
    }

    public function testTranslationCannotBePublishedWhileLegacyOriginalIsDraft(): void
    {
        $legacy = new Post();
        $legacy->setTitle('Draft original')->setSlug('legacy-draft-translation')->setContent('Original')
            ->setAuthor($this->author)->setOriginalLocale('ru')->setStatus(true);
        $this->em->persist($legacy);
        $this->em->flush();

        $this->publisher->saveDraft($legacy, 'en', $this->draft('en', 'Translation'));
        try {
            $this->publisher->publish($legacy, 'en');
            self::fail('The translation of a hidden legacy original must not become publishable.');
        } catch (ArticleStateException) {
            self::assertTrue($legacy->getStatus());
            self::assertSame(1, (int) $this->em->getConnection()->fetchOne(
                'SELECT status FROM posts WHERE id = ?',
                [(int) $legacy->getId()],
            ));
            self::assertNull($this->em->getConnection()->fetchOne(
                'SELECT published_at FROM post_locales WHERE post_id = ? AND locale = ?',
                [(int) $legacy->getId(), 'en'],
            ));
        }
    }

    public function testClassifiedLegacyTranslationIsRefusedWhenItsSlugIsAmbiguous(): void
    {
        $this->dropUniqueSlugIndexForLegacyDuplicates();

        $translation = $this->classifiedLegacyPost('ambiguous-legacy-slug');
        $this->classifiedLegacyPost('ambiguous-legacy-slug');

        try {
            $this->publisher->saveDraft($translation, 'en', $this->draft('en', 'Translated title'), $this->author);
            self::fail('A translation whose slug identifies two posts must not be stored.');
        } catch (ArticleConflictException $e) {
            self::assertSame(ArticleConflictException::CODE_SLUG_AMBIGUOUS, $e->reason);
        }

        self::assertNull($this->em->find(PostLocale::class, ['postId' => (int) $translation->getId(), 'locale' => 'en']));
    }

    public function testClassifiedLegacyTranslationRechecksAmbiguousSlugBeforePublishing(): void
    {
        $this->dropUniqueSlugIndexForLegacyDuplicates();

        $translation = $this->classifiedLegacyPost('late-ambiguous-slug');
        $this->publisher->saveDraft($translation, 'en', $this->draft('en', 'Translated title'), $this->author);
        $this->classifiedLegacyPost('late-ambiguous-slug');

        try {
            $this->publisher->publish($translation, 'en');
            self::fail('A duplicate slug that appears after drafting must block publication.');
        } catch (ArticleConflictException $e) {
            self::assertSame(ArticleConflictException::CODE_SLUG_AMBIGUOUS, $e->reason);
        }

        self::assertFalse($translation->getStatus());
        // The failed transaction closes the EntityManager, so the state is read straight from the database.
        self::assertNull($this->em->getConnection()->fetchOne(
            'SELECT published_at FROM post_locales WHERE post_id = ? AND locale = ?',
            [(int) $translation->getId(), 'en'],
        ));
    }

    /**
     * Legacy slugs are not guaranteed unique in production. On PostgreSQL only, this isolated test
     * database drops the SchemaTool unique index on posts.slug so two legacy posts can share a slug.
     */
    private function dropUniqueSlugIndexForLegacyDuplicates(): void
    {
        $connection = $this->em->getConnection();
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('Duplicate legacy slugs are only exercised on PostgreSQL.');
        }

        foreach ($connection->createSchemaManager()->listTableIndexes('posts') as $index) {
            if ($index->isUnique() && !$index->isPrimary() && ['slug'] === $index->getColumns()) {
                $connection->executeStatement(\sprintf('DROP INDEX %s', $connection->quoteSingleIdentifier($index->getName())));
            }
        }
    }

    private function classifiedLegacyPost(string $slug): Post
    {
        $legacy = new Post();
        $legacy->setTitle('Original title')->setSlug($slug)->setContent('<p>Original body</p>')
            ->setDescription('Original summary')->setAuthor($this->author)->setOriginalLocale('ru');
        $this->em->persist($legacy);
        $this->em->flush();

        return $legacy;
    }

    private function draft(string $locale, string $title, string $summary = 'A short summary.'): ArticleDraft
    {
        $document = 'ru' === $locale
            ? ['schema_version' => 1, 'locale' => 'ru', 'blocks' => [['id' => 'body', 'type' => 'richText', 'props' => ['html' => '<p>Текст статьи</p>']]]]
            : ContractFixtures::validDocument('minimal-en');

        return new ArticleDraft($title, $summary, null, null, $document);
    }

    private function localeRow(Post $post, string $locale): PostLocale
    {
        $row = $this->em->find(PostLocale::class, ['postId' => (int) $post->getId(), 'locale' => $locale]);
        self::assertInstanceOf(PostLocale::class, $row);

        $this->em->refresh($row);

        return $row;
    }

    private static function postRepository(): PostRepository
    {
        $repository = self::getContainer()->get(PostRepository::class);
        \assert($repository instanceof PostRepository);

        return $repository;
    }
}
