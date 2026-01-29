<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminCategoryPayload;
use App\Entity\Category;
use App\Manager\AdminCategoryManager;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly AdminCategoryManager $categoryManager,
    ) {
    }

    #[Route('/admin/categories/', name: 'admin_categories_index')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/categories/store', name: 'admin_categories_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminCategoryPayload $payload): RedirectResponse
    {
        $this->categoryManager->create($payload);
        $this->addFlash('success', 'Category created successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/admin/categories/create', name: 'admin_categories_create')]
    public function create(): Response
    {
        return $this->render('admin/categories/create.html.twig');
    }

    #[Route('/admin/categories/{id}/edit', name: 'admin_categories_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category instanceof Category) {
            throw $this->createNotFoundException('Category not found');
        }

        return $this->render('admin/categories/edit.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/admin/categories/{id}/update', name: 'admin_categories_update', requirements: ['id' => '\d+'], methods: [
        'POST',
        'PUT'
    ])]
    public function update(int $id, #[MapRequestPayload] AdminCategoryPayload $payload): RedirectResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category instanceof Category) {
            throw $this->createNotFoundException('Category not found');
        }

        $this->categoryManager->update($category, $payload);
        $this->addFlash('success', 'Category updated successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/admin/categories/{id}/delete', name: 'admin_categories_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): RedirectResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category instanceof Category) {
            throw $this->createNotFoundException('Category not found');
        }

        $this->categoryManager->delete($category);
        $this->addFlash('success', 'Category deleted successfully!');

        return $this->redirectToRoute('admin_categories_index');
    }
}
