<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\View;
use App\Services\DashboardService;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly View $view,
    ) {
    }

    /**
     * The admin dashboard: sales totals, low stock and the latest purchases.
     * @return Response
     */
    #[Route('/admin', ['GET'], ['admin'])]
    public function index(Request $request): Response
    {
        return $this->view->render('admin/dashboard', ['title' => 'Dashboard'] + $this->dashboard->overview());
    }
}
