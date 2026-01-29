<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Manager\CommentManager;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comment', name: 'comment_store', methods: ['POST'])]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentManager $commentManager,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $message = $request->request->get('message');
        $postId = (int)$request->request->get('post_id');
        $countMe = (int)$request->request->get('countMe');
        $honeypot = $request->request->get('honeypot');
        $referer = $request->headers->get('referer', '/');

        if (empty($message)) {
            $this->addFlash('error', 'Comment text is required');

            return $this->redirect($referer);
        }

        if ($countMe < 3) {
            $this->addFlash('error', 'Anti-bot check failed');

            return $this->redirect($referer);
        }

        if (!empty($honeypot)) {
            $this->addFlash('error', 'Error: HPF');

            return $this->redirect($referer);
        }

        $post = $this->postRepository->find($postId);

        if (null === $post) {
            $this->addFlash('error', 'Post not found');

            return $this->redirect($referer);
        }

        $user = $this->getUser();
        $commentUser = $user instanceof User ? $user : null;

        $this->commentManager->createComment($post, $commentUser, (string)$message);

        $this->addFlash('success', 'Your comment will be added soon!');

        return $this->redirect($referer);
    }
}
