<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\PostRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class FrontendFooterExtension extends AbstractExtension implements GlobalsInterface
{
    private const string CACHE_KEY = 'frontend.custom_category_post_id';
    private const int CACHE_TTL = 5;

    public function __construct(
        private readonly PostRepository $posts,
        private readonly CacheInterface $cache,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || str_starts_with($request->getPathInfo(), '/admin')) {
            return [
                'customCategoryPost' => null,
            ];
        }

        $postId = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): ?int {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->posts->findRandomPublishedId();
        });

        if (null === $postId) {
            return [
                'customCategoryPost' => null,
            ];
        }

        $post = $this->posts->findPublishedById($postId);

        if (null === $post) {
            $this->cache->delete(self::CACHE_KEY);
        }

        return [
            'customCategoryPost' => $post,
        ];
    }
}
