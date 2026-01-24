<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminProductPayload;
use App\Manager\AdminProductManager;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products')]
#[IsGranted('ROLE_ADMIN')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AdminProductManager $productManager,
    ) {
    }

    #[Route('/', name: 'admin_products_index')]
    public function index(): Response
    {
        $products = $this->productRepository->findAllOrderedByUpdatedAtDesc();

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/store', name: 'admin_products_store', methods: ['POST'])]
    public function store(
        #[MapRequestPayload] AdminProductPayload $payload,
        #[MapUploadedFile(name: 'preview_picture')] ?UploadedFile $previewPicture = null,
        #[MapUploadedFile(name: 'detail_picture')] ?UploadedFile $detailPicture = null,
    ): Response {
        $payload->previewPicture = $previewPicture;
        $payload->detailPicture = $detailPicture;

        $this->productManager->create($payload);
        $this->addFlash('success', 'Product created successfully!');

        return $this->redirectToRoute('admin_products_index');
    }

    #[Route('/create', name: 'admin_products_create')]
    public function create(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('admin/products/create.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_products_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $categories = $this->categoryRepository->findAll();

        return $this->render('admin/products/edit.html.twig', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_products_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] AdminProductPayload $payload,
        #[MapUploadedFile(name: 'preview_picture')] ?UploadedFile $previewPicture = null,
        #[MapUploadedFile(name: 'detail_picture')] ?UploadedFile $detailPicture = null,
    ): Response {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $payload->previewPicture = $previewPicture;
        $payload->detailPicture = $detailPicture;

        $this->productManager->update($product, $payload);
        $this->addFlash('success', 'Product updated successfully!');

        return $this->redirectToRoute('admin_products_index');
    }

    #[Route('/{id}/delete', name: 'admin_products_delete', requirements: ['id' => '\d+'], methods: ['POST', 'DELETE'])]
    public function delete(int $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $this->productManager->delete($product);
        $this->addFlash('success', 'Product deleted successfully!');

        return $this->redirectToRoute('admin_products_index');
    }
}
