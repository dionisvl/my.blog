<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminProductPayload;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Media\ImageUploader;
use App\Media\MediaTarget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        private ImageUploader $imageUploader,
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

            if (null !== $category) {
                $product->setCategory($category);
            }
        }

        $newImages = [];

        try {
            if ($payload->previewPicture instanceof UploadedFile) {
                $newImages[] = $filename = $this->imageUploader->upload($payload->previewPicture, MediaTarget::Product);
                $product->setPreviewPicture($filename);
            }

            if ($payload->detailPicture instanceof UploadedFile) {
                $newImages[] = $filename = $this->imageUploader->upload($payload->detailPicture, MediaTarget::Product);
                $product->setDetailPicture($filename);
            }

            $this->entityManager->persist($product);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            foreach ($newImages as $image) {
                $this->imageUploader->discard(MediaTarget::Product, $image);
            }
            throw $e;
        }

        return $product;
    }

    private function generateUniqueSlug(string $title, ?Product $current): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $current)) {
            $slug = $base.'-'.$suffix;
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

        if (!$current instanceof Product || null === $current->getId()) {
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
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }
}
