<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminPortfolioPayload;
use App\Manager\AdminPortfolioManager;
use App\Repository\PortfolioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/portfolios')]
#[IsGranted('ROLE_ADMIN')]
final class PortfolioController extends AbstractController
{
    public function __construct(
        private readonly PortfolioRepository $portfolioRepository,
        private readonly AdminPortfolioManager $portfolioManager,
    ) {
    }

    #[Route('/', name: 'admin_portfolios_index')]
    public function index(): Response
    {
        $portfolios = $this->portfolioRepository->findAllOrderedByUpdatedAtDesc();

        return $this->render('admin/portfolios/index.html.twig', [
            'portfolios' => $portfolios,
        ]);
    }

    #[Route('/store', name: 'admin_portfolios_store', methods: ['POST'])]
    public function store(
        #[MapRequestPayload] AdminPortfolioPayload $payload,
        #[MapUploadedFile(name: 'image')] ?UploadedFile $image = null,
    ): Response {
        $payload->image = $image;

        $this->portfolioManager->create($payload);
        $this->addFlash('success', 'Portfolio created successfully!');

        return $this->redirectToRoute('admin_portfolios_index');
    }

    #[Route('/create', name: 'admin_portfolios_create')]
    public function create(): Response
    {
        return $this->render('admin/portfolios/create.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_portfolios_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $portfolio = $this->portfolioRepository->find($id);

        if (!$portfolio) {
            throw $this->createNotFoundException('Portfolio not found');
        }

        return $this->render('admin/portfolios/edit.html.twig', [
            'portfolio' => $portfolio,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_portfolios_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] AdminPortfolioPayload $payload,
        #[MapUploadedFile(name: 'image')] ?UploadedFile $image = null,
    ): Response {
        $portfolio = $this->portfolioRepository->find($id);

        if (!$portfolio) {
            throw $this->createNotFoundException('Portfolio not found');
        }

        $payload->image = $image;
        $this->portfolioManager->update($portfolio, $payload);
        $this->addFlash('success', 'Portfolio updated successfully!');

        return $this->redirectToRoute('admin_portfolios_index');
    }

    #[Route('/{id}/delete', name: 'admin_portfolios_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): Response
    {
        $portfolio = $this->portfolioRepository->find($id);

        if (!$portfolio) {
            throw $this->createNotFoundException('Portfolio not found');
        }

        $this->portfolioManager->delete($portfolio);
        $this->addFlash('success', 'Portfolio deleted successfully!');

        return $this->redirectToRoute('admin_portfolios_index');
    }
}
