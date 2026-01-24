<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminCategoryPayload;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminCategoryManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(AdminCategoryPayload $payload): Category
    {
        $category = new Category();
        $category->setTitle($payload->title);
        $category->setDetailText($payload->detailText);
        $category->setPreviewText($payload->previewText);
        $category->setSlug($this->generateUniqueSlug($payload->title));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        $slug = $base;
        $suffix = 2;

        while ($this->categoryRepository->findOneBy(['slug' => $slug])) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    public function update(Category $category, AdminCategoryPayload $payload): Category
    {
        $category->setTitle($payload->title);
        $category->setDetailText($payload->detailText);
        $category->setPreviewText($payload->previewText);

        if ('' === $category->getSlug()) {
            $category->setSlug($this->generateUniqueSlug($payload->title));
        }

        $this->entityManager->flush();

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
