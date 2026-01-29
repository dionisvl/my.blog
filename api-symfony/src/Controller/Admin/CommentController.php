<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Manager\AdminCommentManager;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly AdminCommentManager $commentManager,
    ) {
    }

    #[Route('/admin/comments/', name: 'admin_comments_index')]
    public function index(): Response
    {
        $comments = $this->commentRepository->findAllOrderedByCreatedAtDesc();

        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
        ]);
    }

    #[Route('/admin/comments/{id}/toggle', name: 'admin_comments_toggle', requirements: ['id' => '\d+'])]
    public function toggle(int $id): RedirectResponse
    {
        $comment = $this->commentRepository->find($id);

        if (!$comment instanceof Comment) {
            throw $this->createNotFoundException('Comment not found');
        }

        $this->commentManager->toggleStatus($comment);

        return $this->redirectToRoute('admin_comments_index');
    }

    #[Route('/admin/comments/{id}/delete', name: 'admin_comments_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE'
    ])]
    public function delete(int $id): RedirectResponse
    {
        $comment = $this->commentRepository->find($id);

        if (!$comment instanceof Comment) {
            throw $this->createNotFoundException('Comment not found');
        }

        $this->commentManager->delete($comment);

        return $this->redirectToRoute('admin_comments_index');
    }
}
