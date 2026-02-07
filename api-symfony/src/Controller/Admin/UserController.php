<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminUserPayload;
use App\Manager\AdminUserManager;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AdminUserManager $userManager,
    ) {
    }

    #[Route('/admin/users/', name: 'admin_users_index')]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/users/store', name: 'admin_users_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminUserPayload $payload): Response
    {
        if (null === $payload->password || '' === $payload->password) {
            return new JsonResponse([
                'errors' => [
                    ['field' => 'password', 'message' => 'Password is required.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->userManager->create($payload);
        $this->addFlash('success', 'User created successfully!');

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/admin/users/create', name: 'admin_users_create')]
    public function create(): Response
    {
        return $this->render('admin/users/create.html.twig');
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/update', name: 'admin_users_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(int $id, #[MapRequestPayload] AdminUserPayload $payload): RedirectResponse
    {
        $user = $this->userRepository->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('User not found');
        }

        $this->userManager->update($user, $payload);
        $this->addFlash('success', 'User updated successfully!');

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST', 'DELETE'])]
    public function delete(int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('User not found');
        }

        $this->userManager->delete($user);
        $this->addFlash('success', 'User deleted successfully!');

        return $this->redirectToRoute('admin_users_index');
    }
}
