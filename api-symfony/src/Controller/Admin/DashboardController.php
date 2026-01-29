<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AdminDashboardQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(private readonly AdminDashboardQuery $dashboardQuery)
    {
    }

    #[Route('/admin/', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig', $this->dashboardQuery->getDashboardData());
    }
}
