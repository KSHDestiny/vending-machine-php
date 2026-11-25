<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\View;
use App\Services\DashboardService;
use DateTimeImmutable;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly View $view,
    ) {
    }

    /**
     * The admin dashboard: sales totals, the sales trend (?days=7|14|30), low stock and the latest purchases.
     * @return Response
     */
    #[Route('/admin', ['GET'], ['admin'])]
    public function index(Request $request): Response
    {
        $days = (int) $request->query('days');
        $days = in_array($days, DashboardService::TREND_DAYS, true) ? $days : 14;

        return $this->view->render('admin/dashboard', ['title' => 'Dashboard', 'days' => $days] + $this->dashboard->overview($days, new DateTimeImmutable('today')));
    }
}
