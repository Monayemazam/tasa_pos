<?php

namespace App\Services\Reports;

use App\Models\CourierOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesReportService
{
    /**
     * Resolve date range from preset or custom inputs.
     * Default is 'this_month' (PRD §6.5).
     *
     * @return array{preset: string, from: string, to: string, label: string}
     */
    public function resolveDateRange(?string $preset, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::now();

        switch ($preset) {
            case 'today':
                return [
                    'preset' => 'today',
                    'from' => $today->toDateString(),
                    'to' => $today->toDateString(),
                    'label' => 'Today ('.$today->format('d M Y').')',
                ];
            case 'yesterday':
                $y = $today->subDay();

                return [
                    'preset' => 'yesterday',
                    'from' => $y->toDateString(),
                    'to' => $y->toDateString(),
                    'label' => 'Yesterday ('.$y->format('d M Y').')',
                ];
            case 'this_week':
                $startOfWeek = $today->startOfWeek();

                return [
                    'preset' => 'this_week',
                    'from' => $startOfWeek->toDateString(),
                    'to' => $today->toDateString(),
                    'label' => 'This Week ('.$startOfWeek->format('d M').' - '.$today->format('d M Y').')',
                ];
            case 'last_month':
                $lastMonth = $today->subMonth();

                return [
                    'preset' => 'last_month',
                    'from' => $lastMonth->startOfMonth()->toDateString(),
                    'to' => $lastMonth->endOfMonth()->toDateString(),
                    'label' => 'Last Month ('.$lastMonth->format('F Y').')',
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
                // fallback to this_month if custom dates missing
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
     * Summary cards for Sales Report:
     * invoice count, gross sales, discounts, returns, net sales, amount received, amount due.
     * Cancelled sales are excluded; returns reduce revenue in return date's period.
     *
     * @return array<string, mixed>
     */
    public function getSummary(string|CarbonInterface $from, string|CarbonInterface $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $salesAgg = Sale::where('status', '<>', 'cancelled')
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(id) AS invoice_count,
                COALESCE(SUM(subtotal), 0) AS gross_sales,
                COALESCE(SUM(discount_amount), 0) AS discounts,
                COALESCE(SUM(delivery_charge), 0) AS delivery_charge,
                COALESCE(SUM(grand_total), 0) AS grand_total,
                COALESCE(SUM(paid_total), 0) AS amount_received,
                COALESCE(SUM(due_amount), 0) AS amount_due
            ')
            ->first();

        $invoiceCount = (int) ($salesAgg->invoice_count ?? 0);
        $grossSales = round((float) ($salesAgg->gross_sales ?? 0), 2);
        $discounts = round((float) ($salesAgg->discounts ?? 0), 2);
        $deliveryCharge = round((float) ($salesAgg->delivery_charge ?? 0), 2);
        $grandTotal = round((float) ($salesAgg->grand_total ?? 0), 2);
        $amountReceived = round((float) ($salesAgg->amount_received ?? 0), 2);
        $amountDue = round((float) ($salesAgg->amount_due ?? 0), 2);

        // Returns in return date's period
        $returns = round((float) SaleReturn::whereBetween('returned_on', [$fromDate, $toDate])
            ->sum('return_value'), 2);

        $netSales = round($grossSales - $discounts - $returns, 2);

        return [
            'invoice_count' => $invoiceCount,
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'returns' => $returns,
            'net_sales' => $netSales,
            'delivery_charge' => $deliveryCharge,
            'grand_total' => $grandTotal,
            'amount_received' => $amountReceived,
            'amount_due' => $amountDue,
        ];
    }

    /**
     * By-product table:
     * product, quantity sold, revenue, COGS and gross profit.
     * COGS uses the unit_cost snapshot on sale lines (Query §8.1).
     * If $includeCostProfit is false, cost and profit columns are strictly omitted from response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByProduct(string|CarbonInterface $from, string|CarbonInterface $to, bool $includeCostProfit = false): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'si.product_variant_id')
            ->where('s.status', '<>', 'cancelled')
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->selectRaw('
                si.product_id,
                si.product_variant_id,
                si.name,
                COALESCE(si.variant_sku, pv.sku, p.sku) AS sku,
                si.variant_name,
                p.name AS parent_name,
                SUM(si.qty - si.returned_qty) AS qty_sold,
                SUM((si.qty - si.returned_qty) * si.unit_price) AS revenue,
                SUM((si.qty - si.returned_qty) * si.unit_cost) AS cogs
            ')
            ->groupBy(
                'si.product_id',
                'si.product_variant_id',
                'si.name',
                'si.variant_sku',
                'pv.sku',
                'p.sku',
                'si.variant_name',
                'p.name'
            )
            ->orderByDesc('qty_sold')
            ->get();

        return $rows->map(function ($row) use ($includeCostProfit) {
            $qtySold = (int) $row->qty_sold;
            $revenue = round((float) $row->revenue, 2);

            $item = [
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'name' => $row->name,
                'parent_name' => $row->parent_name ?? $row->name,
                'variant_name' => $row->variant_name,
                'sku' => $row->sku ?? 'CUSTOM',
                'qty_sold' => $qtySold,
                'revenue' => $revenue,
            ];

            if ($includeCostProfit) {
                $cogs = round((float) $row->cogs, 2);
                $grossProfit = round($revenue - $cogs, 2);
                $profitMargin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

                $item['cogs'] = $cogs;
                $item['gross_profit'] = $grossProfit;
                $item['profit_margin'] = $profitMargin;
            }

            return $item;
        })->toArray();
    }

    /**
     * Daily trend view:
     * by day, invoice count, gross sales, discounts, returns, net sales, and optional cost/profit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByDay(string|CarbonInterface $from, string|CarbonInterface $to, bool $includeCostProfit = false): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        // 1. Sales by date
        $salesByDate = DB::table('sales')
            ->where('status', '<>', 'cancelled')
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('
                sale_date,
                COUNT(id) AS invoice_count,
                COALESCE(SUM(subtotal), 0) AS gross_sales,
                COALESCE(SUM(discount_amount), 0) AS discounts,
                COALESCE(SUM(paid_total), 0) AS amount_received,
                COALESCE(SUM(due_amount), 0) AS amount_due
            ')
            ->groupBy('sale_date')
            ->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->sale_date)->toDateString());

        // 2. Returns by date
        $returnsByDate = DB::table('sale_returns')
            ->whereBetween('returned_on', [$fromDate, $toDate])
            ->selectRaw('returned_on, COALESCE(SUM(return_value), 0) AS returns_amount')
            ->groupBy('returned_on')
            ->pluck('returns_amount', 'returned_on')
            ->toArray();

        // 3. Optional COGS by date
        $cogsByDate = [];
        if ($includeCostProfit) {
            $cogsRows = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.status', '<>', 'cancelled')
                ->whereBetween('s.sale_date', [$fromDate, $toDate])
                ->selectRaw('
                    s.sale_date,
                    SUM((si.qty - si.returned_qty) * si.unit_cost) AS cogs
                ')
                ->groupBy('s.sale_date')
                ->get();

            foreach ($cogsRows as $cr) {
                $cogsByDate[CarbonImmutable::parse($cr->sale_date)->toDateString()] = round((float) $cr->cogs, 2);
            }
        }

        // Merge all dates in range that have activity
        $allDates = array_unique(array_merge(
            $salesByDate->keys()->toArray(),
            array_keys($returnsByDate)
        ));
        sort($allDates);

        $results = [];
        foreach ($allDates as $d) {
            $s = $salesByDate->get($d);
            $gross = $s ? round((float) $s->gross_sales, 2) : 0.00;
            $disc = $s ? round((float) $s->discounts, 2) : 0.00;
            $ret = round((float) ($returnsByDate[$d] ?? 0), 2);
            $net = round($gross - $disc - $ret, 2);

            $dayData = [
                'date' => $d,
                'invoice_count' => $s ? (int) $s->invoice_count : 0,
                'gross_sales' => $gross,
                'discounts' => $disc,
                'returns' => $ret,
                'net_sales' => $net,
                'amount_received' => $s ? round((float) $s->amount_received, 2) : 0.00,
                'amount_due' => $s ? round((float) $s->amount_due, 2) : 0.00,
            ];

            if ($includeCostProfit) {
                $cogs = $cogsByDate[$d] ?? 0.00;
                $profit = round($net - $cogs, 2);
                $dayData['cogs'] = $cogs;
                $dayData['gross_profit'] = $profit;
            }

            $results[] = $dayData;
        }

        return $results;
    }

    /**
     * Breakdown by payment method.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByPaymentMethod(string|CarbonInterface $from, string|CarbonInterface $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $rows = DB::table('sale_payments as sp')
            ->join('sales as s', 's.id', '=', 'sp.sale_id')
            ->where('s.status', '<>', 'cancelled')
            ->whereBetween('sp.paid_on', [$fromDate, $toDate])
            ->selectRaw('
                sp.method,
                COUNT(sp.id) AS payment_count,
                COALESCE(SUM(sp.amount), 0) AS total_amount
            ')
            ->groupBy('sp.method')
            ->orderByDesc('total_amount')
            ->get();

        $grandTotal = (float) $rows->sum('total_amount');

        return $rows->map(function ($row) use ($grandTotal) {
            $amount = round((float) $row->total_amount, 2);
            $pct = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 1) : 0.0;

            return [
                'method' => $row->method,
                'method_label' => ucfirst(str_replace('_', ' ', $row->method)),
                'payment_count' => (int) $row->payment_count,
                'total_amount' => $amount,
                'percentage' => $pct,
            ];
        })->toArray();
    }

    /**
     * Breakdown by courier status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByCourierStatus(string|CarbonInterface $from, string|CarbonInterface $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $rows = DB::table('courier_orders as co')
            ->join('sales as s', 's.id', '=', 'co.sale_id')
            ->where('s.status', '<>', 'cancelled')
            ->whereBetween('s.sale_date', [$fromDate, $toDate])
            ->selectRaw('
                co.status,
                COUNT(co.id) AS order_count,
                COALESCE(SUM(s.grand_total), 0) AS total_sales,
                COALESCE(SUM(co.cod_amount), 0) AS cod_amount
            ')
            ->groupBy('co.status')
            ->orderByDesc('order_count')
            ->get();

        return $rows->map(function ($row) {
            return [
                'status' => $row->status,
                'status_label' => ucfirst(str_replace('_', ' ', $row->status)),
                'order_count' => (int) $row->order_count,
                'total_sales' => round((float) $row->total_sales, 2),
                'cod_amount' => round((float) $row->cod_amount, 2),
            ];
        })->toArray();
    }

    /**
     * Top customers in period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopCustomers(string|CarbonInterface $from, string|CarbonInterface $to, int $limit = 10): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $rows = DB::table('sales')
            ->where('status', '<>', 'cancelled')
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('
                customer_id,
                customer_name,
                customer_phone,
                COUNT(id) AS invoice_count,
                COALESCE(SUM(grand_total), 0) AS total_spent,
                COALESCE(SUM(paid_total), 0) AS amount_paid,
                COALESCE(SUM(due_amount), 0) AS amount_due
            ')
            ->groupBy('customer_id', 'customer_name', 'customer_phone')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'customer_phone' => $row->customer_phone,
                'invoice_count' => (int) $row->invoice_count,
                'total_spent' => round((float) $row->total_spent, 2),
                'amount_paid' => round((float) $row->amount_paid, 2),
                'amount_due' => round((float) $row->amount_due, 2),
            ];
        })->toArray();
    }

    /**
     * Drill-down into source records supporting any summary card, product row, or day.
     *
     * @return array<string, mixed>
     */
    public function getDrilldown(string $type, string|CarbonInterface $from, string|CarbonInterface $to, array $params = [], bool $includeCostProfit = false): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        switch ($type) {
            case 'invoices':
            case 'gross_sales':
            case 'discounts':
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->whereBetween('sale_date', [$fromDate, $toDate])
                    ->with(['courierOrder:id,sale_id,status,consignment_id'])
                    ->latest('id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => $type,
                    'title' => 'Invoices in Period ('.$fromDate.' to '.$toDate.')',
                    'count' => $sales->count(),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'sale_date' => $s->sale_date->toDateString(),
                        'customer_name' => $s->customer_name,
                        'customer_phone' => $s->customer_phone,
                        'subtotal' => (float) $s->subtotal,
                        'discount_amount' => (float) $s->discount_amount,
                        'delivery_charge' => (float) $s->delivery_charge,
                        'grand_total' => (float) $s->grand_total,
                        'paid_total' => (float) $s->paid_total,
                        'due_amount' => (float) $s->due_amount,
                        'payment_status' => $s->payment_status,
                        'courier_status' => $s->courierOrder?->status ?? 'none',
                    ]),
                ];

            case 'returns':
                $returns = SaleReturn::whereBetween('returned_on', [$fromDate, $toDate])
                    ->with(['sale:id,invoice_no,customer_name,customer_phone', 'items'])
                    ->latest('id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => 'returns',
                    'title' => 'Sales Returns in Period ('.$fromDate.' to '.$toDate.')',
                    'count' => $returns->count(),
                    'records' => $returns->map(fn ($r) => [
                        'id' => $r->id,
                        'sale_id' => $r->sale_id,
                        'invoice_no' => $r->sale?->invoice_no,
                        'customer_name' => $r->sale?->customer_name,
                        'returned_on' => $r->returned_on->toDateString(),
                        'reason' => $r->reason,
                        'return_value' => (float) $r->return_value,
                        'refunded_amount' => (float) $r->refunded_amount,
                        'restock' => (bool) $r->restock,
                        'items_count' => $r->items->count(),
                    ]),
                ];

            case 'amount_received':
                $payments = SalePayment::join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                    ->where('sales.status', '<>', 'cancelled')
                    ->whereBetween('sale_payments.paid_on', [$fromDate, $toDate])
                    ->select('sale_payments.*', 'sales.invoice_no', 'sales.customer_name', 'sales.customer_phone')
                    ->latest('sale_payments.id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => 'amount_received',
                    'title' => 'Payments Received in Period ('.$fromDate.' to '.$toDate.')',
                    'count' => $payments->count(),
                    'records' => $payments->map(fn ($p) => [
                        'id' => $p->id,
                        'sale_id' => $p->sale_id,
                        'invoice_no' => $p->invoice_no,
                        'customer_name' => $p->customer_name,
                        'paid_on' => CarbonImmutable::parse($p->paid_on)->toDateString(),
                        'amount' => (float) $p->amount,
                        'method' => $p->method,
                        'reference' => $p->reference,
                        'note' => $p->note,
                    ]),
                ];

            case 'amount_due':
                $dues = Sale::where('status', '<>', 'cancelled')
                    ->where('payment_status', '<>', 'paid')
                    ->whereBetween('sale_date', [$fromDate, $toDate])
                    ->with(['courierOrder:id,sale_id,status,consignment_id'])
                    ->latest('id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => 'amount_due',
                    'title' => 'Invoices with Outstanding Due ('.$fromDate.' to '.$toDate.')',
                    'count' => $dues->count(),
                    'records' => $dues->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'sale_date' => $s->sale_date->toDateString(),
                        'customer_name' => $s->customer_name,
                        'customer_phone' => $s->customer_phone,
                        'grand_total' => (float) $s->grand_total,
                        'paid_total' => (float) $s->paid_total,
                        'due_amount' => (float) $s->due_amount,
                        'courier_status' => $s->courierOrder?->status ?? 'none',
                    ]),
                ];

            case 'product':
                $productId = $params['product_id'] ?? null;
                $productName = $params['product_name'] ?? null;

                $query = DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', '<>', 'cancelled')
                    ->whereBetween('s.sale_date', [$fromDate, $toDate]);

                if ($productId) {
                    $query->where('si.product_id', $productId);
                } elseif ($productName) {
                    $query->where('si.name', $productName);
                }

                $records = $query->selectRaw('
                    s.id AS sale_id,
                    s.invoice_no,
                    s.sale_date,
                    s.customer_name,
                    si.name,
                    si.qty,
                    si.returned_qty,
                    (si.qty - si.returned_qty) AS net_qty,
                    si.unit_price,
                    si.unit_cost,
                    ((si.qty - si.returned_qty) * si.unit_price) AS line_revenue
                ')
                    ->latest('s.id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => 'product',
                    'title' => 'Sales for '.($productName ?? 'Product #'.$productId),
                    'count' => $records->count(),
                    'records' => $records->map(function ($r) use ($includeCostProfit) {
                        $item = [
                            'sale_id' => $r->sale_id,
                            'invoice_no' => $r->invoice_no,
                            'sale_date' => $r->sale_date,
                            'customer_name' => $r->customer_name,
                            'item_name' => $r->name,
                            'qty' => (int) $r->qty,
                            'returned_qty' => (int) $r->returned_qty,
                            'net_qty' => (int) $r->net_qty,
                            'unit_price' => (float) $r->unit_price,
                            'line_revenue' => round((float) $r->line_revenue, 2),
                        ];

                        if ($includeCostProfit) {
                            $item['unit_cost'] = (float) $r->unit_cost;
                            $lineCogs = round((float) $r->net_qty * (float) $r->unit_cost, 2);
                            $item['line_cogs'] = $lineCogs;
                            $item['line_profit'] = round((float) $r->line_revenue - $lineCogs, 2);
                        }

                        return $item;
                    }),
                ];

            case 'day':
                $date = $params['date'] ?? $fromDate;
                $sales = Sale::where('status', '<>', 'cancelled')
                    ->where('sale_date', $date)
                    ->with(['courierOrder:id,sale_id,status,consignment_id'])
                    ->latest('id')
                    ->limit(100)
                    ->get();

                return [
                    'type' => 'day',
                    'title' => 'Sales on '.$date,
                    'count' => $sales->count(),
                    'records' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'sale_date' => $s->sale_date->toDateString(),
                        'customer_name' => $s->customer_name,
                        'customer_phone' => $s->customer_phone,
                        'subtotal' => (float) $s->subtotal,
                        'discount_amount' => (float) $s->discount_amount,
                        'grand_total' => (float) $s->grand_total,
                        'paid_total' => (float) $s->paid_total,
                        'due_amount' => (float) $s->due_amount,
                        'payment_status' => $s->payment_status,
                        'courier_status' => $s->courierOrder?->status ?? 'none',
                    ]),
                ];

            default:
                return [
                    'type' => 'unknown',
                    'title' => 'Records',
                    'count' => 0,
                    'records' => [],
                ];
        }
    }

    /**
     * Generate CSV content for export.
     * Enforces authorization: Staff exports NEVER contain cost or profit columns.
     */
    public function exportCsv(string|CarbonInterface $from, string|CarbonInterface $to, bool $includeCostProfit = false): string
    {
        $summary = $this->getSummary($from, $to);
        $products = $this->getByProduct($from, $to, $includeCostProfit);

        $output = fopen('php://temp', 'r+');

        // Header block
        fputcsv($output, ['TASA POS - SALES REPORT']);
        fputcsv($output, ['Period:', $from.' to '.$to]);
        fputcsv($output, ['Generated At:', now()->toDateTimeString()]);
        fputcsv($output, []);

        // Summary KPI block
        fputcsv($output, ['SUMMARY METRICS']);
        fputcsv($output, ['Metric', 'Amount (BDT)']);
        fputcsv($output, ['Invoices Count', $summary['invoice_count']]);
        fputcsv($output, ['Gross Sales', number_format($summary['gross_sales'], 2)]);
        fputcsv($output, ['Discounts', number_format($summary['discounts'], 2)]);
        fputcsv($output, ['Returns', number_format($summary['returns'], 2)]);
        fputcsv($output, ['Net Sales', number_format($summary['net_sales'], 2)]);
        fputcsv($output, ['Delivery Income', number_format($summary['delivery_charge'], 2)]);
        fputcsv($output, ['Grand Total', number_format($summary['grand_total'], 2)]);
        fputcsv($output, ['Amount Received', number_format($summary['amount_received'], 2)]);
        fputcsv($output, ['Amount Due', number_format($summary['amount_due'], 2)]);
        fputcsv($output, []);

        // By-Product block
        fputcsv($output, ['SALES BY PRODUCT']);
        $productHeaders = ['Product Name', 'SKU', 'Qty Sold', 'Revenue (BDT)'];
        if ($includeCostProfit) {
            $productHeaders[] = 'COGS (BDT)';
            $productHeaders[] = 'Gross Profit (BDT)';
            $productHeaders[] = 'Margin (%)';
        }
        fputcsv($output, $productHeaders);

        foreach ($products as $p) {
            $row = [
                $p['name'],
                $p['sku'],
                $p['qty_sold'],
                number_format($p['revenue'], 2),
            ];
            if ($includeCostProfit) {
                $row[] = number_format($p['cogs'] ?? 0, 2);
                $row[] = number_format($p['gross_profit'] ?? 0, 2);
                $row[] = number_format($p['profit_margin'] ?? 0, 1).'%';
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }

    /**
     * Generate Excel spreadsheet workbook using PhpSpreadsheet.
     * Enforces authorization: Staff exports NEVER contain cost or profit columns.
     */
    public function exportExcel(string|CarbonInterface $from, string|CarbonInterface $to, bool $includeCostProfit = false): Spreadsheet
    {
        $summary = $this->getSummary($from, $to);
        $products = $this->getByProduct($from, $to, $includeCostProfit);
        $daily = $this->getByDay($from, $to, $includeCostProfit);

        $spreadsheet = new Spreadsheet();

        // Sheet 1: Products & Summary
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Summary');

        // Brand Banner
        $sheet->setCellValue('A1', 'TASA POS — SALES REPORT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0B6E5E'));

        $sheet->setCellValue('A2', 'Period: '.$from.' to '.$to.' | Generated: '.now()->format('d M Y H:i'));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('525252'));

        // Summary Cards Table
        $sheet->setCellValue('A4', 'SUMMARY METRICS');
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $kpis = [
            ['Invoice Count', $summary['invoice_count']],
            ['Gross Sales (BDT)', $summary['gross_sales']],
            ['Discounts (BDT)', $summary['discounts']],
            ['Returns (BDT)', $summary['returns']],
            ['Net Sales (BDT)', $summary['net_sales']],
            ['Amount Received (BDT)', $summary['amount_received']],
            ['Amount Due (BDT)', $summary['amount_due']],
        ];

        $kpiRow = 5;
        foreach ($kpis as [$label, $val]) {
            $sheet->setCellValue('A'.$kpiRow, $label);
            $sheet->setCellValue('B'.$kpiRow, $val);
            if (is_numeric($val) && $label !== 'Invoice Count') {
                $sheet->getStyle('B'.$kpiRow)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $kpiRow++;
        }

        // Product Breakdown Table
        $prodStartRow = $kpiRow + 2;
        $sheet->setCellValue('A'.$prodStartRow, 'SALES BY PRODUCT');
        $sheet->getStyle('A'.$prodStartRow)->getFont()->setBold(true);

        $prodHeaderRow = $prodStartRow + 1;
        $headers = ['Product Name', 'SKU', 'Qty Sold', 'Revenue (BDT)'];
        if ($includeCostProfit) {
            $headers[] = 'COGS (BDT)';
            $headers[] = 'Gross Profit (BDT)';
            $headers[] = 'Margin (%)';
        }

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$prodHeaderRow, $h);
            $sheet->getStyle($col.$prodHeaderRow)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($col.$prodHeaderRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0B6E5E');
            $col++;
        }

        $rowIdx = $prodHeaderRow + 1;
        foreach ($products as $p) {
            $sheet->setCellValue('A'.$rowIdx, $p['name']);
            $sheet->setCellValue('B'.$rowIdx, $p['sku']);
            $sheet->setCellValue('C'.$rowIdx, $p['qty_sold']);
            $sheet->setCellValue('D'.$rowIdx, $p['revenue']);
            $sheet->getStyle('D'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');

            if ($includeCostProfit) {
                $sheet->setCellValue('E'.$rowIdx, $p['cogs'] ?? 0);
                $sheet->setCellValue('F'.$rowIdx, $p['gross_profit'] ?? 0);
                $sheet->setCellValue('G'.$rowIdx, ($p['profit_margin'] ?? 0).'%');
                $sheet->getStyle('E'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('F'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $rowIdx++;
        }

        // Auto-fit columns
        foreach (range('A', $col) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Generate PDF document via DomPDF.
     * Enforces authorization: Staff exports NEVER contain cost or profit columns.
     */
    public function exportPdf(string|CarbonInterface $from, string|CarbonInterface $to, bool $includeCostProfit = false): \Barryvdh\DomPDF\PDF
    {
        $summary = $this->getSummary($from, $to);
        $products = $this->getByProduct($from, $to, $includeCostProfit);
        $daily = $this->getByDay($from, $to, $includeCostProfit);

        $pdf = Pdf::loadView('reports.sales-report-pdf', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'products' => $products,
            'daily' => $daily,
            'includeCostProfit' => $includeCostProfit,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
