<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminPostPayload;
use App\Entity\Post;
use App\Manager\AdminPostManager;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly AdminPostManager $postManager,
    ) {
    }

    #[Route('/admin/posts/', name: 'admin_posts_index')]
    public function index(): Response
    {
        $posts = $this->postRepository->findAllOrderedByCreatedAtDesc();

        return $this->render('admin/posts/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/admin/posts/{id}/edit', name: 'admin_posts_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $post = $this->postRepository->find($id);

        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Post not found');
        }

        $categories = $this->categoryRepository->findAll();
        $tags = $this->tagRepository->findAll();
        $selectedTags = [];

        foreach ($post->getTags() as $tag) {
            $selectedTags[] = $tag->getId();
        }

        return $this->render('admin/posts/edit.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'selectedTags' => $selectedTags,
            'tags' => $tags,
        ]);
    }

    #[Route('/admin/posts/{id}/update', name: 'admin_posts_update', requirements: ['id' => '\d+'], methods: [
        'POST',
        'PUT'
    ])]
    public function update(
        int $id,
        #[MapRequestPayload] AdminPostPayload $payload,
        #[MapUploadedFile(name: 'image')] ?UploadedFile $image = null,
    ): RedirectResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Post not found');
        }

        $payload->image = $image;

        $this->postManager->update($post, $payload);
        $this->addFlash('success', 'Post updated successfully!');

        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/{id}/delete', name: 'admin_posts_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE'
    ])]
    public function delete(int $id): RedirectResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Post not found');
        }

        $this->postManager->delete($post);
        $this->addFlash('success', 'Post deleted successfully!');

        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/store', name: 'admin_posts_store', methods: ['POST'])]
    public function store(
        #[MapRequestPayload] AdminPostPayload $payload,
        #[MapUploadedFile(name: 'image')] ?UploadedFile $image = null,
    ): RedirectResponse
    {
        $payload->image = $image;

        $this->postManager->create($payload);
        $this->addFlash('success', 'Post created successfully!');

        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/create', name: 'admin_posts_create')]
    public function create(): Response
    {
        $categories = $this->categoryRepository->findAll();
        $tags = $this->tagRepository->findAll();

        return $this->render('admin/posts/create.html.twig', [
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }
}
