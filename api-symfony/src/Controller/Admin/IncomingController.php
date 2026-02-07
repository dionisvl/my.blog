<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Incoming;
use App\Manager\AdminIncomingManager;
use App\Repository\IncomingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class IncomingController extends AbstractController
{
    public function __construct(
        private readonly IncomingRepository $incomingRepository,
        private readonly AdminIncomingManager $incomingManager,
    ) {
    }

    #[Route('/admin/incomings/', name: 'admin_incomings_index')]
    public function index(): Response
    {
        $incomings = $this->incomingRepository->findAll();

        return $this->render('admin/incomings/index.html.twig', [
            'incomings' => $incomings,
        ]);
    }

    #[Route('/admin/incomings/{id}/toggle', name: 'admin_incomings_toggle', requirements: ['id' => '\d+'])]
    public function toggle(int $id): RedirectResponse
    {
        $incoming = $this->incomingRepository->find($id);

        if (!$incoming instanceof Incoming) {
            throw $this->createNotFoundException('Incoming message not found');
        }

        $this->incomingManager->toggleStatus($incoming);

        return $this->redirectToRoute('admin_incomings_index');
    }

    #[Route('/admin/incomings/{id}/delete', name: 'admin_incomings_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): RedirectResponse
    {
        $incoming = $this->incomingRepository->find($id);

        if (!$incoming instanceof Incoming) {
            throw $this->createNotFoundException('Incoming message not found');
        }

        $this->incomingManager->delete($incoming);

        return $this->redirectToRoute('admin_incomings_index');
    }
}
