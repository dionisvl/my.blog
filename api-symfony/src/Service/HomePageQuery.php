<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;

final readonly class HomePageQuery
{
    public function __construct(
        private PostRepository $posts,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private AphorismQuery $aphorisms,
    ) {
    }

    /**
     * @return array{posts: list<Post>, categories: list<array{0: Category, posts_count: int|string}>, featuredPosts: list<Post>, recentPosts: list<Post>}
     */
    public function getIndexViewData(
        int $postLimit = 20,
        int $featuredLimit = 3,
        int $recentLimit = 4,
    ): array {
        return [
            'posts' => $this->posts->findPublishedLatest($postLimit),
            'categories' => $this->categories->findWithPostCounts(),
            'featuredPosts' => $this->posts->findFeatured($featuredLimit),
            'recentPosts' => $this->posts->findRecentPublished($recentLimit),
        ];
    }

    /**
     * @return list<Post>
     */
    public function findLatestPosts(int $limit = 20): array
    {
        return $this->posts->findLatest($limit);
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findLatestPostsPaginated(int $page, int $perPage): array
    {
        return $this->posts->findLatestPaginated($page, $perPage);
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedLatestPaginated(int $page, int $perPage): array
    {
        return $this->posts->findPublishedLatestPaginated($page, $perPage);
    }

    public function findPostForShow(string $slug): ?Post
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    public function findPostBySlug(string $slug): ?Post
    {
        return $this->posts->findOneBy(['slug' => $slug]);
    }

    public function findCategoryBySlug(string $slug): ?Category
    {
        return $this->categories->findOneBy(['slug' => $slug]);
    }

    public function findTagBySlug(string $slug): ?Tag
    {
        return $this->tags->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Post>
     */
    public function findPublishedByCategorySlug(string $slug): array
    {
        return $this->posts->findPublishedByCategorySlug($slug);
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedByCategorySlugPaginated(string $slug, int $page, int $perPage): array
    {
        return $this->posts->findPublishedByCategorySlugPaginated($slug, $page, $perPage);
    }

    /**
     * @return list<Post>
     */
    public function findPublishedByTagSlug(string $slug): array
    {
        return $this->posts->findPublishedByTagSlug($slug);
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedByTagSlugPaginated(string $slug, int $page, int $perPage): array
    {
        return $this->posts->findPublishedByTagSlugPaginated($slug, $page, $perPage);
    }

    /**
     * @return list<Post>
     */
    public function searchPosts(string $query, int $limit, bool $includeDrafts = false): array
    {
        return $this->posts->searchPosts($query, $limit, $includeDrafts);
    }

    /**
     * @return array{post: Post, featuredPosts: list<Post>, recentPosts: list<Post>, categories: list<array{0: Category, posts_count: int|string}>}
     */
    public function getShowViewData(
        Post $post,
        int $featuredLimit = 3,
        int $recentLimit = 4,
        int $minCategoryCount = 2,
    ): array {
        return [
            'post' => $post,
            'featuredPosts' => $this->posts->findFeatured($featuredLimit),
            'recentPosts' => $this->posts->findRecentPublished($recentLimit),
            'categories' => $this->categories->findPopularWithPostCounts($minCategoryCount),
            'aphorism' => $this->aphorisms->findRandom(),
        ];
    }
}
