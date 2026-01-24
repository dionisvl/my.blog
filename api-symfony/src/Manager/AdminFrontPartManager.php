<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminFrontPartPayload;
use App\Entity\FrontPart;
use App\Repository\FrontPartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminFrontPartManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FrontPartRepository $frontPartRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(AdminFrontPartPayload $payload): FrontPart
    {
        $frontPart = new FrontPart();

        return $this->applyPayload($frontPart, $payload);
    }

    private function applyPayload(FrontPart $frontPart, AdminFrontPartPayload $payload): FrontPart
    {
        $frontPart->setTitle($payload->title);
        $frontPart->setSlug($this->generateUniqueSlug($payload->title, $frontPart));
        $frontPart->setCategoryName($payload->categoryName);
        $frontPart->setType($payload->type);
        $frontPart->setPreviewText($payload->previewText);
        $frontPart->setDetailText($payload->detailText);
        $frontPart->setUrl($payload->url);

        $status = \is_bool($payload->status)
            ? $payload->status
            : filter_var($payload->status, \FILTER_VALIDATE_BOOLEAN);
        $frontPart->setStatus($status ? '1' : '0');

        $this->entityManager->persist($frontPart);
        $this->entityManager->flush();

        return $frontPart;
    }

    private function generateUniqueSlug(string $title, ?FrontPart $current): string
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

    private function slugExists(string $slug, ?FrontPart $current): bool
    {
        $existing = $this->frontPartRepository->findOneBy(['slug' => $slug]);

        if (null === $existing) {
            return false;
        }

        if (null === $current || null === $current->getId()) {
            return true;
        }

        return $existing->getId() !== $current->getId();
    }

    public function update(FrontPart $frontPart, AdminFrontPartPayload $payload): FrontPart
    {
        return $this->applyPayload($frontPart, $payload);
    }

    public function delete(FrontPart $frontPart): void
    {
        $this->entityManager->remove($frontPart);
        $this->entityManager->flush();
    }
}
