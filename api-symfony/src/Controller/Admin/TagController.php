<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminTagPayload;
use App\Manager\AdminTagManager;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tags')]
#[IsGranted('ROLE_ADMIN')]
final class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly AdminTagManager $tagManager
    ) {
    }

    #[Route('/', name: 'admin_tags_index')]
    public function index(): Response
    {
        $tags = $this->tagRepository->findAll();

        return $this->render('admin/tags/index.html.twig', [
            'tags' => $tags,
        ]);
    }

    #[Route('/store', name: 'admin_tags_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminTagPayload $payload): Response
    {
        $this->tagManager->create($payload);
        $this->addFlash('success', 'Tag created successfully!');

        return $this->redirectToRoute('admin_tags_index');
    }

    #[Route('/create', name: 'admin_tags_create')]
    public function create(): Response
    {
        return $this->render('admin/tags/create.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_tags_edit', requirements: ['id' => '\\d+'])]
    public function edit(int $id): Response
    {
        $tag = $this->tagRepository->find($id);

        if (!$tag) {
            throw $this->createNotFoundException('Tag not found');
        }

        return $this->render('admin/tags/edit.html.twig', [
            'tag' => $tag,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_tags_update', requirements: ['id' => '\\d+'], methods: ['POST', 'PUT'])]
    public function update(int $id, #[MapRequestPayload] AdminTagPayload $payload): Response
    {
        $tag = $this->tagRepository->find($id);

        if (!$tag) {
            throw $this->createNotFoundException('Tag not found');
        }

        $this->tagManager->update($tag, $payload);
        $this->addFlash('success', 'Tag updated successfully!');

        return $this->redirectToRoute('admin_tags_index');
    }

    #[Route('/{id}/delete', name: 'admin_tags_delete', requirements: ['id' => '\\d+'], methods: ['POST', 'DELETE'])]
    public function delete(int $id): Response
    {
        $tag = $this->tagRepository->find($id);

        if (!$tag) {
            throw $this->createNotFoundException('Tag not found');
        }

        $this->tagManager->delete($tag);
        $this->addFlash('success', 'Tag deleted successfully!');

        return $this->redirectToRoute('admin_tags_index');
    }
}
