<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\CommentRepository;
use App\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AdminSidebarExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/admin')) {
            return [
                'newCommentsCount' => 0,
                'newOrdersCount' => 0,
            ];
        }

        return [
            'newCommentsCount' => $this->commentRepository->count(['status' => 0]),
            'newOrdersCount' => $this->orderRepository->count(['status' => 0]),
        ];
    }
}
