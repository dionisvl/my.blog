<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminProductPayload;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminProductManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private SluggerInterface $slugger,
        private TokenStorageInterface $tokenStorage,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function create(AdminProductPayload $payload): Product
    {
        $product = new Product();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof User) {
            $product->setAuthor($user);
        }

        return $this->applyPayload($product, $payload);
    }

    private function applyPayload(Product $product, AdminProductPayload $payload): Product
    {
        $product->setTitle($payload->title);
        $product->setSlug($this->generateUniqueSlug($payload->title, $product));
        $product->setDetailText($payload->detailText);
        $product->setPreviewText($payload->previewText);

        if (null !== $payload->price) {
            $product->setPrice($payload->price);
        }

        if (null !== $payload->balance) {
            $product->setBalance($payload->balance);
        }
        $product->setComposition($payload->composition);
        $product->setFeatures($payload->features);
        $product->setSize($payload->size);
        $product->setManufacturer($payload->manufacturer);
        $product->setDelivery($payload->delivery);

        if (null !== $payload->stars) {
            $product->setStars($payload->stars);
        }

        if (null !== $payload->date && '' !== $payload->date) {
            $parsed = \DateTime::createFromFormat('Y-m-d', $payload->date);

            if (false === $parsed) {
                throw new \InvalidArgumentException(\sprintf('Invalid date format: %s', $payload->date));
            }
            $product->setDate($parsed);
        }

        if (null !== $payload->categoryId) {
            $category = $this->categoryRepository->find($payload->categoryId);

            if ($category) {
                $product->setCategory($category);
            }
        }

        $uploadDir = $this->projectDir . '/public/storage/shop_uploads';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (null !== $payload->previewPicture) {
            $product->removeImage('preview_picture', $uploadDir);
            $product->uploadImage($payload->previewPicture, 'preview_picture', $uploadDir);
        }

        if (null !== $payload->detailPicture) {
            $product->removeImage('detail_picture', $uploadDir);
            $product->uploadImage($payload->detailPicture, 'detail_picture', $uploadDir);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function generateUniqueSlug(string $title, ?Product $current): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $current)) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Product $current): bool
    {
        $existing = $this->productRepository->findOneBy(['slug' => $slug]);

        if (null === $existing) {
            return false;
        }

        if (null === $current || null === $current->getId()) {
            return true;
        }

        return $existing->getId() !== $current->getId();
    }

    public function update(Product $product, AdminProductPayload $payload): Product
    {
        return $this->applyPayload($product, $payload);
    }

    public function delete(Product $product): void
    {
        $uploadDir = $this->projectDir . '/public/storage/shop_uploads';
        $product->removeImage('preview_picture', $uploadDir);
        $product->removeImage('detail_picture', $uploadDir);

        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }
}
