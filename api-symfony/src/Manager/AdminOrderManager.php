<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\AdminOrderPayload;
use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AdminOrderManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private SluggerInterface $slugger
    ) {
    }

    public function create(AdminOrderPayload $payload): Order
    {
        $order = new Order();
        return $this->applyPayload($order, $payload);
    }

    private function applyPayload(Order $order, AdminOrderPayload $payload): Order
    {
        $order->setTitle($payload->title);
        $order->setSlug($this->generateUniqueSlug($payload->title, $order));
        $order->setPrice($payload->price ?? 0);
        $order->setPhone($payload->phone);
        $order->setAddress($payload->address);
        $order->setNotes($payload->notes);
        $order->setContents($payload->contents);
        $order->setContentsJson($payload->contentsJson);
        $order->setManager($payload->manager);
        $order->setStatus($payload->status ?? 0);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function generateUniqueSlug(string $title, ?Order $current): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $current)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Order $current): bool
    {
        $existing = $this->orderRepository->findOneBy(['slug' => $slug]);
        if ($existing === null) {
            return false;
        }

        if ($current === null || $current->getId() === null) {
            return true;
        }

        return $existing->getId() !== $current->getId();
    }

    public function update(Order $order, AdminOrderPayload $payload): Order
    {
        return $this->applyPayload($order, $payload);
    }

    public function delete(Order $order): void
    {
        $this->entityManager->remove($order);
        $this->entityManager->flush();
    }
}
