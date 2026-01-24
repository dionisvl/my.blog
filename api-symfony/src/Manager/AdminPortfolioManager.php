<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminPortfolioPayload;
use App\Entity\Portfolio;
use App\Entity\User;
use App\Repository\PortfolioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminPortfolioManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PortfolioRepository $portfolioRepository,
        private SluggerInterface $slugger,
        private TokenStorageInterface $tokenStorage,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function create(AdminPortfolioPayload $payload): Portfolio
    {
        $portfolio = new Portfolio();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof User) {
            $portfolio->setAuthor($user);
        }

        return $this->applyPayload($portfolio, $payload);
    }

    private function applyPayload(Portfolio $portfolio, AdminPortfolioPayload $payload): Portfolio
    {
        $portfolio->setTitle($payload->title);
        $portfolio->setSlug($this->generateUniqueSlug($payload->title, $portfolio));
        $portfolio->setContent($payload->content);
        $portfolio->setDescription($payload->description);

        $status = \is_bool($payload->status)
            ? $payload->status
            : filter_var($payload->status, \FILTER_VALIDATE_BOOLEAN);
        $isFeatured = \is_bool($payload->isFeatured)
            ? $payload->isFeatured
            : filter_var($payload->isFeatured, \FILTER_VALIDATE_BOOLEAN);

        $portfolio->setStatus($status ? 1 : 0);
        $portfolio->setIsFeatured($isFeatured ? 1 : 0);

        if (null !== $payload->image) {
            $uploadDir = $this->projectDir . '/public/storage/uploads/portfolio';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $portfolio->removeImage($uploadDir);
            $portfolio->uploadImage($payload->image, $uploadDir);
        }

        $this->entityManager->persist($portfolio);
        $this->entityManager->flush();

        return $portfolio;
    }

    private function generateUniqueSlug(string $title, ?Portfolio $current): string
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

    private function slugExists(string $slug, ?Portfolio $current): bool
    {
        $existing = $this->portfolioRepository->findOneBy(['slug' => $slug]);

        if (null === $existing) {
            return false;
        }

        if (null === $current || null === $current->getId()) {
            return true;
        }

        return $existing->getId() !== $current->getId();
    }

    public function update(Portfolio $portfolio, AdminPortfolioPayload $payload): Portfolio
    {
        return $this->applyPayload($portfolio, $payload);
    }

    public function delete(Portfolio $portfolio): void
    {
        if (null !== $portfolio->getImage()) {
            $uploadDir = $this->projectDir . '/public/storage/uploads/portfolio';
            $portfolio->removeImage($uploadDir);
        }

        $this->entityManager->remove($portfolio);
        $this->entityManager->flush();
    }
}
