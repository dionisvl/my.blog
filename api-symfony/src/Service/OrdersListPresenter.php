<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;

final readonly class OrdersListPresenter
{
    public function __construct(private OrderRepository $orderRepository)
    {
    }

    /**
     * @return array{orders: list<Order>}
     */
    public function getOrdersList(): array
    {
        return ['orders' => $this->orderRepository->findAllOrderedByUpdatedAtDesc()];
    }

    public function getExcelOrdersList(): Spreadsheet
    {
        $orders = $this->orderRepository->findAllOrderedByUpdatedAtDesc();
        if ($orders === []) {
            return new Spreadsheet();
        }

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'id' => $order->getId(),
                'title' => $order->getTitle(),
                'slug' => $order->getSlug(),
                'price' => $order->getPrice(),
                'user_id' => $order->getCustomer()?->getId(),
                'phone' => $order->getPhone(),
                'address' => $order->getAddress(),
                'notes' => $order->getNotes(),
                'contents' => $order->getContents(),
                'contents_json' => $order->getContentsJson(),
                'manager' => $order->getManager(),
                'manager_id' => $order->getManagerId(),
                'status' => $order->getStatus(),
                'status_date' => $order->getStatusDate()?->format('Y-m-d'),
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $headings = [array_keys($rows[0])];
        array_splice($rows, 0, 0, $headings);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Creator')
            ->setLastModifiedBy('User')
            ->setTitle('My Awesome Orders');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B1', 'My Awesome Orders');
        $sheet->getStyle('B1')->getFont()->getColor()->setARGB(Color::COLOR_RED);
        $sheet->getStyle('B1')->getFont()->setSize(20);
        $sheet->fromArray($rows, null, 'B2');

        return $spreadsheet;
    }
}
