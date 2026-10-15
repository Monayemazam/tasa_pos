<?php

namespace App\Services\Reports;

use App\Models\CourierOrder;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected ProfitLossService $profitLossService
    ) {
    }

    /**
     * Get basic operational dashboard metrics for all authenticated users (Staff & Owner).
     *
     * @return array<string, mixed>
     */
    public function getBasicMetrics(): array
    {
        $today = CarbonImmutable::now()->toDateString();
        $startOfMonth = CarbonImmutable::now()->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::now()->endOfMonth()->toDateString();

        // 1. Today's sales
        $todayAgg = Sale::where('status', '<>', 'cancelled')
            ->where('sale_date', $today)
            ->selectRaw('COUNT(id) AS count, COALESCE(SUM(grand_total), 0) AS total')
            ->first();

        $todaySales = [
            'count' => (int) ($todayAgg->count ?? 0),
            'amount' => round((float) ($todayAgg->total ?? 0), 2),
        ];

        // 2. This month's sales
        $monthAgg = Sale::where('status', '<>', 'cancelled')
            ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(id) AS count, COALESCE(SUM(grand_total), 0) AS total')
            ->first();

        $thisMonthSales = [
            'count' => (int) ($monthAgg->count ?? 0),
            'amount' => round((float) ($monthAgg->total ?? 0), 2),
        ];

        // 3. Courier orders: pending pickup & failed
        $courierPending = CourierOrder::whereIn('status', ['queued', 'pending'])->count();
        $courierFailed = CourierOrder::where('status', 'failed')->count();

        // 4. Low stock alerts (simple products, variants, leather, accessories)
        $lowStockSimpleProducts = Product::where('is_active', true)
            ->where('has_variants', false)
            ->whereNull('deleted_at')
            ->whereRaw('stock_qty <= min_stock')
            ->select('id', 'name', 'sku', 'stock_qty', 'min_stock')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'variant_name' => null,
                'stock_qty' => (int) $p->stock_qty,
                'min_stock' => (int) $p->min_stock,
            ]);

        $lowStockVariants = \App\Models\ProductVariant::where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw('stock_qty <= min_stock')
            ->with('product:id,name')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->product_id,
                'name' => "{$v->product?->name} — {$v->color_name}",
                'sku' => $v->sku,
                'variant_name' => $v->color_name,
                'stock_qty' => (int) $v->stock_qty,
                'min_stock' => (int) $v->min_stock,
            ]);

        $lowStockProducts = $lowStockSimpleProducts->concat($lowStockVariants);

        $lowStockLeather = DB::table('v_leather_stock')
            ->whereRaw('current_stock <= min_stock')
            ->select('id', 'name', 'colour_grade', 'current_stock', 'min_stock')
            ->get();

        $lowStockAccessories = DB::table('v_accessory_stock')
            ->whereRaw('current_stock <= min_stock')
            ->select('id', 'name', 'unit', 'current_stock', 'min_stock')
            ->get();

        $totalLowStockCount = $lowStockProducts->count() + $lowStockLeather->count() + $lowStockAccessories->count();

        // 5. Latest 10 invoices
        $latestInvoices = Sale::where('status', '<>', 'cancelled')
            ->with(['courierOrder:id,sale_id,status,consignment_id'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'sale_date' => $s->sale_date->toDateString(),
                'customer_name' => $s->customer_name,
                'customer_phone' => $s->customer_phone,
                'grand_total' => (float) $s->grand_total,
                'paid_total' => (float) $s->paid_total,
                'due_amount' => (float) $s->due_amount,
                'payment_status' => $s->payment_status,
                'courier_status' => $s->courierOrder?->status ?? 'none',
                'courier_consignment' => $s->courierOrder?->consignment_id,
            ])
            ->toArray();

        return [
            'today_sales' => $todaySales,
            'this_month_sales' => $thisMonthSales,
            'courier_orders' => [
                'pending' => $courierPending,
                'failed' => $courierFailed,
            ],
            'low_stock' => [
                'total_count' => $totalLowStockCount,
                'products_count' => $lowStockProducts->count(),
                'leather_count' => $lowStockLeather->count(),
                'accessories_count' => $lowStockAccessories->count(),
                'products' => $lowStockProducts->toArray(),
                'leather' => $lowStockLeather->toArray(),
                'accessories' => $lowStockAccessories->toArray(),
            ],
            'latest_invoices' => $latestInvoices,
            'quick_actions' => [
                ['label' => 'New Sale', 'url' => '/sales/create', 'icon' => 'plus'],
                ['label' => 'Add Expense', 'url' => '/expenses', 'icon' => 'receipt'],
                ['label' => 'Add Leather Purchase', 'url' => '/inventory/leather', 'icon' => 'database'],
            ],
        ];
    }

    /**
     * Get sensitive Owner/Financial dashboard metrics.
     * Accessible only by Owner or users with dashboard.view_financials permission.
     *
     * @return array<string, mixed>
     */
    public function getFinancialMetrics(): array
    {
        $now = CarbonImmutable::now();
        $startOfMonth = $now->startOfMonth()->toDateString();
        $endOfMonth = $now->endOfMonth()->toDateString();

        // 1. Current Month P&L using actual ProfitLossService (Requirement 8 & 9)
        $currentPl = $this->profitLossService->calculate($startOfMonth, $endOfMonth);

        // 2. COD to receive (delivered, not yet settled - Schema §8.6)
        $codToReceive = (float) DB::table('courier_orders')
            ->where('status', 'delivered')
            ->where('cod_amount', '>', 0)
            ->whereNull('cod_settled_at')
            ->sum('cod_amount');

        // 3. Customer dues (excluding COD still with courier - Schema §8.6)
        $customerDues = (float) DB::table('sales')
            ->where('status', '<>', 'cancelled')
            ->where('payment_status', '<>', 'paid')
            ->whereNotIn('id', function ($query) {
                $query->select('sale_id')
                    ->from('courier_orders')
                    ->where('cod_amount', '>', 0)
                    ->whereNull('cod_settled_at');
            })
            ->sum('due_amount');

        // 4. Payroll balances (owed and over-paid for both employees and workers)
        $payrollBalances = $this->calculatePayrollBalances();

        // 5. Six-month sales vs expenses trend (Requirement 8 & 10)
        $sixMonthTrend = $this->calculateSixMonthTrend($now);

        return [
            'net_profit_this_month' => $currentPl['net_profit'],
            'gross_margin_pct' => $currentPl['gross_margin_pct'],
            'total_revenue_this_month' => $currentPl['revenue']['total_revenue'],
            'total_expenses_this_month' => $currentPl['expenses']['total'],
            'cod_to_receive' => round($codToReceive, 2),
            'customer_dues' => round($customerDues, 2),
            'payroll_balances' => $payrollBalances,
            'facebook_ads' => [
                'ad_spend' => $currentPl['marketing']['facebook_ads_spend'],
                'sales' => $currentPl['marketing']['net_sales'],
                'roas' => $currentPl['marketing']['roas'],
                'roas_formatted' => $currentPl['marketing']['roas_formatted'],
                'ad_spend_pct_sales' => $currentPl['marketing']['ad_spend_pct_sales'],
            ],
            'six_month_trend' => $sixMonthTrend,
        ];
    }

    /**
     * Compute aggregated payroll balances across all employees and workers.
     * Positive = Tasa owes worker/employee. Negative = Worker/employee was overpaid (advance).
     *
     * @return array{owed: float, overpaid: float}
     */
    protected function calculatePayrollBalances(): array
    {
        // 1. Employee balances
        $employees = DB::table('employees as e')
            ->leftJoin(DB::raw('(SELECT employee_id, SUM(payable) as total_payable FROM salary_months GROUP BY employee_id) sm'), 'sm.employee_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT employee_id, SUM(amount) as total_paid FROM salary_payments GROUP BY employee_id) sp'), 'sp.employee_id', '=', 'e.id')
            ->selectRaw('e.id, (COALESCE(sm.total_payable, 0) - COALESCE(sp.total_paid, 0)) as balance')
            ->get();

        // 2. Worker balances
        $workers = DB::table('workers as w')
            ->leftJoin(DB::raw('(SELECT worker_id, SUM(amount) as total_earned FROM worker_earnings GROUP BY worker_id) we'), 'we.worker_id', '=', 'w.id')
            ->leftJoin(DB::raw('(SELECT worker_id, SUM(amount) as total_paid FROM worker_payments GROUP BY worker_id) wp'), 'wp.worker_id', '=', 'w.id')
            ->selectRaw('w.id, (COALESCE(we.total_earned, 0) - COALESCE(wp.total_paid, 0)) as balance')
            ->get();

        $owed = 0.00;
        $overpaid = 0.00;

        foreach ($employees as $emp) {
            $bal = (float) $emp->balance;
            if ($bal > 0) {
                $owed += $bal;
            } elseif ($bal < 0) {
                $overpaid += abs($bal);
            }
        }

        foreach ($workers as $wkr) {
            $bal = (float) $wkr->balance;
            if ($bal > 0) {
                $owed += $bal;
            } elseif ($bal < 0) {
                $overpaid += abs($bal);
            }
        }

        return [
            'owed' => round($owed, 2),
            'overpaid' => round($overpaid, 2),
        ];
    }

    /**
     * Compute 6-month historical revenue vs expenses trend.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function calculateSixMonthTrend(CarbonImmutable $currentDate): array
    {
        $trend = [];

        // Last 6 months in chronological order
        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = $currentDate->subMonths($i);
            $from = $targetMonth->startOfMonth()->toDateString();
            $to = $targetMonth->endOfMonth()->toDateString();

            $pl = $this->profitLossService->calculate($from, $to);

            $trend[] = [
                'month_key' => $targetMonth->format('Y-m'),
                'month_label' => $targetMonth->format('M Y'),
                'revenue' => $pl['revenue']['total_revenue'],
                'expenses' => $pl['expenses']['total'],
                'net_profit' => $pl['net_profit'],
            ];
        }

        return $trend;
    }
}
