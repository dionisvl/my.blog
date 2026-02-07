<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Manager\PostViewManager;
use App\Service\HomePageQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly HomePageQuery $homePageQuery,
        private readonly PostViewManager $postViewManager,
        #[Autowire('%app.secure_cookies%')]
        private readonly bool $secureCookies,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = 10;
        $data = $this->homePageQuery->getIndexViewData();

        if ($this->isGranted('ROLE_ADMIN')) {
            $paginated = $this->homePageQuery->findLatestPostsPaginated($page, $perPage);
        } else {
            $paginated = $this->homePageQuery->findPublishedLatestPaginated($page, $perPage);
        }

        $data['posts'] = $paginated['items'];
        $data['pagination'] = $this->buildPagination($page, $perPage, $paginated['total']);

        return $this->render('home/index.html.twig', $data);
    }

    #[Route('/post/{slug}', name: 'post_show')]
    public function show(string $slug, Request $request): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $post = $this->homePageQuery->findPostBySlug($slug);
        } else {
            $post = $this->homePageQuery->findPostForShow($slug);
        }

        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Post not found');
        }

        $cookieName = 'viewedPostToday' . $post->getId();
        $isViewed = $request->cookies->has($cookieName);

        if (!$isViewed) {
            $this->postViewManager->increment($post);
        }

        $response = $this->render('home/show.html.twig', $this->homePageQuery->getShowViewData($post));

        if (!$isViewed) {
            $cookie = Cookie::create($cookieName)
                ->withValue(new \DateTime()->format('Y-m-d H:i:s'))
                ->withExpires(time() + 60 * 60 * 24)
                ->withPath('/')
                ->withSecure($this->secureCookies)
                ->withHttpOnly(true);

            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    #[Route('/contacts/', name: 'contacts')]
    public function contacts(): Response
    {
        return $this->render('home/contacts.html.twig');
    }

    #[Route('/search', name: 'search', methods: ['GET', 'POST'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');

        if (null === $query) {
            $query = $request->request->get('q', '');
        }

        $query = trim((string)$query);
        $posts = [];

        if ('' !== $query) {
            $posts = $this->homePageQuery->searchPosts($query, 20, $this->isGranted('ROLE_ADMIN'));
        }

        $data = $this->homePageQuery->getIndexViewData();
        $data['details'] = $posts;
        $data['query'] = $query;

        return $this->render('home/search.html.twig', $data);
    }

    #[Route('/tag/{slug}', name: 'tag_show')]
    public function tag(string $slug, Request $request): Response
    {
        $tag = $this->homePageQuery->findTagBySlug($slug);

        if (!$tag instanceof Tag) {
            throw $this->createNotFoundException('Tag not found');
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = 10;
        $paginated = $this->homePageQuery->findPublishedByTagSlugPaginated($slug, $page, $perPage);

        $data = $this->homePageQuery->getIndexViewData();
        $data['posts'] = $paginated['items'];
        $data['tag'] = $tag;
        $data['pagination'] = $this->buildPagination($page, $perPage, $paginated['total']);

        return $this->render('home/list.html.twig', $data);
    }

    #[Route('/category/{slug}', name: 'category_show')]
    public function category(string $slug, Request $request): Response
    {
        $category = $this->homePageQuery->findCategoryBySlug($slug);

        if (!$category instanceof Category) {
            throw $this->createNotFoundException('Category not found');
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = 10;
        $paginated = $this->homePageQuery->findPublishedByCategorySlugPaginated($slug, $page, $perPage);

        $data = $this->homePageQuery->getIndexViewData();
        $data['posts'] = $paginated['items'];
        $data['category'] = $category;
        $data['pagination'] = $this->buildPagination($page, $perPage, $paginated['total']);

        return $this->render('home/list.html.twig', $data);
    }

    /**
     * @return array{currentPage: int, totalPages: int, hasPrev: bool, hasNext: bool, prevPage: int, nextPage: int}
     */
    private function buildPagination(int $page, int $perPage, int $total): array
    {
        $totalPages = max(1, (int)ceil($total / $perPage));
        $currentPage = min($page, $totalPages);

        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'hasPrev' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'prevPage' => max(1, $currentPage - 1),
            'nextPage' => min($totalPages, $currentPage + 1),
        ];
    }
}
