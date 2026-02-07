<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminTagPayload;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminTagManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(AdminTagPayload $payload): Tag
    {
        $tag = new Tag();
        $tag->setTitle($payload->title);
        $tag->setSlug($this->generateUniqueSlug($payload->title));

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        $slug = $base;
        $suffix = 2;

        while ($this->tagRepository->findOneBy(['slug' => $slug])) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    public function update(Tag $tag, AdminTagPayload $payload): Tag
    {
        $tag->setTitle($payload->title);

        if ('' === $tag->getSlug()) {
            $tag->setSlug($this->generateUniqueSlug($payload->title));
        }

        $this->entityManager->flush();

        return $tag;
    }

    public function delete(Tag $tag): void
    {
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
    }
}
