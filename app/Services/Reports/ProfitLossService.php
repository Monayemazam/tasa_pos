<?php

namespace App\Services\Reports;

use App\Models\AccessoryConsumption;
use App\Models\AccessoryItem;
use App\Models\AccessoryPurchase;
use App\Models\CourierOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\Product;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Services\Payroll\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProfitLossService
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Resolve date range from preset or custom inputs.
     * Presets: this_month (default), last_month, this_quarter, this_year, custom.
     *
     * @return array{preset: string, from: string, to: string, label: string}
     */
    public function resolveDateRange(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::now();

        switch ($preset) {
            case 'last_month':
                $lastMonth = $today->subMonth();

                return [
                    'preset' => 'last_month',
                    'from' => $lastMonth->startOfMonth()->toDateString(),
                    'to' => $lastMonth->endOfMonth()->toDateString(),
                    'label' => 'Last Month ('.$lastMonth->format('F Y').')',
                ];

            case 'this_quarter':
                $startOfQuarter = $today->firstOfQuarter();
                $endOfQuarter = $today->lastOfQuarter();

                return [
                    'preset' => 'this_quarter',
                    'from' => $startOfQuarter->toDateString(),
                    'to' => $endOfQuarter->toDateString(),
                    'label' => 'This Quarter (Q'.$today->quarter.' '.$today->format('Y').')',
                ];

            case 'this_year':
                return [
                    'preset' => 'this_year',
                    'from' => $today->startOfYear()->toDateString(),
                    'to' => $today->endOfYear()->toDateString(),
                    'label' => 'This Year ('.$today->format('Y').')',
                ];

            case 'custom':
                if ($from && $to) {
                    $cFrom = CarbonImmutable::parse($from);
                    $cTo = CarbonImmutable::parse($to);
                    if ($cFrom->gt($cTo)) {
                        [$cFrom, $cTo] = [$cTo, $cFrom];
                    }

                    return [
                        'preset' => 'custom',
                        'from' => $cFrom->toDateString(),
                        'to' => $cTo->toDateString(),
                        'label' => 'Custom ('.$cFrom->format('d M Y').' - '.$cTo->format('d M Y').')',
                    ];
                }
                // Fallback to this_month if custom dates missing
                // no break

            case 'this_month':
            default:
                return [
                    'preset' => 'this_month',
                    'from' => $today->startOfMonth()->toDateString(),
                    'to' => $today->endOfMonth()->toDateString(),
                    'label' => 'This Month ('.$today->format('F Y').')',
                ];
        }
    }

    /**
     * Resolve the immediately preceding comparison period for a given range.
     *
     * @return array{preset: string, from: string, to: string, label: string}
     */
    public function resolvePreviousPeriod(string $preset, string $from, string $to): array
    {
        $cFrom = CarbonImmutable::parse($from);
        $cTo = CarbonImmutable::parse($to);

        switch ($preset) {
            case 'this_month':
                $prev = $cFrom->subMonth();

                return [
                    'preset' => 'last_month',
                    'from' => $prev->startOfMonth()->toDateString(),
                    'to' => $prev->endOfMonth()->toDateString(),
                    'label' => 'Prior Month ('.$prev->format('F Y').')',
                ];

            case 'last_month':
                $prev = $cFrom->subMonth();

                return [
                    'preset' => 'prior_month',
                    'from' => $prev->startOfMonth()->toDateString(),
                    'to' => $prev->endOfMonth()->toDateString(),
                    'label' => '2 Months Ago ('.$prev->format('F Y').')',
                ];

            case 'this_quarter':
                $prevQuarter = $cFrom->subQuarter();

                return [
                    'preset' => 'last_quarter',
                    'from' => $prevQuarter->firstOfQuarter()->toDateString(),
                    'to' => $prevQuarter->lastOfQuarter()->toDateString(),
                    'label' => 'Prior Quarter (Q'.$prevQuarter->quarter.' '.$prevQuarter->format('Y').')',
                ];

            case 'this_year':
                $prevYear = $cFrom->subYear();

                return [
                    'preset' => 'last_year',
                    'from' => $prevYear->startOfYear()->toDateString(),
                    'to' => $prevYear->endOfYear()->toDateString(),
                    'label' => 'Prior Year ('.$prevYear->format('Y').')',
                ];

            case 'custom':
            default:
                $days = $cFrom->diffInDays($cTo) + 1;
                $prevTo = $cFrom->subDay();
                $prevFrom = $prevTo->subDays($days - 1);

                return [
                    'preset' => 'custom_previous',
                    'from' => $prevFrom->toDateString(),
                    'to' => $prevTo->toDateString(),
                    'label' => 'Previous '.$days.' Days ('.$prevFrom->format('d M Y').' - '.$prevTo->format('d M Y').')',
                ];
        }
    }

    /**
     * Compute full Profit & Loss statement for the given period.
     * Strictly implements PRD §11.1, §11.2, §11.3 and Schema §8.2 source-record formulas.
     *
     * @return array<string, mixed>
     */
    public function calculate(string|CarbonInterface $from, string|CarbonInterface $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        // 1. Sales & Revenue Components (Non-cancelled sales in range - PRD §11.1 Lines A, B, E)
        $salesAggregate = Sale::where('status', '<>', 'cancelled')
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(id) AS invoice_count,
                COALESCE(SUM(subtotal), 0) AS gross_sales,
                COALESCE(SUM(discount_amount), 0) AS discounts,
                COALESCE(SUM(delivery_charge), 0) AS delivery_income,
                COALESCE(SUM(paid_total), 0) AS amount_received,
                COALESCE(SUM(due_amount), 0) AS amount_due
            ')
            ->first();

        $invoiceCount = (int) ($salesAggregate->invoice_count ?? 0);
        $grossSales = round((float) ($salesAggregate->gross_sales ?? 0), 2);
        $discounts = round((float) ($salesAggregate->discounts ?? 0), 2);
        $deliveryIncome = round((float) ($salesAggregate->delivery_income ?? 0), 2);
        $amountReceived = round((float) ($salesAggregate->amount_received ?? 0), 2);
        $amountDue = round((float) ($salesAggregate->amount_due ?? 0), 2);

        // 2. Returns (Attributed to return date's period - PRD §11.1 Line C)
        $returns = round((float) SaleReturn::whereBetween('returned_on', [$fromDate, $toDate])
            ->sum('return_value'), 2);

        // 3. Net Sales & Total Revenue
        // Line D: Net Sales = Gross Sales (A) - Discounts (B) - Returns (C)
        $netSales = round($grossSales - $discounts - $returns, 2);
        // Line F: Total Revenue = Net Sales (D) + Delivery Income (E)
        $totalRevenue = round($netSales + $deliveryIncome, 2);

        // 4. Cost of Goods Sold (COGS - PRD §11.1 Line G / Schema §8.2)
        // Cost of sold lines (using unit_cost snapshot on sale lines; never current product cost)
        $soldCost = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', '<>', 'cancelled')
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->sum(DB::raw('si.qty * si.unit_cost'));

        // Restocked returns cost (by return date)
        $restockedReturnCost = (float) DB::table('sale_return_items as ri')
            ->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->where('r.restock', 1)
            ->whereBetween('r.returned_on', [$fromDate, $toDate])
            ->sum(DB::raw('ri.qty * ri.unit_cost'));

        $cogs = round(max(0, $soldCost - $restockedReturnCost), 2);

        // 5. Gross Profit (Line H) & Margin
        $grossProfit = round($totalRevenue - $cogs, 2);
        $grossMarginPct = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 2)
            : 0.0;

        // 6. Operating Expenses by P&L Group (PRD §11.1 Lines I1, I2, J, M, N, O)
        $expenseGroups = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereNull('e.deleted_at')
            ->whereBetween('e.expense_date', [$fromDate, $toDate])
            ->select('c.pl_group', DB::raw('SUM(e.amount) as total'))
            ->groupBy('c.pl_group')
            ->pluck('total', 'pl_group')
            ->toArray();

        $facebookAds = round((float) ($expenseGroups['marketing_facebook'] ?? 0), 2);
        $otherMarketing = round((float) ($expenseGroups['marketing_other'] ?? 0), 2);
        $courierExpense = round((float) ($expenseGroups['courier'] ?? 0), 2);
        $packagingExpense = round((float) ($expenseGroups['packaging'] ?? 0), 2);
        $rentUtilitiesExpense = round((float) ($expenseGroups['rent_utilities'] ?? 0), 2);
        $otherExpense = round((float) ($expenseGroups['other'] ?? 0), 2);

        // 7. Payroll Expenses (PRD §11.1 Lines K & L)
        // K Salaries: months whose first day falls in range (accrual earned/payable, NOT payments)
        $salaries = round((float) SalaryMonth::whereBetween('period', [$fromDate, $toDate])
            ->sum('payable'), 2);

        // L Wages: accrued earnings in range (skipped if payroll.cost_price_includes_labour = 1)
        $costIncludesLabour = Setting::get('payroll.cost_price_includes_labour', '0');
        $isLabourInCost = ($costIncludesLabour === '1' || $costIncludesLabour === true || $costIncludesLabour === 1);
        $wages = round($this->payrollService->getEarnedWageExpense($fromDate, $toDate), 2);

        // P Total Operating Expenses
        $totalOperatingExpenses = round(
            $facebookAds
            + $otherMarketing
            + $courierExpense
            + $salaries
            + ($isLabourInCost ? 0 : $wages)
            + $packagingExpense
            + $rentUtilitiesExpense
            + $otherExpense,
            2
        );

        // 8. Net Profit (Line Q) & Margin
        $netProfit = round($grossProfit - $totalOperatingExpenses, 2);
        $netMarginPct = $totalRevenue > 0
            ? round(($netProfit / $totalRevenue) * 100, 2)
            : 0.0;

        // 9. Facebook Ads ROAS (Net Sales ÷ Ad Spend)
        // Zero ad spend returns null / "—", not infinity!
        $roas = $facebookAds > 0
            ? round($netSales / $facebookAds, 2)
            : null;

        $roasFormatted = $roas !== null
            ? number_format($roas, 2).'x'
            : '—';

        $adSpendPctSales = $netSales > 0
            ? round(($facebookAds / $netSales) * 100, 1)
            : 0.0;

        // 10. Check if date range is aligned to month boundary for salaries notice
        $cFrom = CarbonImmutable::parse($fromDate);
        $cTo = CarbonImmutable::parse($toDate);
        $salariesWholeMonthNotice = ($cFrom->day !== 1 || $cTo->day !== $cTo->endOfMonth()->day);

        // 11. Memo Section (PRD §11.2 - Informational only, not in profit calculation)
        $memo = $this->calculateMemoSection($fromDate, $toDate);

        // 12. Top 5 Profitable Products in Period
        $topProducts = $this->calculateTopProfitableProducts($fromDate, $toDate);

        // 13. Expense Breakdown for Visuals
        $expenseBreakdown = $this->calculateExpenseBreakdown([
            'facebook_ads' => $facebookAds,
            'other_marketing' => $otherMarketing,
            'courier' => $courierExpense,
            'salaries' => $salaries,
            'wages' => $wages,
            'packaging' => $packagingExpense,
            'rent_utilities' => $rentUtilitiesExpense,
            'other' => $otherExpense,
        ], $totalOperatingExpenses);

        // 14. Monthly Historical Trend (Last 6 months)
        $monthlyTrend = $this->calculateMonthlyTrend(CarbonImmutable::parse($toDate));

        // Exact line definitions for PRD §11.1 Statement Layout
        $statement = [
            'gross_sales' => [
                'code' => 'A',
                'label' => 'Gross sales (invoice subtotals)',
                'amount' => $grossSales,
            ],
            'discounts' => [
                'code' => 'B',
                'label' => '− Discounts',
                'amount' => $discounts,
            ],
            'returns' => [
                'code' => 'C',
                'label' => '− Sales returns / cancellations',
                'amount' => $returns,
            ],
            'net_sales' => [
                'code' => 'D',
                'label' => 'NET SALES (A − B − C)',
                'amount' => $netSales,
            ],
            'delivery_charges' => [
                'code' => 'E',
                'label' => '+ Delivery charges collected from customers',
                'amount' => $deliveryIncome,
            ],
            'total_revenue' => [
                'code' => 'F',
                'label' => 'TOTAL REVENUE (D + E)',
                'amount' => $totalRevenue,
            ],
            'cogs' => [
                'code' => 'G',
                'label' => 'Cost of items sold (qty × snapshot unit cost)',
                'amount' => $cogs,
            ],
            'gross_profit' => [
                'code' => 'H',
                'label' => 'GROSS PROFIT (F − G)',
                'amount' => $grossProfit,
                'margin_pct' => $grossMarginPct,
            ],
            'expenses' => [
                'facebook_ads' => [
                    'code' => 'I1',
                    'label' => 'Facebook Ads',
                    'amount' => $facebookAds,
                ],
                'other_marketing' => [
                    'code' => 'I2',
                    'label' => 'Other Marketing',
                    'amount' => $otherMarketing,
                ],
                'courier' => [
                    'code' => 'J',
                    'label' => 'Courier charges (Pathao + return costs)',
                    'amount' => $courierExpense,
                ],
                'salaries' => [
                    'code' => 'K',
                    'label' => 'Salaries (earned for period)',
                    'amount' => $salaries,
                ],
                'wages' => [
                    'code' => 'L',
                    'label' => 'Wages (earned for period)',
                    'amount' => $wages,
                    'is_excluded' => $isLabourInCost,
                ],
                'packaging' => [
                    'code' => 'M',
                    'label' => 'Packaging',
                    'amount' => $packagingExpense,
                ],
                'rent_utilities' => [
                    'code' => 'N',
                    'label' => 'Rent & utilities (rent, electricity, internet, gas/water)',
                    'amount' => $rentUtilitiesExpense,
                ],
                'other' => [
                    'code' => 'O',
                    'label' => 'Other expenses',
                    'amount' => $otherExpense,
                ],
                'total' => [
                    'code' => 'P',
                    'label' => 'TOTAL OPERATING EXPENSES (I1+I2+J+K+L+M+N+O)',
                    'amount' => $totalOperatingExpenses,
                ],
            ],
            'net_profit' => [
                'code' => 'Q',
                'label' => 'NET PROFIT / (LOSS) (H − P)',
                'amount' => $netProfit,
                'margin_pct' => $netMarginPct,
            ],
        ];

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'salaries_whole_month_notice' => $salariesWholeMonthNotice,
            ],
            'cost_includes_labour' => $isLabourInCost,
            'revenue' => [
                'invoice_count' => $invoiceCount,
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'returns' => $returns,
                'net_sales' => $netSales,
                'delivery_income' => $deliveryIncome,
                'total_revenue' => $totalRevenue,
                'amount_received' => $amountReceived,
                'amount_due' => $amountDue,
            ],
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'expenses' => [
                'facebook_ads' => $facebookAds,
                'other_marketing' => $otherMarketing,
                'courier' => $courierExpense,
                'salaries' => $salaries,
                'wages' => $wages,
                'packaging' => $packagingExpense,
                'rent_utilities' => $rentUtilitiesExpense,
                'other' => $otherExpense,
                'total' => $totalOperatingExpenses,
            ],
            'net_profit' => $netProfit,
            'net_margin_pct' => $netMarginPct,
            'marketing' => [
                'facebook_ads_spend' => $facebookAds,
                'net_sales' => $netSales,
                'roas' => $roas,
                'roas_formatted' => $roasFormatted,
                'ad_spend_pct_sales' => $adSpendPctSales,
            ],
            'statement' => $statement,
            'memo' => $memo,
            'top_products' => $topProducts,
            'expense_breakdown' => $expenseBreakdown,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /**
     * Compute comparative Profit & Loss with variance and percent change.
     *
     * @return array<string, mixed>
     */
    public function getComparative(string $from, string $to, string $prevFrom, string $prevTo): array
    {
        $current = $this->calculate($from, $to);
        $previous = $this->calculate($prevFrom, $prevTo);

        // Helper to compute variance & pct change
        $calcVariance = function (float $currVal, float $prevVal) {
            $diff = round($currVal - $prevVal, 2);
            $pct = $prevVal != 0
                ? round((($currVal - $prevVal) / abs($prevVal)) * 100, 1)
                : null;

            return [
                'diff' => $diff,
                'pct' => $pct,
                'pct_formatted' => $pct !== null ? ($pct > 0 ? '+'.$pct.'%' : $pct.'%') : '—',
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
            ];
        };

        // Attach comparison details to statement lines
        $statement = $current['statement'];

        // Revenue lines
        foreach (['gross_sales', 'discounts', 'returns', 'net_sales', 'delivery_charges', 'total_revenue', 'cogs', 'gross_profit'] as $key) {
            $currAmt = (float) $statement[$key]['amount'];
            $prevAmt = (float) $previous['statement'][$key]['amount'];
            $statement[$key]['previous'] = $prevAmt;
            $statement[$key]['variance'] = $calcVariance($currAmt, $prevAmt);
        }

        // Expense lines
        foreach ($statement['expenses'] as $expKey => $expData) {
            $currAmt = (float) $expData['amount'];
            $prevAmt = (float) $previous['statement']['expenses'][$expKey]['amount'];
            $statement['expenses'][$expKey]['previous'] = $prevAmt;
            $statement['expenses'][$expKey]['variance'] = $calcVariance($currAmt, $prevAmt);
        }

        // Net profit
        $currNet = (float) $statement['net_profit']['amount'];
        $prevNet = (float) $previous['statement']['net_profit']['amount'];
        $statement['net_profit']['previous'] = $prevNet;
        $statement['net_profit']['variance'] = $calcVariance($currNet, $prevNet);

        return [
            'current' => $current,
            'previous' => $previous,
            'statement' => $statement,
            'kpis' => [
                'net_profit' => [
                    'current' => $current['net_profit'],
                    'previous' => $previous['net_profit'],
                    'variance' => $calcVariance($current['net_profit'], $previous['net_profit']),
                ],
                'gross_margin_pct' => [
                    'current' => $current['gross_margin_pct'],
                    'previous' => $previous['gross_margin_pct'],
                    'diff' => round($current['gross_margin_pct'] - $previous['gross_margin_pct'], 2),
                ],
                'total_revenue' => [
                    'current' => $current['revenue']['total_revenue'],
                    'previous' => $previous['revenue']['total_revenue'],
                    'variance' => $calcVariance($current['revenue']['total_revenue'], $previous['revenue']['total_revenue']),
                ],
                'total_expenses' => [
                    'current' => $current['expenses']['total'],
                    'previous' => $previous['expenses']['total'],
                    'variance' => $calcVariance($current['expenses']['total'], $previous['expenses']['total']),
                ],
            ],
        ];
    }

    /**
     * Compute Memo Section (PRD §11.2 & Schema §8.7).
     *
     * @return array<string, mixed>
     */
    protected function calculateMemoSection(string $from, string $to): array
    {
        // 1. Leather purchased and consumed in period
        $leatherPurchases = DB::table('leather_purchases')
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(total_cost), 0) AS total_cost, COALESCE(SUM(qty_sqft), 0) AS qty_sqft, COALESCE(SUM(paid_amount), 0) AS paid_amount')
            ->first();

        $leatherPurchasedCost = round((float) ($leatherPurchases->total_cost ?? 0), 2);
        $leatherPurchasedSqft = round((float) ($leatherPurchases->qty_sqft ?? 0), 3);
        $leatherPurchasesPaid = round((float) ($leatherPurchases->paid_amount ?? 0), 2);

        $leatherConsumedSqft = round((float) DB::table('leather_consumptions')
            ->whereNull('deleted_at')
            ->whereBetween('consumption_date', [$from, $to])
            ->sum('qty_sqft'), 3);

        // Leather consumed valued at average cost using v_leather_stock
        $leatherConsumedValue = round((float) DB::table('leather_consumptions as lc')
            ->join('v_leather_stock as vs', 'vs.id', '=', 'lc.leather_item_id')
            ->whereNull('lc.deleted_at')
            ->whereBetween('lc.consumption_date', [$from, $to])
            ->sum(DB::raw('lc.qty_sqft * vs.avg_cost')), 2);

        // 2. Accessories purchased and consumed in period
        $accessoryPurchases = DB::table('accessory_purchases')
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(total_cost), 0) AS total_cost, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(paid_amount), 0) AS paid_amount')
            ->first();

        $accessoryPurchasedCost = round((float) ($accessoryPurchases->total_cost ?? 0), 2);
        $accessoryPurchasedQty = round((float) ($accessoryPurchases->qty ?? 0), 3);
        $accessoryPurchasesPaid = round((float) ($accessoryPurchases->paid_amount ?? 0), 2);

        $accessoryConsumedQty = round((float) DB::table('accessory_consumptions')
            ->whereNull('deleted_at')
            ->whereBetween('consumption_date', [$from, $to])
            ->sum('qty'), 3);

        $accessoryConsumedValue = round((float) DB::table('accessory_consumptions as ac')
            ->join('v_accessory_stock as vs', 'vs.id', '=', 'ac.accessory_item_id')
            ->whereNull('ac.deleted_at')
            ->whereBetween('ac.consumption_date', [$from, $to])
            ->sum(DB::raw('ac.qty * vs.avg_cost')), 2);

        // 3. Closing stock values (as of current stock: simple products + variants without double counting)
        $closingSimpleProducts = round((float) DB::table('products')
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('has_variants', 0)
            ->sum(DB::raw('stock_qty * cost_price')), 2);

        $closingVariants = round((float) DB::table('product_variants')
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->sum(DB::raw('stock_qty * cost_price')), 2);

        $closingProducts = round($closingSimpleProducts + $closingVariants, 2);

        $closingLeather = round((float) DB::table('v_leather_stock')->sum('stock_value'), 2);
        $closingAccessories = round((float) DB::table('v_accessory_stock')->sum('stock_value'), 2);
        $closingTotal = round($closingProducts + $closingLeather + $closingAccessories, 2);

        // 4. COD receivable from Pathao (delivered, not yet settled - Schema §8.6)
        $codReceivable = round((float) DB::table('courier_orders')
            ->where('status', 'delivered')
            ->where('cod_amount', '>', 0)
            ->whereNull('cod_settled_at')
            ->sum('cod_amount'), 2);

        // 5. Customer dues (unpaid invoice balances excluding COD in transit with courier)
        $customerDues = round((float) DB::table('sales')
            ->where('status', '<>', 'cancelled')
            ->where('payment_status', '<>', 'paid')
            ->whereNotIn('id', function ($query) {
                $query->select('sale_id')
                    ->from('courier_orders')
                    ->where('cod_amount', '>', 0)
                    ->whereNull('cod_settled_at');
            })
            ->sum('due_amount'), 2);

        // 6. Payroll payable / (advances)
        $employees = DB::table('employees as e')
            ->leftJoin(DB::raw('(SELECT employee_id, SUM(payable) as total_payable FROM salary_months GROUP BY employee_id) sm'), 'sm.employee_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT employee_id, SUM(amount) as total_paid FROM salary_payments GROUP BY employee_id) sp'), 'sp.employee_id', '=', 'e.id')
            ->selectRaw('e.id, (COALESCE(sm.total_payable, 0) - COALESCE(sp.total_paid, 0)) as balance')
            ->get();

        $workers = DB::table('workers as w')
            ->leftJoin(DB::raw('(SELECT worker_id, SUM(amount) as total_earned FROM worker_earnings GROUP BY worker_id) we'), 'we.worker_id', '=', 'w.id')
            ->leftJoin(DB::raw('(SELECT worker_id, SUM(amount) as total_paid FROM worker_payments GROUP BY worker_id) wp'), 'wp.worker_id', '=', 'w.id')
            ->selectRaw('w.id, (COALESCE(we.total_earned, 0) - COALESCE(wp.total_paid, 0)) as balance')
            ->get();

        $payrollOwed = 0.00;
        $payrollAdvances = 0.00;

        foreach ($employees as $emp) {
            $bal = (float) $emp->balance;
            if ($bal > 0) {
                $payrollOwed += $bal;
            } elseif ($bal < 0) {
                $payrollAdvances += abs($bal);
            }
        }

        foreach ($workers as $wkr) {
            $bal = (float) $wkr->balance;
            if ($bal > 0) {
                $payrollOwed += $bal;
            } elseif ($bal < 0) {
                $payrollAdvances += abs($bal);
            }
        }

        $payrollOwed = round($payrollOwed, 2);
        $payrollAdvances = round($payrollAdvances, 2);
        $payrollNet = round($payrollOwed - $payrollAdvances, 2);

        // 7. Cash Summary (PRD §11.2 & Schema §8.7)
        // Cash in:
        $customerPayments = round((float) DB::table('sale_payments')
            ->where('method', '<>', 'cod')
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount'), 2);

        $codSettlements = round((float) DB::table('courier_orders')
            ->whereNotNull('cod_settled_at')
            ->whereBetween('cod_settled_at', [$from, $to])
            ->sum('cod_settled_amount'), 2);

        $totalCashIn = round($customerPayments + $codSettlements, 2);

        // Cash out:
        $expensesPaid = round((float) DB::table('expenses')
            ->whereNull('deleted_at')
            ->where('fee_deducted_from_cod', 0)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount'), 2);

        $salariesPaid = round((float) DB::table('salary_payments')
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount'), 2);

        $wagesPaid = round((float) DB::table('worker_payments')
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount'), 2);

        $materialPurchasesPaid = round($leatherPurchasesPaid + $accessoryPurchasesPaid, 2);

        $refundsPaid = round((float) DB::table('sale_returns')
            ->whereBetween('returned_on', [$from, $to])
            ->sum('refunded_amount'), 2);

        $totalCashOut = round(
            $expensesPaid
            + $salariesPaid
            + $wagesPaid
            + $materialPurchasesPaid
            + $refundsPaid,
            2
        );

        $netCashMovement = round($totalCashIn - $totalCashOut, 2);

        return [
            'leather' => [
                'purchased_cost' => $leatherPurchasedCost,
                'purchased_sqft' => $leatherPurchasedSqft,
                'consumed_sqft' => $leatherConsumedSqft,
                'consumed_value' => $leatherConsumedValue,
            ],
            'accessories' => [
                'purchased_cost' => $accessoryPurchasedCost,
                'purchased_qty' => $accessoryPurchasedQty,
                'consumed_qty' => $accessoryConsumedQty,
                'consumed_value' => $accessoryConsumedValue,
            ],
            'closing_stock' => [
                'finished_products' => $closingProducts,
                'leather' => $closingLeather,
                'accessories' => $closingAccessories,
                'total' => $closingTotal,
            ],
            'cod_receivable' => $codReceivable,
            'customer_dues' => $customerDues,
            'payroll_balances' => [
                'owed' => $payrollOwed,
                'advances' => $payrollAdvances,
                'net' => $payrollNet,
            ],
            'cash_summary' => [
                'cash_in' => [
                    'customer_payments' => $customerPayments,
                    'cod_settlements' => $codSettlements,
                    'total' => $totalCashIn,
                ],
                'cash_out' => [
                    'expenses' => $expensesPaid,
                    'salaries' => $salariesPaid,
                    'wages' => $wagesPaid,
                    'material_purchases' => $materialPurchasesPaid,
                    'leather_purchases' => $leatherPurchasesPaid,
                    'accessory_purchases' => $accessoryPurchasesPaid,
                    'refunds' => $refundsPaid,
                    'total' => $totalCashOut,
                ],
                'net_movement' => $netCashMovement,
            ],
        ];
    }

    /**
     * Compute Top 5 Profitable Products in period (PRD §11.4).
     * Ranked by gross profit = revenue - COGS.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function calculateTopProfitableProducts(string $from, string $to): array
    {
        $products = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', '<>', 'cancelled')
            ->whereBetween('s.sale_date', [$from, $to])
            ->select(
                'si.product_id',
                'si.name',
                'p.sku',
                DB::raw('SUM(si.qty - si.returned_qty) AS qty_sold'),
                DB::raw('SUM((si.qty - si.returned_qty) * si.unit_price) AS revenue'),
                DB::raw('SUM((si.qty - si.returned_qty) * si.unit_cost) AS cogs'),
                DB::raw('(SUM((si.qty - si.returned_qty) * si.unit_price) - SUM((si.qty - si.returned_qty) * si.unit_cost)) AS gross_profit')
            )
            ->groupBy('si.product_id', 'si.name', 'p.sku')
            ->orderBy('gross_profit', 'desc')
            ->limit(5)
            ->get();

        return $products->map(function ($p) {
            $revenue = round((float) $p->revenue, 2);
            $cogs = round((float) $p->cogs, 2);
            $grossProfit = round((float) $p->gross_profit, 2);
            $marginPct = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

            return [
                'product_id' => $p->product_id,
                'name' => $p->name,
                'sku' => $p->sku ?? '—',
                'qty_sold' => (int) $p->qty_sold,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'profit_margin' => $marginPct,
            ];
        })->toArray();
    }

    /**
     * Compute Expense Breakdown for Chart (PRD §11.4).
     *
     * @param array<string, float> $expenses
     * @return array<int, array<string, mixed>>
     */
    protected function calculateExpenseBreakdown(array $expenses, float $totalOperatingExpenses): array
    {
        $labels = [
            'facebook_ads' => ['code' => 'I1', 'label' => 'Facebook Ads', 'color' => '#0B6E5E'],
            'other_marketing' => ['code' => 'I2', 'label' => 'Other Marketing', 'color' => '#1D5FA8'],
            'courier' => ['code' => 'J', 'label' => 'Courier Charges', 'color' => '#B26A00'],
            'salaries' => ['code' => 'K', 'label' => 'Salaries', 'color' => '#6B3FA0'],
            'wages' => ['code' => 'L', 'label' => 'Wages', 'color' => '#4A5957'],
            'packaging' => ['code' => 'M', 'label' => 'Packaging', 'color' => '#14602C'],
            'rent_utilities' => ['code' => 'N', 'label' => 'Rent & Utilities', 'color' => '#8E1B12'],
            'other' => ['code' => 'O', 'label' => 'Other Expenses', 'color' => '#5F6F6D'],
        ];

        $breakdown = [];
        foreach ($expenses as $key => $amount) {
            if ($amount <= 0 && $key !== 'facebook_ads' && $key !== 'salaries') {
                continue;
            }
            $pct = $totalOperatingExpenses > 0
                ? round(($amount / $totalOperatingExpenses) * 100, 1)
                : 0.0;

            $meta = $labels[$key] ?? ['code' => '', 'label' => ucfirst(str_replace('_', ' ', $key)), 'color' => '#5F6F6D'];

            $breakdown[] = [
                'key' => $key,
                'code' => $meta['code'],
                'label' => $meta['label'],
                'amount' => $amount,
                'percentage' => $pct,
                'color' => $meta['color'],
            ];
        }

        // Sort by amount descending
        usort($breakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $breakdown;
    }

    /**
     * Compute 6-Month Historical Revenue vs Expenses vs Net Profit.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function calculateMonthlyTrend(CarbonImmutable $currentDate): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $currentDate->subMonths($i);
            $from = $month->startOfMonth()->toDateString();
            $to = $month->endOfMonth()->toDateString();

            // Direct fast aggregate for trend chart
            $sales = Sale::where('status', '<>', 'cancelled')
                ->whereBetween('sale_date', [$from, $to])
                ->selectRaw('COALESCE(SUM(subtotal), 0) - COALESCE(SUM(discount_amount), 0) + COALESCE(SUM(delivery_charge), 0) AS rev')
                ->value('rev') ?? 0;

            $returns = SaleReturn::whereBetween('returned_on', [$from, $to])->sum('return_value') ?? 0;
            $monthRevenue = round((float) $sales - (float) $returns, 2);

            $expenses = DB::table('expenses')
                ->whereNull('deleted_at')
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount') ?? 0;

            $salaries = SalaryMonth::whereBetween('period', [$from, $to])->sum('payable') ?? 0;
            $wages = $this->payrollService->getEarnedWageExpense($from, $to);

            $monthExpenses = round((float) $expenses + (float) $salaries + (float) $wages, 2);

            // COGS estimate for trend
            $soldCost = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.status', '<>', 'cancelled')
                ->whereBetween('s.sale_date', [$from, $to])
                ->sum(DB::raw('si.qty * si.unit_cost')) ?? 0;

            $returnCost = DB::table('sale_return_items as ri')
                ->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
                ->where('r.restock', 1)
                ->whereBetween('r.returned_on', [$from, $to])
                ->sum(DB::raw('ri.qty * ri.unit_cost')) ?? 0;

            $monthCogs = max(0, (float) $soldCost - (float) $returnCost);
            $monthProfit = round($monthRevenue - $monthCogs - $monthExpenses, 2);

            $trend[] = [
                'month_key' => $month->format('Y-m'),
                'month_label' => $month->format('M Y'),
                'revenue' => $monthRevenue,
                'expenses' => $monthExpenses,
                'cogs' => round($monthCogs, 2),
                'net_profit' => $monthProfit,
            ];
        }

        return $trend;
    }

    /**
     * Fetch drill-down details for any clicked P&L line or memo figure.
     * Recomputes from source database records.
     *
     * @return array<string, mixed>
     */
    public function getDrilldown(string $type, string $from, string $to, array $params = []): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        switch ($type) {
            case 'gross_sales':
            case 'A':
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->whereBetween('sale_date', [$fromDate, $toDate])
                    ->latest('id')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'gross_sales',
                    'title' => 'Line A: Gross Sales (Non-Cancelled Invoices)',
                    'subtitle' => "Invoices dated between {$fromDate} and {$toDate}",
                    'count' => $sales->count(),
                    'total' => round((float) $sales->sum('subtotal'), 2),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'date' => $s->sale_date->toDateString(),
                        'customer' => $s->customer_name,
                        'phone' => $s->customer_phone,
                        'subtotal' => (float) $s->subtotal,
                        'discount' => (float) $s->discount_amount,
                        'grand_total' => (float) $s->grand_total,
                        'status' => $s->status,
                    ]),
                ];

            case 'discounts':
            case 'B':
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->where('discount_amount', '>', 0)
                    ->whereBetween('sale_date', [$fromDate, $toDate])
                    ->latest('id')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'discounts',
                    'title' => 'Line B: Invoices with Discounts',
                    'subtitle' => "Discounts given on sales between {$fromDate} and {$toDate}",
                    'count' => $sales->count(),
                    'total' => round((float) $sales->sum('discount_amount'), 2),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'date' => $s->sale_date->toDateString(),
                        'customer' => $s->customer_name,
                        'subtotal' => (float) $s->subtotal,
                        'discount_amount' => (float) $s->discount_amount,
                        'discount_type' => $s->discount_type ?? 'flat',
                        'grand_total' => (float) $s->grand_total,
                    ]),
                ];

            case 'returns':
            case 'C':
                $returns = SaleReturn::with(['sale:id,invoice_no,customer_name', 'items'])
                    ->whereBetween('returned_on', [$fromDate, $toDate])
                    ->latest('id')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'returns',
                    'title' => 'Line C: Sales Returns & Cancellations',
                    'subtitle' => "Returns processed between {$fromDate} and {$toDate}",
                    'count' => $returns->count(),
                    'total' => round((float) $returns->sum('return_value'), 2),
                    'records' => $returns->map(fn ($r) => [
                        'id' => $r->id,
                        'return_id' => 'RET-'.str_pad($r->id, 4, '0', STR_PAD_LEFT),
                        'invoice_no' => $r->sale?->invoice_no ?? '—',
                        'date' => $r->returned_on->toDateString(),
                        'customer' => $r->sale?->customer_name ?? '—',
                        'return_value' => (float) $r->return_value,
                        'refunded_amount' => (float) $r->refunded_amount,
                        'restock' => (bool) $r->restock,
                        'reason' => $r->reason,
                    ]),
                ];

            case 'delivery_charges':
            case 'E':
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->where('delivery_charge', '>', 0)
                    ->whereBetween('sale_date', [$fromDate, $toDate])
                    ->latest('id')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'delivery_charges',
                    'title' => 'Line E: Delivery Charges Collected',
                    'subtitle' => "Customer delivery charges collected between {$fromDate} and {$toDate}",
                    'count' => $sales->count(),
                    'total' => round((float) $sales->sum('delivery_charge'), 2),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'date' => $s->sale_date->toDateString(),
                        'customer' => $s->customer_name,
                        'delivery_charge' => (float) $s->delivery_charge,
                        'grand_total' => (float) $s->grand_total,
                    ]),
                ];

            case 'cogs':
            case 'G':
                $soldItems = DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', '<>', 'cancelled')
                    ->whereBetween('s.sale_date', [$fromDate, $toDate])
                    ->select('si.id', 'si.name', 's.invoice_no', 's.sale_date as date', 'si.qty', 'si.unit_cost', DB::raw('(si.qty * si.unit_cost) as total_cost'))
                    ->latest('s.id')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'cogs',
                    'title' => 'Line G: Cost of Items Sold (COGS)',
                    'subtitle' => "Snapshot cost at moment of sale between {$fromDate} and {$toDate}",
                    'count' => $soldItems->count(),
                    'total' => round((float) $soldItems->sum('total_cost'), 2),
                    'records' => $soldItems->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'invoice_no' => $item->invoice_no,
                        'date' => $item->date,
                        'qty' => (int) $item->qty,
                        'unit_cost' => (float) $item->unit_cost,
                        'total_cost' => round((float) $item->total_cost, 2),
                        'kind' => 'sale',
                    ]),
                ];

            // Operating Expense Lines
            case 'facebook_ads':
            case 'I1':
            case 'other_marketing':
            case 'I2':
            case 'courier':
            case 'J':
            case 'packaging':
            case 'M':
            case 'rent_utilities':
            case 'N':
            case 'other_expenses':
            case 'O':
                $groupMap = [
                    'facebook_ads' => 'marketing_facebook',
                    'I1' => 'marketing_facebook',
                    'other_marketing' => 'marketing_other',
                    'I2' => 'marketing_other',
                    'courier' => 'courier',
                    'J' => 'courier',
                    'packaging' => 'packaging',
                    'M' => 'packaging',
                    'rent_utilities' => 'rent_utilities',
                    'N' => 'rent_utilities',
                    'other_expenses' => 'other',
                    'O' => 'other',
                ];

                $targetGroup = $groupMap[$type] ?? 'other';
                $expenses = Expense::with('category')
                    ->whereHas('category', fn ($q) => $q->where('pl_group', $targetGroup))
                    ->whereBetween('expense_date', [$fromDate, $toDate])
                    ->latest('expense_date')
                    ->limit(200)
                    ->get();

                return [
                    'type' => $type,
                    'title' => 'Operating Expense: '.ucfirst(str_replace('_', ' ', $targetGroup)),
                    'subtitle' => "Expense vouchers recorded between {$fromDate} and {$toDate}",
                    'count' => $expenses->count(),
                    'total' => round((float) $expenses->sum('amount'), 2),
                    'records' => $expenses->map(fn ($e) => [
                        'id' => $e->id,
                        'category' => $e->category?->name ?? 'General',
                        'date' => $e->expense_date->toDateString(),
                        'vendor' => $e->vendor ?? '—',
                        'amount' => (float) $e->amount,
                        'method' => $e->payment_method ?? 'cash',
                        'note' => $e->note ?? '',
                    ]),
                ];

            case 'salaries':
            case 'K':
                $months = SalaryMonth::with('employee')
                    ->whereBetween('period', [$fromDate, $toDate])
                    ->latest('period')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'salaries',
                    'title' => 'Line K: Salaries Earned for Period',
                    'subtitle' => "Salary sheets with month period in {$fromDate} to {$toDate}",
                    'count' => $months->count(),
                    'total' => round((float) $months->sum('payable'), 2),
                    'records' => $months->map(fn ($m) => [
                        'id' => $m->id,
                        'employee' => $m->employee?->name ?? 'Staff',
                        'period' => CarbonImmutable::parse($m->period)->format('F Y'),
                        'base_salary' => (float) $m->base_salary,
                        'bonus' => (float) ($m->bonus ?? 0),
                        'deduction' => (float) ($m->deduction ?? 0),
                        'payable' => (float) $m->payable,
                    ]),
                ];

            case 'wages':
            case 'L':
                $earnings = WorkerEarning::with('worker')
                    ->whereBetween('work_date', [$fromDate, $toDate])
                    ->latest('work_date')
                    ->limit(200)
                    ->get();

                return [
                    'type' => 'wages',
                    'title' => 'Line L: Wages Earned in Period',
                    'subtitle' => "Worker piece-rate/daily earnings between {$fromDate} and {$toDate}",
                    'count' => $earnings->count(),
                    'total' => round((float) $earnings->sum('amount'), 2),
                    'records' => $earnings->map(fn ($w) => [
                        'id' => $w->id,
                        'worker' => $w->worker?->name ?? 'Worker',
                        'date' => $w->work_date->toDateString(),
                        'amount' => (float) $w->amount,
                        'wage_basis' => $w->worker?->wage_basis ?? 'piece_rate',
                        'note' => $w->note ?? '',
                    ]),
                ];

            // Memo Section Drilldowns
            case 'leather_purchased':
                $purchases = LeatherPurchase::with('item')
                    ->whereNull('deleted_at')
                    ->whereBetween('purchase_date', [$fromDate, $toDate])
                    ->latest('purchase_date')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'leather_purchased',
                    'title' => 'Memo: Leather Purchases',
                    'subtitle' => "Purchases made between {$fromDate} and {$toDate} (inventory asset, not P&L expense)",
                    'count' => $purchases->count(),
                    'total' => round((float) $purchases->sum('total_cost'), 2),
                    'records' => $purchases->map(fn ($p) => [
                        'id' => $p->id,
                        'item' => $p->item?->name ?? 'Leather',
                        'colour' => $p->item?->colour_grade ?? '—',
                        'supplier' => $p->supplier ?? '—',
                        'date' => $p->purchase_date->toDateString(),
                        'qty_sqft' => (float) $p->qty_sqft,
                        'rate' => (float) $p->rate_per_sqft,
                        'total_cost' => (float) $p->total_cost,
                        'paid_amount' => (float) $p->paid_amount,
                    ]),
                ];

            case 'leather_consumed':
                $consumptions = LeatherConsumption::with('item')
                    ->whereNull('deleted_at')
                    ->whereBetween('consumption_date', [$fromDate, $toDate])
                    ->latest('consumption_date')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'leather_consumed',
                    'title' => 'Memo: Leather Consumed',
                    'subtitle' => "Raw hides consumed in production between {$fromDate} and {$toDate}",
                    'count' => $consumptions->count(),
                    'total' => round((float) $consumptions->sum('qty_sqft'), 3),
                    'records' => $consumptions->map(fn ($c) => [
                        'id' => $c->id,
                        'item' => $c->item?->name ?? 'Leather',
                        'colour' => $c->item?->colour_grade ?? '—',
                        'date' => $c->consumption_date->toDateString(),
                        'qty_sqft' => (float) $c->qty_sqft,
                        'note' => $c->note ?? '',
                    ]),
                ];

            case 'closing_stock_products':
                $products = Product::whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('stock_qty', '>', 0)
                    ->orderBy('stock_qty', 'desc')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'closing_stock_products',
                    'title' => 'Memo: Finished Products In Stock',
                    'subtitle' => 'Valued at unit cost price',
                    'count' => $products->count(),
                    'total' => round((float) $products->sum(fn ($p) => $p->stock_qty * $p->cost_price), 2),
                    'records' => $products->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'stock_qty' => (int) $p->stock_qty,
                        'cost_price' => (float) $p->cost_price,
                        'valuation' => round((float) ($p->stock_qty * $p->cost_price), 2),
                    ]),
                ];

            case 'cod_receivable':
                $orders = CourierOrder::with('sale')
                    ->where('status', 'delivered')
                    ->where('cod_amount', '>', 0)
                    ->whereNull('cod_settled_at')
                    ->latest('id')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'cod_receivable',
                    'title' => 'Memo: Pathao COD Receivable',
                    'subtitle' => 'Parcels marked delivered by Pathao but not yet settled to bank',
                    'count' => $orders->count(),
                    'total' => round((float) $orders->sum('cod_amount'), 2),
                    'records' => $orders->map(fn ($o) => [
                        'id' => $o->id,
                        'consignment_id' => $o->consignment_id ?? '—',
                        'invoice_no' => $o->sale?->invoice_no ?? $o->merchant_order_id,
                        'customer' => $o->recipient_name ?? $o->sale?->customer_name,
                        'phone' => $o->recipient_phone ?? $o->sale?->customer_phone,
                        'cod_amount' => (float) $o->cod_amount,
                        'status' => $o->status,
                    ]),
                ];

            case 'customer_dues':
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->where('payment_status', '<>', 'paid')
                    ->whereNotIn('id', function ($query) {
                        $query->select('sale_id')
                            ->from('courier_orders')
                            ->where('cod_amount', '>', 0)
                            ->whereNull('cod_settled_at');
                    })
                    ->latest('id')
                    ->limit(150)
                    ->get();

                return [
                    'type' => 'customer_dues',
                    'title' => 'Memo: Customer Due Balances',
                    'subtitle' => 'Unpaid sales balances (excluding COD in transit with courier)',
                    'count' => $sales->count(),
                    'total' => round((float) $sales->sum('due_amount'), 2),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'date' => $s->sale_date->toDateString(),
                        'customer' => $s->customer_name,
                        'phone' => $s->customer_phone,
                        'grand_total' => (float) $s->grand_total,
                        'paid_total' => (float) $s->paid_total,
                        'due_amount' => (float) $s->due_amount,
                        'payment_status' => $s->payment_status,
                    ]),
                ];

            default:
                return [
                    'type' => 'unknown',
                    'title' => 'Source Records',
                    'subtitle' => '',
                    'count' => 0,
                    'total' => 0.00,
                    'records' => [],
                ];
        }
    }

    /**
     * Generate Excel spreadsheet workbook using PhpSpreadsheet (PRD §11).
     * Exact PRD §11 statement lines, memo section, and top products.
     */
    public function exportExcel(string|CarbonInterface $from, string|CarbonInterface $to, ?array $comparative = null): Spreadsheet
    {
        $current = $this->calculate($from, $to);
        $statement = $current['statement'];
        $memo = $current['memo'];
        $topProducts = $current['top_products'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Profit & Loss');

        // Brand Banner
        $sheet->setCellValue('A1', 'TASA POS — OWNER PROFIT & LOSS STATEMENT');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $sheet->setCellValue('A2', 'Period: '.$from.' to '.$to.' | Generated: '.now()->format('d M Y H:i'));
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('5F6F6D'));

        // Table Header
        $row = 4;
        $sheet->setCellValue('A'.$row, 'LINE');
        $sheet->setCellValue('B'.$row, 'DESCRIPTION');
        $sheet->setCellValue('C'.$row, 'AMOUNT (BDT)');
        $sheet->setCellValue('D'.$row, 'NOTES / FORMULA');

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getStyle($col.$row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0B6E5E');
        }

        // Section 1: REVENUE
        $row++;
        $sheet->setCellValue('B'.$row, 'REVENUE');
        $sheet->getStyle('B'.$row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $lines = [
            ['A', 'Gross sales (invoice subtotals)', $statement['gross_sales']['amount'], 'Non-cancelled sales in period'],
            ['B', '− Discounts', $statement['discounts']['amount'], 'Invoice discounts'],
            ['C', '− Sales returns / cancellations', $statement['returns']['amount'], 'Returns in period (by return date)'],
            ['D', 'NET SALES', $statement['net_sales']['amount'], 'A − B − C'],
            ['E', '+ Delivery charges collected from customers', $statement['delivery_charges']['amount'], 'Delivery fees charged to customers'],
            ['F', 'TOTAL REVENUE', $statement['total_revenue']['amount'], 'D + E'],
        ];

        foreach ($lines as [$code, $desc, $amt, $note]) {
            $row++;
            $sheet->setCellValue('A'.$row, $code);
            $sheet->setCellValue('B'.$row, $desc);
            $sheet->setCellValue('C'.$row, $amt);
            $sheet->setCellValue('D'.$row, $note);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

            if (in_array($code, ['D', 'F'])) {
                $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            }
        }

        // Section 2: COST OF GOODS SOLD
        $row += 2;
        $sheet->setCellValue('B'.$row, 'COST OF GOODS SOLD');
        $sheet->getStyle('B'.$row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $cogsLines = [
            ['G', 'Cost of items sold (qty × snapshot unit cost)', $statement['cogs']['amount'], 'Sold cost snapshot − restocked return cost snapshot'],
            ['H', 'GROSS PROFIT', $statement['gross_profit']['amount'], 'F − G (Gross Margin: '.$current['gross_margin_pct'].'%)'],
        ];

        foreach ($cogsLines as [$code, $desc, $amt, $note]) {
            $row++;
            $sheet->setCellValue('A'.$row, $code);
            $sheet->setCellValue('B'.$row, $desc);
            $sheet->setCellValue('C'.$row, $amt);
            $sheet->setCellValue('D'.$row, $note);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            if ($code === 'H') {
                $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            }
        }

        // Section 3: OPERATING EXPENSES
        $row += 2;
        $sheet->setCellValue('B'.$row, 'OPERATING EXPENSES');
        $sheet->getStyle('B'.$row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $expLines = [
            ['I1', 'Facebook Ads', $statement['expenses']['facebook_ads']['amount'], 'ROAS: '.$current['marketing']['roas_formatted']],
            ['I2', 'Other marketing', $statement['expenses']['other_marketing']['amount'], 'Brand, influencer, printing'],
            ['J', 'Courier charges', $statement['expenses']['courier']['amount'], 'Pathao delivery charges & return parcel costs'],
            ['K', 'Salaries (earned for period)', $statement['expenses']['salaries']['amount'], 'Monthly payroll payable'],
            ['L', 'Wages (earned for period)', $statement['expenses']['wages']['amount'], $current['cost_includes_labour'] ? 'Excluded (Cost price includes labour = Yes)' : 'Accrued wage earnings'],
            ['M', 'Packaging', $statement['expenses']['packaging']['amount'], 'Boxes, tags, bags'],
            ['N', 'Rent & utilities', $statement['expenses']['rent_utilities']['amount'], 'Rent, electricity, internet, gas/water'],
            ['O', 'Other expenses', $statement['expenses']['other']['amount'], 'Transport, tools, repairs, miscellaneous'],
            ['P', 'TOTAL OPERATING EXPENSES', $statement['expenses']['total']['amount'], 'I1 + I2 + J + K + L + M + N + O'],
        ];

        foreach ($expLines as [$code, $desc, $amt, $note]) {
            $row++;
            $sheet->setCellValue('A'.$row, $code);
            $sheet->setCellValue('B'.$row, $desc);
            $sheet->setCellValue('C'.$row, $amt);
            $sheet->setCellValue('D'.$row, $note);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            if ($code === 'P') {
                $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            }
        }

        // Section 4: NET PROFIT / (LOSS)
        $row += 2;
        $sheet->setCellValue('A'.$row, 'Q');
        $sheet->setCellValue('B'.$row, 'NET PROFIT / (LOSS)');
        $sheet->setCellValue('C'.$row, $statement['net_profit']['amount']);
        $sheet->setCellValue('D'.$row, 'H − P (Net Margin: '.$current['net_margin_pct'].'%)');
        $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Section 5: MEMO SECTION
        $row += 3;
        $sheet->setCellValue('B'.$row, 'MEMO SECTION (Informational — Not in Profit Calculation)');
        $sheet->getStyle('B'.$row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $memoLines = [
            ['M-1', 'Leather Purchased', $memo['leather']['purchased_cost'], $memo['leather']['purchased_sqft'].' sq ft purchased'],
            ['M-2', 'Leather Consumed', $memo['leather']['consumed_value'], $memo['leather']['consumed_sqft'].' sq ft consumed at avg cost'],
            ['M-3', 'Accessories Purchased', $memo['accessories']['purchased_cost'], $memo['accessories']['purchased_qty'].' items purchased'],
            ['M-4', 'Accessories Consumed', $memo['accessories']['consumed_value'], $memo['accessories']['consumed_qty'].' items consumed'],
            ['M-5', 'Closing Stock: Finished Products', $memo['closing_stock']['finished_products'], 'Products in stock valuation'],
            ['M-6', 'Closing Stock: Leather', $memo['closing_stock']['leather'], 'Raw hides stock valuation'],
            ['M-7', 'Closing Stock: Accessories', $memo['closing_stock']['accessories'], 'Accessories stock valuation'],
            ['M-8', 'Closing Stock: Total Inventory', $memo['closing_stock']['total'], 'Combined total inventory asset'],
            ['M-9', 'COD Receivable from Pathao', $memo['cod_receivable'], 'Delivered parcels awaiting settlement'],
            ['M-10', 'Customer Dues', $memo['customer_dues'], 'Unpaid invoice balances'],
            ['M-11', 'Payroll Payable Owed', $memo['payroll_balances']['owed'], 'Amount owed to staff & workers'],
            ['M-12', 'Payroll Advances (Overpaid)', $memo['payroll_balances']['advances'], 'Overpaid advances owed to Tasa'],
            ['M-13', 'Cash In (Customer Payments + COD)', $memo['cash_summary']['cash_in']['total'], 'Actual cash collected in period'],
            ['M-14', 'Cash Out (Expenses + Payroll + Materials + Refunds)', $memo['cash_summary']['cash_out']['total'], 'Actual cash disbursed in period'],
            ['M-15', 'Net Cash Movement', $memo['cash_summary']['net_movement'], 'Cash In − Cash Out'],
        ];

        foreach ($memoLines as [$code, $desc, $amt, $note]) {
            $row++;
            $sheet->setCellValue('A'.$row, $code);
            $sheet->setCellValue('B'.$row, $desc);
            $sheet->setCellValue('C'.$row, $amt);
            $sheet->setCellValue('D'.$row, $note);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            if (in_array($code, ['M-8', 'M-15'])) {
                $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            }
        }

        // Auto-fit columns
        foreach (range('A', 'D') as $colId) {
            $sheet->getColumnDimension($colId)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Generate formatted PDF document via DomPDF (PRD §11).
     */
    public function exportPdf(string|CarbonInterface $from, string|CarbonInterface $to, ?array $comparative = null): \Barryvdh\DomPDF\PDF
    {
        $current = $this->calculate($from, $to);

        $pdf = Pdf::loadView('reports.profit-loss-pdf', [
            'from' => $from,
            'to' => $to,
            'current' => $current,
            'comparative' => $comparative,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
