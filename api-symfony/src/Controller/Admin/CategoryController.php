<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminCategoryPayload;
use App\Manager\AdminCategoryManager;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly AdminCategoryManager $categoryManager,
    ) {
    }

    #[Route('/', name: 'admin_categories_index')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/store', name: 'admin_categories_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminCategoryPayload $payload): Response
    {
        $this->categoryManager->create($payload);
        $this->addFlash('success', 'Category created successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/create', name: 'admin_categories_create')]
    public function create(): Response
    {
        return $this->render('admin/categories/create.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        return $this->render('admin/categories/edit.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_categories_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(int $id, #[MapRequestPayload] AdminCategoryPayload $payload): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $this->categoryManager->update($category, $payload);
        $this->addFlash('success', 'Category updated successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/delete', name: 'admin_categories_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $this->categoryManager->delete($category);
        $this->addFlash('success', 'Category deleted successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }
}
