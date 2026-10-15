<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AddSalePaymentRequest;
use App\Http\Requests\Sales\CancelSaleRequest;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Jobs\DispatchPathaoOrderJob;
use App\Models\Sale;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected SaleService $saleService
    ) {
    }

    /**
     * Display invoices list with filters and pagination.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $query = Sale::query()
            ->with([
                'courierOrder:id,sale_id,status,consignment_id',
                'items:id,sale_id,product_id,product_variant_id,name,variant_name,variant_sku,qty,returned_qty,unit_price,line_total,is_custom',
                'returns:id,sale_id,return_value,refunded_amount',
            ])
            ->latest('id');

        // Search (invoice_no, customer_name, customer_phone, item name, variant SKU)
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('name', 'like', "%{$search}%")
                            ->orWhere('variant_name', 'like', "%{$search}%")
                            ->orWhere('variant_sku', 'like', "%{$search}%");
                    });
            });
        }

        // Date Presets & Range
        $datePreset = $request->input('date_preset');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($datePreset === 'today') {
            $dateFrom = Carbon::today()->toDateString();
            $dateTo = Carbon::today()->toDateString();
        } elseif ($datePreset === 'yesterday') {
            $dateFrom = Carbon::yesterday()->toDateString();
            $dateTo = Carbon::yesterday()->toDateString();
        } elseif ($datePreset === 'last_7_days') {
            $dateFrom = Carbon::today()->subDays(6)->toDateString();
            $dateTo = Carbon::today()->toDateString();
        } elseif ($datePreset === 'this_month') {
            $dateFrom = Carbon::now()->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } elseif ($datePreset === 'last_month') {
            $dateFrom = Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        }

        if ($dateFrom) {
            $query->whereDate('sale_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('sale_date', '<=', $dateTo);
        }

        // Payment status filter
        if ($paymentStatus = $request->input('payment_status')) {
            if ($paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
            }
        }

        // Sale status filter (active, cancelled, partially_returned, returned)
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Courier mode filter
        if ($courierMode = $request->input('courier_mode')) {
            if ($courierMode !== 'all') {
                $query->where('courier_mode', $courierMode);
            }
        }

        // Courier status filter
        if ($courierStatus = $request->input('courier_status')) {
            if ($courierStatus === 'none') {
                $query->where('courier_mode', 'none');
            } elseif ($courierStatus !== 'all') {
                $query->whereHas('courierOrder', function ($q) use ($courierStatus) {
                    $q->where('status', $courierStatus);
                });
            }
        }

        // Payment method filter
        if ($paymentMethod = $request->input('payment_method')) {
            if ($paymentMethod !== 'all') {
                $query->whereHas('payments', function ($q) use ($paymentMethod) {
                    $q->where('method', $paymentMethod);
                });
            }
        }

        $invoices = $query->paginate(15)->withQueryString()->through(function (Sale $s) {
            return [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'sale_date' => $s->sale_date?->format('Y-m-d'),
                'customer_name' => $s->customer_name,
                'customer_phone' => $s->customer_phone,
                'customer_address' => $s->customer_address,
                'grand_total' => (float) $s->grand_total,
                'paid_total' => (float) $s->paid_total,
                'due_amount' => (float) $s->due_amount,
                'net_due_amount' => (float) $s->net_due_amount,
                'payment_status' => $s->payment_status,
                'status' => $s->status,
                'courier_mode' => $s->courier_mode,
                'courier_status' => $s->courierOrder?->status,
                'consignment_id' => $s->courierOrder?->consignment_id,
                'cancel_reason' => $s->cancel_reason,
                'items' => $s->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'product_variant_id' => $i->product_variant_id,
                    'name' => $i->name,
                    'variant_name' => $i->variant_name,
                    'variant_sku' => $i->variant_sku,
                    'qty' => (int) $i->qty,
                    'returned_qty' => (int) $i->returned_qty,
                    'unreturned_qty' => max(0, (int) ($i->qty - $i->returned_qty)),
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                    'is_custom' => (bool) $i->is_custom,
                ]),
            ];
        });

        return Inertia::render('sales/Invoices', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $request->input('search', ''),
                'date_preset' => $request->input('date_preset', ''),
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'payment_status' => $request->input('payment_status', 'all'),
                'status' => $request->input('status', 'all'),
                'courier_status' => $request->input('courier_status', 'all'),
                'courier_mode' => $request->input('courier_mode', 'all'),
                'payment_method' => $request->input('payment_method', 'all'),
            ],
            'can' => [
                'cancel' => $request->user()?->can('sales.cancel') || $request->user()?->hasRole('Owner'),
                'return' => $request->user()?->can('sales.return') || $request->user()?->hasRole('Owner'),
                'add_payment' => $request->user()?->can('sales.add_payment') || $request->user()?->hasRole('Owner'),
            ],
        ]);
    }

    /**
     * Display printable and previewable invoice view.
     */
    public function show(Sale $sale, Request $request): Response
    {
        $this->authorize('view', $sale);

        $invoiceData = $this->invoiceService->getInvoiceData($sale, $request->user());
        $paperSize = strtoupper((string) $request->input('paper_size', $invoiceData['branding']['paper_size']));

        return Inertia::render('sales/InvoiceView', [
            'invoice' => $invoiceData,
            'paperSize' => $paperSize,
            'can' => [
                'cancel' => $request->user()?->can('sales.cancel') || $request->user()?->hasRole('Owner'),
                'return' => $request->user()?->can('sales.return') || $request->user()?->hasRole('Owner'),
                'add_payment' => $request->user()?->can('sales.add_payment') || $request->user()?->hasRole('Owner'),
            ],
        ]);
    }

    /**
     * Record an additional payment against an invoice.
     */
    public function addPayment(Sale $sale, AddSalePaymentRequest $request): RedirectResponse
    {
        $this->authorize('addPayment', $sale);

        $this->saleService->addPayment($sale, $request->validated(), $request->user());

        return back()->with('success', "Payment of ৳{$request->input('amount')} recorded successfully for {$sale->invoice_no}.");
    }

    /**
     * Cancel a sale: Owner-only by default, restores stock, audits old/new state.
     */
    public function cancel(Sale $sale, CancelSaleRequest $request): RedirectResponse
    {
        $this->authorize('cancel', $sale);

        $result = $this->saleService->cancelSale($sale, $request->validated(), $request->user());

        if (! empty($result['warning'])) {
            return back()->with('warning', $result['warning']);
        }

        return back()->with('success', "Sale {$sale->invoice_no} cancelled successfully and stock restored.");
    }

    /**
     * Process full or partial returns for a sale.
     */
    public function return(Sale $sale, ReturnSaleRequest $request): RedirectResponse
    {
        $this->authorize('return', $sale);

        $this->saleService->processReturn($sale, $request->validated(), $request->user());

        return back()->with('success', "Return processed successfully for sale {$sale->invoice_no}.");
    }

    /**
     * Re-dispatch Pathao order if queued or failed.
     */
    public function retryCourier(Sale $sale, Request $request): RedirectResponse
    {
        $this->authorize('view', $sale);

        if ($sale->courierOrder && in_array($sale->courierOrder->status, ['queued', 'failed'], true)) {
            DispatchPathaoOrderJob::dispatch($sale->courierOrder->id);
            return back()->with('success', "Pathao order dispatch queued for {$sale->invoice_no}.");
        }

        return back()->with('error', 'Courier order is not in a retryable state.');
    }

    /**
     * Get sale items for client-side modal inspection.
     */
    public function items(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        $items = $sale->items->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'qty' => (int) $i->qty,
            'returned_qty' => (int) $i->returned_qty,
            'unreturned_qty' => max(0, (int) ($i->qty - $i->returned_qty)),
            'unit_price' => (float) $i->unit_price,
            'line_total' => (float) $i->line_total,
            'is_custom' => (bool) $i->is_custom,
        ]);

        return response()->json([
            'invoice_no' => $sale->invoice_no,
            'status' => $sale->status,
            'items' => $items,
        ]);
    }

    /**
     * Download real server-side generated PDF using DomPDF.
     */
    public function downloadPdf(Sale $sale, Request $request): SymfonyResponse
    {
        $this->authorize('view', $sale);

        $paperSize = $request->input('paper_size');
        $pdf = $this->invoiceService->generatePdf($sale, $paperSize);

        return $pdf->download("{$sale->invoice_no}.pdf");
    }

    /**
     * Return direct HTML layout for print preview / browser print dialog.
     */
    public function print(Sale $sale, Request $request): HttpResponse
    {
        $this->authorize('view', $sale);

        $paperSize = $request->input('paper_size');
        $html = $this->invoiceService->renderHtml($sale, $paperSize);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
