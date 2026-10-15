<?php

namespace App\Http\Controllers;

use App\Services\Reports\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
    }

    /**
     * Display the application dashboard with role-appropriate widgets.
     * Staff users receive operational metrics only.
     * Owners and financial permission holders receive sensitive profit,
     * expense, payroll, COD, and customer due metrics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewFinancials = $user && ($user->hasRole('Owner') || $user->can('dashboard.view_financials'));

        $basic = $this->dashboardService->getBasicMetrics();
        $financials = $canViewFinancials ? $this->dashboardService->getFinancialMetrics() : null;

        return Inertia::render('Dashboard', [
            'basic' => $basic,
            'financials' => $financials,
            'canViewFinancials' => $canViewFinancials,
        ]);
    }
}
