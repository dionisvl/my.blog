<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminPostPayload;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Service\FileNameGenerator;
use App\Service\FileValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminPostManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private SluggerInterface $slugger,
        private TokenStorageInterface $tokenStorage,
        private FileValidator $fileValidator,
        private FileNameGenerator $fileNameGenerator,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function create(AdminPostPayload $payload): Post
    {
        $post = new Post();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof User) {
            $post->setAuthor($user);
        }

        $slug = $this->slugger->slug($payload->title)->lower()->toString();
        $post->setSlug($slug);

        return $this->applyPayload($post, $payload);
    }

    private function applyPayload(Post $post, AdminPostPayload $payload): Post
    {
        $post->setTitle($payload->title);
        $post->setContent($payload->content);
        $post->setDescription($payload->description);

        if (null !== $payload->date && '' !== $payload->date) {
            $createdAt = \DateTime::createFromFormat('Y-m-d', $payload->date);

            if (false === $createdAt) {
                throw new \InvalidArgumentException(\sprintf('Invalid date format: %s', $payload->date));
            }

            $post->setCreatedAt($createdAt);
        }

        $status = \is_bool($payload->status)
            ? $payload->status
            : filter_var($payload->status, \FILTER_VALIDATE_BOOLEAN);
        $isFeatured = \is_bool($payload->isFeatured)
            ? $payload->isFeatured
            : filter_var($payload->isFeatured, \FILTER_VALIDATE_BOOLEAN);

        $post->setStatus($status);
        $post->setIsFeatured($isFeatured);

        if (null !== $payload->categoryId) {
            $category = $this->categoryRepository->find($payload->categoryId);

            if (null !== $category) {
                $post->setCategory($category);
            }
        }

        if ([] !== $payload->tags) {
            $tagIds = array_values(array_filter($payload->tags, static fn($id): bool => null !== $id && '' !== $id));
            $tags = [] === $tagIds ? [] : $this->tagRepository->findBy(['id' => $tagIds]);
            $post->setTags($tags);
        } else {
            $post->setTags([]);
        }

        if ($payload->image instanceof UploadedFile) {
            $uploadDir = $this->projectDir . '/public/storage/uploads';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $post->uploadImage($payload->image, $uploadDir, $this->fileValidator, $this->fileNameGenerator);
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    public function update(Post $post, AdminPostPayload $payload): Post
    {
        return $this->applyPayload($post, $payload);
    }

    public function delete(Post $post): void
    {
        $this->entityManager->remove($post);
        $this->entityManager->flush();
    }
}
