<?php

declare(strict_types=1);

namespace App\Article;

use App\Article\Exception\ArticleConflictException;
use App\Article\Exception\ArticleStateException;
use App\Article\Exception\ArticleValidationException;
use App\Article\Validation\ArticleDocumentValidator;
use App\Article\Validation\ValidationIssue;
use App\Entity\Post;
use App\Entity\PostLocale;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/**
 * Draft / publish lifecycle of modular articles and legacy translations.
 *
 * There is no revision history: a locale is one row holding its current content. Invariants:
 *  - the `posts` anchor of a modular article is created as a DRAFT (status = true) and only becomes
 *    visible (status = false) in the same transaction that publishes its first locale;
 *  - saving content validates it in draft mode; if the locale is already published the content is
 *    live immediately, so it is validated in full publication mode and the anchor card is re-synced;
 *  - publication validates the stored content in publication mode and sets `published_at`;
 *  - publication of different locales is serialized on the posts row before deriving visibility.
 */
final readonly class ArticlePublisher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleDocumentNormalizer $normalizer,
        private ArticleDocumentValidator $validator,
        private SearchTextExtractor $searchTextExtractor,
    ) {
    }

    /**
     * Creates the hidden anchor row. Nothing is public until publish() succeeds for a locale.
     */
    public function createAnchor(string $slug, string $primaryLocale, User $author, string $workingTitle): Post
    {
        $issues = [];

        if (!\in_array($primaryLocale, ArticleContract::LOCALES, true)) {
            $issues[] = new ValidationIssue('/primary_locale', 'unsupported_locale', 'primary_locale must be one of: '.implode(', ', ArticleContract::LOCALES).'.');
        }

        if (1 !== preg_match(ArticleContract::SLUG_PATTERN, $slug) || \strlen($slug) > 255) {
            $issues[] = new ValidationIssue('/slug', 'invalid_slug', 'Slug must be lower-case latin words and digits separated by single hyphens (max 255 chars).');
        }

        if ('' === trim($workingTitle) || mb_strlen($workingTitle) > ArticleContract::MAX_TITLE_LENGTH) {
            $issues[] = new ValidationIssue('/title', 'invalid_title', 'A working title of 1-255 characters is required.');
        }

        if ([] !== $issues) {
            throw new ArticleValidationException($issues);
        }

        if (null !== $this->entityManager->getRepository(Post::class)->findOneBy(['slug' => $slug])) {
            throw new ArticleConflictException(ArticleConflictException::CODE_SLUG_TAKEN, \sprintf('Slug "%s" is already used by another post.', $slug));
        }

        $post = new Post();
        $post->setTitle($workingTitle)
            ->setSlug($slug)
            ->setContent('')
            ->setDescription('')
            ->setIsFeatured(false)
            ->setAuthor($author)
            ->markAsModularDraft($primaryLocale);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    /**
     * Sanitizes, validates and stores the content of one locale (creating the row on first save).
     * A locale that is already published stays published, so its new content is validated for
     * publication and the anchor card is refreshed in the same transaction.
     */
    public function saveDraft(Post $anchor, string $locale, ArticleDraft $draft, ?User $actor = null): PostLocale
    {
        $postId = $this->assertModular($anchor, $locale);
        $document = $this->normalizer->normalize($draft->document);
        $searchText = $this->searchTextExtractor->extract($document);

        return $this->entityManager->wrapInTransaction(function () use ($anchor, $postId, $locale, $draft, $document, $searchText, $actor): PostLocale {
            // Serialize saves with publication transitions, then validate the latest locale state.
            $this->lockAnchorForPublication($anchor);
            $this->assertModular($anchor, $locale);
            $this->assertUnambiguousLegacySlug($anchor);

            $row = $this->findLocale($postId, $locale);
            if (null !== $row) {
                $this->entityManager->refresh($row);
            }
            $live = null !== $row && $row->isPublished();

            $issues = [
                ...self::metadataIssues($draft->title, $draft->summary, $draft->metaTitle, $draft->metaDescription, $live),
                ...$this->validator->validate($document, $live, $locale),
            ];

            if ([] !== $issues) {
                throw new ArticleValidationException($issues);
            }

            if (null === $row) {
                $row = new PostLocale(
                    $postId,
                    $locale,
                    $draft->title,
                    $draft->summary,
                    $draft->metaTitle,
                    $draft->metaDescription,
                    $searchText,
                    $document,
                    ArticleContract::SCHEMA_VERSION,
                    $actor?->getId(),
                );
            } else {
                $row->replaceContent($draft->title, $draft->summary, $draft->metaTitle, $draft->metaDescription, $searchText, $document, ArticleContract::SCHEMA_VERSION);
            }

            $this->entityManager->persist($row);
            $this->entityManager->flush();

            if ($live && $anchor->isModular()) {
                $this->syncAnchor($anchor);
                $this->entityManager->flush();
            }

            return $row;
        });
    }

    /**
     * Makes the stored content of a locale public, after full publication validation.
     */
    public function publish(Post $anchor, string $locale): PostLocale
    {
        $postId = $this->assertModular($anchor, $locale);

        return $this->entityManager->wrapInTransaction(function () use ($anchor, $postId, $locale): PostLocale {
            $this->lockAnchorForPublication($anchor);
            $this->assertModular($anchor, $locale);
            if (!$anchor->isModular() && $anchor->getStatus()) {
                throw new ArticleStateException('The legacy original is no longer published.');
            }
            $this->assertUnambiguousLegacySlug($anchor);

            $row = $this->findLocale($postId, $locale)
                ?? throw new ArticleStateException(\sprintf('Locale "%s" has no content to publish.', $locale));
            $this->entityManager->refresh($row);

            $issues = [
                ...self::metadataIssues($row->getTitle(), $row->getSummary(), $row->getMetaTitle(), $row->getMetaDescription(), true),
                ...$this->validator->validate($row->getDocument(), true, $locale),
            ];

            if ([] !== $issues) {
                throw new ArticleValidationException($issues);
            }

            $row->publish(new \DateTimeImmutable());
            $this->entityManager->flush();

            if ($anchor->isModular()) {
                $this->syncAnchor($anchor);
                $this->entityManager->flush();
            }

            return $row;
        });
    }

    /**
     * Withdraws one locale. When the last published locale goes, the anchor is hidden again.
     */
    public function unpublish(Post $anchor, string $locale): PostLocale
    {
        $postId = $this->assertModular($anchor, $locale);

        $row = $this->findLocale($postId, $locale);

        if (null === $row || !$row->isPublished()) {
            throw new ArticleStateException(\sprintf('Locale "%s" is not published.', $locale));
        }

        $this->entityManager->wrapInTransaction(function () use ($row, $anchor): void {
            $this->lockAnchorForPublication($anchor);
            $this->entityManager->refresh($row);
            if (!$row->isPublished()) {
                throw new ArticleStateException(\sprintf('Locale "%s" is not published.', $row->getLocale()));
            }
            $row->unpublish();
            $this->entityManager->flush();

            if ($anchor->isModular()) {
                $this->syncAnchor($anchor);
                $this->entityManager->flush();
            }
        });

        return $row;
    }

    /**
     * Facts live per locale, so locales can drift apart. Returns human-readable warnings when the
     * published locales disagree about their verification date.
     *
     * @return list<string>
     */
    public function verificationWarnings(Post $anchor): array
    {
        $postId = $anchor->getId();

        if (null === $postId) {
            return [];
        }

        $dates = [];

        foreach ($this->publishedLocales($postId) as $row) {
            $dates[$row->getLocale()] = $row->getDocument()['verified_at'] ?? null;
        }

        if (\count($dates) < 2 || 1 === \count(array_unique(array_map(static fn (mixed $date): string => \is_string($date) ? $date : '', $dates)))) {
            return [];
        }

        $parts = [];

        foreach ($dates as $localeName => $date) {
            $parts[] = \sprintf('%s: %s', $localeName, \is_string($date) ? $date : 'not set');
        }

        return [\sprintf('Published locales have different verified_at dates (%s); review the facts in each locale.', implode(', ', $parts))];
    }

    private function assertModular(Post $anchor, string $locale): int
    {
        if (!\in_array($locale, ArticleContract::LOCALES, true)) {
            throw new ArticleValidationException([new ValidationIssue('/locale', 'unsupported_locale', 'locale must be one of: '.implode(', ', ArticleContract::LOCALES).'.')]);
        }

        if (!$anchor->isModular() && (null === $anchor->getOriginalLocale() || $locale === $anchor->getOriginalLocale())) {
            throw new ArticleStateException('Legacy posts need a classified original locale and can only add a different translation.');
        }

        return $anchor->getId() ?? throw new ArticleStateException('The anchor post has not been persisted yet.');
    }

    /**
     * Legacy slugs are not guaranteed unique. A classified legacy translation is only addressable when its
     * slug identifies exactly one post, so a shared slug is refused rather than guessing the canonical one.
     */
    private function assertUnambiguousLegacySlug(Post $anchor): void
    {
        if ($anchor->isModular()) {
            return;
        }

        if ($this->entityManager->getRepository(Post::class)->count(['slug' => $anchor->getSlug()]) > 1) {
            throw new ArticleConflictException(ArticleConflictException::CODE_SLUG_AMBIGUOUS, \sprintf('Slug "%s" identifies more than one post; the canonical translation cannot be resolved.', $anchor->getSlug()));
        }
    }

    private function findLocale(int $postId, string $locale): ?PostLocale
    {
        return $this->entityManager->find(PostLocale::class, ['postId' => $postId, 'locale' => $locale]);
    }

    /**
     * @return list<PostLocale>
     */
    private function publishedLocales(int $postId): array
    {
        /** @var list<PostLocale> $rows */
        $rows = $this->entityManager
            ->createQuery(\sprintf('SELECT l FROM %s l WHERE l.postId = :post AND l.publishedAt IS NOT NULL ORDER BY l.publishedAt ASC, l.locale ASC', PostLocale::class))
            ->setParameter('post', $postId)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        return $rows;
    }

    private function lockAnchorForPublication(Post $anchor): void
    {
        if ($this->entityManager->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->entityManager->lock($anchor, LockMode::PESSIMISTIC_WRITE);
        }
        $this->entityManager->refresh($anchor);
    }

    /**
     * Copies sanitized plain-text card fields onto the anchor and derives its visibility from the
     * published locales: any published locale => visible (status = false), none => hidden (status = true).
     */
    private function syncAnchor(Post $anchor): void
    {
        $published = $this->publishedLocales((int) $anchor->getId());

        if ([] === $published) {
            $anchor->setStatus(true);
            $anchor->setUpdatedAt(new \DateTime());

            return;
        }

        $card = $published[0];

        foreach ($published as $candidate) {
            if ($candidate->getLocale() === $anchor->getPrimaryLocale()) {
                $card = $candidate;

                break;
            }
        }

        // posts.description is rendered as raw HTML by the frontend, so only escaped plain text goes in.
        $escapedSummary = htmlspecialchars($card->getSummary(), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');

        $anchor->setTitle($card->getTitle());
        $anchor->setDescription($escapedSummary);
        $anchor->setContent('<p>'.$escapedSummary.'</p>');
        $anchor->setStatus(false);
        $anchor->setUpdatedAt(new \DateTime());
    }

    /**
     * @return list<ValidationIssue>
     */
    private static function metadataIssues(string $title, string $summary, ?string $metaTitle, ?string $metaDescription, bool $forPublication): array
    {
        $issues = [];
        $limits = [
            '/title' => [$title, ArticleContract::MAX_TITLE_LENGTH, true],
            '/summary' => [$summary, ArticleContract::MAX_SUMMARY_LENGTH, true],
            '/meta_title' => [$metaTitle, ArticleContract::MAX_META_TITLE_LENGTH, false],
            '/meta_description' => [$metaDescription, ArticleContract::MAX_META_DESCRIPTION_LENGTH, false],
        ];

        foreach ($limits as $path => [$value, $max, $required]) {
            if (null === $value) {
                continue;
            }

            if (1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                $issues[] = new ValidationIssue($path, 'control_characters', 'Control characters are not allowed.');
            } elseif (mb_strlen($value) > $max) {
                $issues[] = new ValidationIssue($path, 'too_long', \sprintf('At most %d characters are allowed.', $max));
            } elseif ($forPublication && $required && '' === trim($value)) {
                $issues[] = new ValidationIssue($path, 'empty_value', 'Must not be empty.');
            }
        }

        return $issues;
    }
}
