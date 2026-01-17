<?php

declare(strict_types=1);

namespace App\Controller;

use App\Manager\PostViewManager;
use App\Service\HomePageQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly HomePageQuery $homePageQuery,
        private readonly PostViewManager $postViewManager
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', $this->homePageQuery->getIndexViewData());
    }

    #[Route('/post/{slug}', name: 'post_show')]
    public function show(string $slug, Request $request): Response
    {
        $post = $this->homePageQuery->findPostForShow($slug);

        if (!$post) {
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
                ->withValue((new \DateTime())->format('Y-m-d H:i:s'))
                ->withExpires(time() + 60 * 60 * 24)
                ->withPath('/')
                ->withSecure(false)
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
}
