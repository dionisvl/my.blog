<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;

final readonly class AdminDashboardQuery
{
    public function __construct(
        private PostRepository $posts,
        private CategoryRepository $categories,
        private UserRepository $users,
    ) {
    }

    /**
     * @return array{posts_count: int, categories_count: int, users_count: int, recent_posts: list<Post>}
     */
    public function getDashboardData(int $recentLimit = 5): array
    {
        return [
            'posts_count' => $this->posts->count([]),
            'categories_count' => $this->categories->count([]),
            'users_count' => $this->users->count([]),
            'recent_posts' => $this->posts->findLatest($recentLimit),
        ];
    }
}
