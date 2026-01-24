<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Manager\AdminCommentManager;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/comments')]
#[IsGranted('ROLE_ADMIN')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly AdminCommentManager $commentManager,
    ) {
    }

    #[Route('/', name: 'admin_comments_index')]
    public function index(): Response
    {
        $comments = $this->commentRepository->findAllOrderedByCreatedAtDesc();

        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_comments_toggle', requirements: ['id' => '\d+'])]
    public function toggle(int $id): Response
    {
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            throw $this->createNotFoundException('Comment not found');
        }

        $this->commentManager->toggleStatus($comment);

        return $this->redirectToRoute('admin_comments_index');
    }

    #[Route('/{id}/delete', name: 'admin_comments_delete', requirements: ['id' => '\d+'], methods: ['POST', 'DELETE'])]
    public function delete(int $id): Response
    {
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            throw $this->createNotFoundException('Comment not found');
        }

        $this->commentManager->delete($comment);

        return $this->redirectToRoute('admin_comments_index');
    }
}
