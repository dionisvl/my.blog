<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminSubscriberPayload;
use App\Manager\AdminSubscriberManager;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subscribers')]
#[IsGranted('ROLE_ADMIN')]
final class SubscriberController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly AdminSubscriberManager $subscriberManager,
    ) {
    }

    #[Route('/', name: 'admin_subscribers_index')]
    public function index(): Response
    {
        $subs = $this->subscriptionRepository->findAll();

        return $this->render('admin/subscribers/index.html.twig', [
            'subs' => $subs,
        ]);
    }

    #[Route('/store', name: 'admin_subscribers_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminSubscriberPayload $payload): Response
    {
        $this->subscriberManager->create($payload);
        $this->addFlash('success', 'Subscriber created successfully!');

        return $this->redirectToRoute('admin_subscribers_index');
    }

    #[Route('/create', name: 'admin_subscribers_create')]
    public function create(): Response
    {
        return $this->render('admin/subscribers/create.html.twig');
    }

    #[Route('/{id}/delete', name: 'admin_subscribers_delete', requirements: ['id' => '\d+'], methods: [
        'POST',
        'DELETE',
    ])]
    public function delete(int $id): Response
    {
        $subscriber = $this->subscriptionRepository->find($id);

        if (!$subscriber) {
            throw $this->createNotFoundException('Subscriber not found');
        }

        $this->subscriberManager->delete($subscriber);
        $this->addFlash('success', 'Subscriber deleted successfully!');

        return $this->redirectToRoute('admin_subscribers_index');
    }
}
