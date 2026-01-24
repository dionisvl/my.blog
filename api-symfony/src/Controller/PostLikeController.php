<?php

declare(strict_types=1);

namespace App\Controller;

use App\Manager\PostLikeManager;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/postlike/{postId}', name: 'post_like_toggle', methods: ['POST'])]
final class PostLikeController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PostLikeManager $postLikeManager,
        #[Autowire('%app.secure_cookies%')]
        private readonly bool $secureCookies,
    ) {
    }

    public function __invoke(int $postId, Request $request): JsonResponse
    {
        if ($postId <= 0) {
            return $this->json([
                'status' => 'error',
                'data' => 'empty post_id',
            ]);
        }

        $cookieName = 'likedPostToday' . $postId;
        $isLiked = $request->cookies->has($cookieName);

        if ($isLiked) {
            $likedAt = $request->cookies->get($cookieName);

            if (null !== $likedAt) {
                try {
                    $likeDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', (string)$likedAt);

                    if ($likeDateTime instanceof \DateTime) {
                        $this->postLikeManager->removeLikeForPostAt($postId, $likeDateTime);
                    }
                } catch (\Exception) {
                    // If cookie is malformed, ignore the error and proceed to clear it
                }
            }

            $response = $this->json([
                'status' => 'ok',
                'data' => 'unliked',
            ]);

            $response->headers->clearCookie($cookieName);

            return $response;
        }

        $post = $this->postRepository->find($postId);

        if (!$post) {
            return $this->json([
                'status' => 'error',
                'data' => 'post not found',
            ]);
        }

        $deviceMemory = null;

        try {
            $data = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

            if (isset($data['device_memory'])) {
                $deviceMemory = (int)$data['device_memory'];
            }
        } catch (\JsonException) {
            // If JSON is invalid, continue without device_memory data
        }

        $postLike = $this->postLikeManager->addLike($post, $deviceMemory);

        $response = $this->json([
            'status' => 'ok',
            'data' => 'liked',
        ]);

        $cookie = Cookie::create($cookieName)
            ->withValue($postLike->getCreatedAt()->format('Y-m-d H:i:s'))
            ->withExpires(time() + 60 * 60 * 24)
            ->withPath('/')
            ->withSecure($this->secureCookies)
            ->withHttpOnly(true);

        $response->headers->setCookie($cookie);

        return $response;
    }
}
