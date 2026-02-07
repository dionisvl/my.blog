<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminFrontPartPayload;
use App\Entity\FrontPart;
use App\Manager\AdminFrontPartManager;
use App\Repository\FrontPartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FrontPartController extends AbstractController
{
    public function __construct(
        private readonly FrontPartRepository $frontPartRepository,
        private readonly AdminFrontPartManager $frontPartManager,
    ) {
    }

    #[Route('/admin/frontparts/', name: 'admin_frontparts_index')]
    public function index(): Response
    {
        $frontparts = $this->frontPartRepository->findAllOrderedByUpdatedAtDesc();

        return $this->render('admin/frontparts/index.html.twig', [
            'frontparts' => $frontparts,
        ]);
    }

    #[Route('/admin/frontparts/store', name: 'admin_frontparts_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminFrontPartPayload $payload): RedirectResponse
    {
        $this->frontPartManager->create($payload);
        $this->addFlash('success', 'Front part created successfully!');

        return $this->redirectToRoute('admin_frontparts_index');
    }

    #[Route('/admin/frontparts/create', name: 'admin_frontparts_create')]
    public function create(): Response
    {
        return $this->render('admin/frontparts/create.html.twig');
    }

    #[Route('/admin/frontparts/{id}/edit', name: 'admin_frontparts_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $frontpart = $this->frontPartRepository->find($id);

        if (!$frontpart instanceof FrontPart) {
            throw $this->createNotFoundException('Front part not found');
        }

        return $this->render('admin/frontparts/edit.html.twig', [
            'frontpart' => $frontpart,
        ]);
    }

    #[Route('/admin/frontparts/{id}/update', name: 'admin_frontparts_update', requirements: ['id' => '\d+'], methods: [
        'POST',
        'PUT'
    ])]
    public function update(int $id, #[MapRequestPayload] AdminFrontPartPayload $payload): RedirectResponse
    {
        $frontpart = $this->frontPartRepository->find($id);

        if (!$frontpart instanceof FrontPart) {
            throw $this->createNotFoundException('Front part not found');
        }

        $this->frontPartManager->update($frontpart, $payload);
        $this->addFlash('success', 'Front part updated successfully!');

        return $this->redirectToRoute('admin_frontparts_index');
    }

    #[Route('/admin/frontparts/{id}/delete', name: 'admin_frontparts_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): RedirectResponse
    {
        $frontpart = $this->frontPartRepository->find($id);

        if (!$frontpart instanceof FrontPart) {
            throw $this->createNotFoundException('Front part not found');
        }

        $this->frontPartManager->delete($frontpart);
        $this->addFlash('success', 'Front part deleted successfully!');

        return $this->redirectToRoute('admin_frontparts_index');
    }
}
