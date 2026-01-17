<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\AdminOrderPayload;
use App\Manager\AdminOrderManager;
use App\Repository\OrderRepository;
use App\Service\OrdersListPresenter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/orders')]
#[IsGranted('ROLE_ADMIN')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly AdminOrderManager $orderManager,
        private readonly OrdersListPresenter $ordersListPresenter
    ) {
    }

    #[Route('/', name: 'admin_orders_index')]
    public function index(): Response
    {
        $data = $this->ordersListPresenter->getOrdersList();

        return $this->render('admin/orders/index.html.twig', $data);
    }

    #[Route('/download', name: 'admin_orders_download')]
    public function download(): Response
    {
        $spreadsheet = $this->ordersListPresenter->getExcelOrdersList();
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        $filename = 'orders_' . (new \DateTime())->format('Y-m-d') . '.xlsx';

        $response = new StreamedResponse(static function () use ($writer): void {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/store', name: 'admin_orders_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] AdminOrderPayload $payload): Response
    {
        $this->orderManager->create($payload);
        $this->addFlash('success', 'Order created successfully!');

        return $this->redirectToRoute('admin_orders_index');
    }

    #[Route('/create', name: 'admin_orders_create')]
    public function create(): Response
    {
        return $this->render('admin/orders/create.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_orders_edit', requirements: ['id' => '\\d+'])]
    public function edit(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('admin/orders/edit.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_orders_update', requirements: ['id' => '\\d+'], methods: ['POST', 'PUT'])]
    public function update(int $id, #[MapRequestPayload] AdminOrderPayload $payload): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $this->orderManager->update($order, $payload);
        $this->addFlash('success', 'Order updated successfully!');

        return $this->redirectToRoute('admin_orders_index');
    }

    #[Route('/{id}/delete', name: 'admin_orders_delete', requirements: ['id' => '\\d+'], methods: ['POST', 'DELETE'])]
    public function delete(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $this->orderManager->delete($order);
        $this->addFlash('success', 'Order deleted successfully!');

        return $this->redirectToRoute('admin_orders_index');
    }
}
