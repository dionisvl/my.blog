<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Article\ArticleContract;
use App\Article\ArticleDraft;
use App\Article\ArticlePublisher;
use App\Article\Exception\ArticleConflictException;
use App\Article\Exception\ArticleStateException;
use App\Article\Exception\ArticleValidationException;
use App\Entity\Post;
use App\Entity\PostLocale;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin builder for modular articles. All content rules live in ArticlePublisher; this controller
 * only authenticates, checks CSRF and translates the service's exceptions for the browser.
 */
#[IsGranted('ROLE_ADMIN')]
final class ModularArticleController extends AbstractController
{
    private const string CSRF_ID = 'modular_article';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticlePublisher $publisher,
    ) {
    }

    #[Route('/admin/articles/', name: 'admin_articles_index', methods: ['GET'])]
    public function index(): Response
    {
        $anchors = $this->entityManager->getRepository(Post::class)->findBy(
            ['routeMode' => Post::ROUTE_NEUTRAL],
            ['createdAt' => 'DESC'],
        );

        $rows = [];

        foreach ($anchors as $anchor) {
            $locales = [];

            foreach ($this->localesOf($anchor) as $locale) {
                $locales[$locale->getLocale()] = $locale;
            }

            $rows[] = ['post' => $anchor, 'locales' => $locales];
        }

        return $this->render('admin/articles/index.html.twig', [
            'rows' => $rows,
            'locales' => ArticleContract::LOCALES,
            'csrf' => $this->csrf(),
        ]);
    }

    #[Route('/admin/articles/new', name: 'admin_articles_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $errors = [];
        $form = ['slug' => '', 'primary_locale' => ArticleContract::LOCALE_EN, 'title' => ''];

        if ($request->isMethod(Request::METHOD_POST)) {
            $this->requireCsrf((string) $request->request->get('_token'));
            $form = [
                'slug' => trim((string) $request->request->get('slug')),
                'primary_locale' => (string) $request->request->get('primary_locale'),
                'title' => trim((string) $request->request->get('title')),
            ];
            $author = $this->getUser();

            if (!$author instanceof User) {
                throw $this->createAccessDeniedException();
            }

            try {
                $anchor = $this->publisher->createAnchor($form['slug'], $form['primary_locale'], $author, $form['title']);
            } catch (ArticleValidationException $e) {
                foreach ($e->issues as $issue) {
                    $errors[] = $issue->message;
                }
            } catch (ArticleConflictException $e) {
                $errors[] = $e->getMessage();
            }

            if (isset($anchor)) {
                return $this->redirectToRoute('admin_articles_edit', ['id' => $anchor->getId(), 'locale' => $form['primary_locale']]);
            }
        }

        return $this->render('admin/articles/new.html.twig', [
            'form' => $form,
            'errors' => $errors,
            'locales' => ArticleContract::LOCALES,
            'csrf' => $this->csrf(),
        ], new Response(status: [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/admin/articles/{id}/{locale}', name: 'admin_articles_edit', requirements: ['id' => '\d+', 'locale' => 'ru|en'], methods: ['GET'])]
    public function edit(int $id, string $locale): Response
    {
        $anchor = $this->anchor($id);
        $row = $this->localeRow($anchor, $locale);

        return $this->render('admin/articles/edit.html.twig', [
            'post' => $anchor,
            'locale' => $locale,
            'locales' => ArticleContract::LOCALES,
            'row' => $row,
            'initial' => [
                'title' => $row?->getTitle() ?? ($anchor->getPrimaryLocale() === $locale ? $anchor->getTitle() : ''),
                'summary' => $row?->getSummary() ?? '',
                'meta_title' => $row?->getMetaTitle() ?? '',
                'meta_description' => $row?->getMetaDescription() ?? '',
                'document' => $row?->getDocument() ?? [
                    'schema_version' => ArticleContract::SCHEMA_VERSION,
                    'locale' => $locale,
                    'blocks' => [],
                ],
            ],
            'warnings' => $this->publisher->verificationWarnings($anchor),
            'csrf' => $this->csrf(),
        ]);
    }

    #[Route('/admin/articles/{id}/{locale}/save', name: 'admin_articles_save', requirements: ['id' => '\d+', 'locale' => 'ru|en'], methods: ['POST'])]
    public function save(int $id, string $locale, Request $request): JsonResponse
    {
        $this->requireCsrf((string) $request->headers->get('X-CSRF-Token'));
        $anchor = $this->anchor($id);

        try {
            $payload = json_decode($request->getContent(), true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['ok' => false, 'error' => 'Malformed JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $document = \is_array($payload) ? ($payload['document'] ?? null) : null;

        if (!\is_array($payload) || !\is_array($document)
            || !\is_string($payload['title'] ?? null) || !\is_string($payload['summary'] ?? null)) {
            return new JsonResponse(['ok' => false, 'error' => 'title, summary and document are required.'], Response::HTTP_BAD_REQUEST);
        }

        $draft = new ArticleDraft(
            $payload['title'],
            $payload['summary'],
            self::optionalString($payload['meta_title'] ?? null),
            self::optionalString($payload['meta_description'] ?? null),
            $document,
        );
        $actor = $this->getUser();

        return $this->respond(function () use ($anchor, $locale, $draft, $actor): array {
            $row = $this->publisher->saveDraft($anchor, $locale, $draft, $actor instanceof User ? $actor : null);

            return ['published' => $row->isPublished()];
        });
    }

    #[Route('/admin/articles/{id}/{locale}/publish', name: 'admin_articles_publish', requirements: ['id' => '\d+', 'locale' => 'ru|en'], methods: ['POST'])]
    public function publish(int $id, string $locale, Request $request): Response
    {
        return $this->transition($id, $locale, $request, 'publish');
    }

    #[Route('/admin/articles/{id}/{locale}/unpublish', name: 'admin_articles_unpublish', requirements: ['id' => '\d+', 'locale' => 'ru|en'], methods: ['POST'])]
    public function unpublish(int $id, string $locale, Request $request): Response
    {
        return $this->transition($id, $locale, $request, 'unpublish');
    }

    private function transition(int $id, string $locale, Request $request, string $action): Response
    {
        $this->requireCsrf((string) $request->request->get('_token'));
        $anchor = $this->anchor($id);

        try {
            if ('publish' === $action) {
                $this->publisher->publish($anchor, $locale);
                $this->addFlash('success', \sprintf('Locale "%s" is published.', $locale));
            } else {
                $this->publisher->unpublish($anchor, $locale);
                $this->addFlash('success', \sprintf('Locale "%s" is unpublished.', $locale));
            }
        } catch (ArticleValidationException $e) {
            foreach ($e->issues as $issue) {
                $this->addFlash('danger', $issue->path.': '.$issue->message);
            }
        } catch (ArticleStateException|ArticleConflictException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_articles_edit', ['id' => $id, 'locale' => $locale]);
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function respond(callable $action): JsonResponse
    {
        try {
            return new JsonResponse(['ok' => true] + $action());
        } catch (ArticleValidationException $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'The article has validation problems.',
                'issues' => array_map(static fn ($issue): array => $issue->toArray(), $e->issues),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ArticleStateException|ArticleConflictException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    private function anchor(int $id): Post
    {
        $post = $this->entityManager->find(Post::class, $id);

        if (!$post instanceof Post || !$post->isModular()) {
            throw $this->createNotFoundException('Modular article not found');
        }

        return $post;
    }

    private function localeRow(Post $anchor, string $locale): ?PostLocale
    {
        return $this->entityManager->find(PostLocale::class, ['postId' => $anchor->getId(), 'locale' => $locale]);
    }

    /**
     * @return list<PostLocale>
     */
    private function localesOf(Post $anchor): array
    {
        /** @var list<PostLocale> $rows */
        $rows = $this->entityManager->getRepository(PostLocale::class)->findBy(['postId' => $anchor->getId()]);

        return $rows;
    }

    private function csrf(): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken(self::CSRF_ID)->getValue();
    }

    private function requireCsrf(string $token): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private static function optionalString(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }
}
