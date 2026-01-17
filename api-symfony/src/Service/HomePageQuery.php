<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Post;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

final readonly class HomePageQuery
{
    public function __construct(
        private PostRepository $posts,
        private CategoryRepository $categories
    ) {
    }

    /**
     * @return array{posts: list<Post>, categories: list<array{0: Category, posts_count: string|int}>, featuredPosts: list<Post>, recentPosts: list<Post>}
     */
    public function getIndexViewData(
        int $postLimit = 20,
        int $featuredLimit = 3,
        int $recentLimit = 4
    ): array {
        return [
            'posts' => $this->posts->findPublishedLatest($postLimit),
            'categories' => $this->categories->findWithPostCounts(),
            'featuredPosts' => $this->posts->findFeatured($featuredLimit),
            'recentPosts' => $this->posts->findRecentPublished($recentLimit),
        ];
    }

    public function findPostForShow(string $slug): ?Post
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    /**
     * @return array{post: Post, featuredPosts: list<Post>, recentPosts: list<Post>, categories: list<array{0: Category, posts_count: string|int}>}
     */
    public function getShowViewData(
        Post $post,
        int $featuredLimit = 3,
        int $recentLimit = 4,
        int $minCategoryCount = 2
    ): array {
        return [
            'post' => $post,
            'featuredPosts' => $this->posts->findFeatured($featuredLimit),
            'recentPosts' => $this->posts->findRecentPublished($recentLimit),
            'categories' => $this->categories->findPopularWithPostCounts($minCategoryCount),
        ];
    }
}
