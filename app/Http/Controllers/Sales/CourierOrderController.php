<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchPathaoOrderJob;
use App\Models\CourierOrder;
use App\Services\Courier\PathaoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierOrderController extends Controller
{
    public function __construct(
        protected PathaoService $pathaoService
    ) {
    }

    /**
     * Display the courier orders table with filters and real-time status.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CourierOrder::class);

        $query = CourierOrder::query()
            ->with(['sale:id,invoice_no,customer_name,customer_phone,grand_total,paid_total,due_amount,status,sale_date', 'settledBy:id,name'])
            ->latest('id');

        // Search
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('merchant_order_id', 'like', "%{$search}%")
                    ->orWhere('consignment_id', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Settlement status filter
        if ($settlement = $request->input('settlement')) {
            if ($settlement === 'settled') {
                $query->whereNotNull('cod_settled_at');
            } elseif ($settlement === 'unsettled') {
                $query->whereNull('cod_settled_at')->where('cod_amount', '>', 0);
            }
        }

        // Date range
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->paginate(15)->withQueryString()->through(function (CourierOrder $o) {
            return [
                'id' => $o->id,
                'merchant_order_id' => $o->merchant_order_id,
                'sale_id' => $o->sale_id,
                'consignment_id' => $o->consignment_id,
                'recipient_name' => $o->recipient_name,
                'recipient_phone' => $o->recipient_phone,
                'recipient_address' => $o->recipient_address,
                'recipient_city' => (int) $o->recipient_city,
                'recipient_zone' => (int) $o->recipient_zone,
                'recipient_area' => (int) $o->recipient_area,
                'store_id' => (int) $o->store_id,
                'item_description' => $o->item_description,
                'special_instruction' => $o->special_instruction,
                'delivery_type' => $o->delivery_type,
                'delivery_type_label' => $o->delivery_type === 12 ? 'On Demand (12h)' : 'Normal (48h)',
                'item_weight' => (float) $o->item_weight,
                'item_quantity' => $o->item_quantity,
                'cod_amount' => (float) $o->cod_amount,
                'courier_charge' => (float) $o->courier_charge,
                'status' => $o->status,
                'raw_status' => $o->raw_status,
                'attempts' => $o->attempts,
                'last_error' => $o->last_error,
                'created_at' => $o->created_at?->format('d M Y, h:i A'),
                'delivered_at' => $o->delivered_at?->format('d M Y, h:i A'),
                'last_synced_at' => $o->last_synced_at?->format('d M Y, h:i A'),
                'is_settled' => (bool) $o->cod_settled_at,
                'cod_settled_at' => $o->cod_settled_at?->format('d M Y, h:i A'),
                'cod_settled_by' => $o->settledBy?->name,
                'tracking_url' => $o->consignment_id ? "https://merchant.pathao.com/tracking?consignment_id={$o->consignment_id}" : null,
            ];
        });

        $connection = $this->pathaoService->getConnectionStatus();
        $cities = $this->pathaoService->getCities();
        $stores = $this->pathaoService->getStores();

        return Inertia::render('sales/CourierOrders', [
            'orders' => $orders,
            'connection' => $connection,
            'cities' => $cities,
            'stores' => $stores,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
                'settlement' => $request->input('settlement', 'all'),
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
            'can' => [
                'retry' => $request->user()?->can('courier.retry') || $request->user()?->hasRole('Owner'),
                'settle_cod' => $request->user()?->can('courier.settle_cod') || $request->user()?->hasRole('Owner'),
            ],
        ]);
    }

    /**
     * Re-dispatch / retry a failed or queued courier order.
     */
    public function retry(CourierOrder $courierOrder): RedirectResponse
    {
        $this->authorize('retry', $courierOrder);

        if (in_array($courierOrder->status, ['created', 'picked_up', 'in_transit', 'delivered'], true) && $courierOrder->consignment_id) {
            return back()->with('info', "Order #{$courierOrder->merchant_order_id} already has an active consignment ({$courierOrder->consignment_id}).");
        }

        // Reset attempts count and set status to queued to unlock manual retry execution
        $courierOrder->update([
            'attempts' => 0,
            'status' => 'queued',
            'last_error' => null,
        ]);

        DispatchPathaoOrderJob::dispatch($courierOrder->id);

        return back()->with('success', "Order #{$courierOrder->merchant_order_id} queued for Pathao dispatch.");
    }

    /**
     * Poll and refresh delivery status from Pathao API.
     */
    public function sync(CourierOrder $courierOrder): RedirectResponse
    {
        $this->authorize('view', $courierOrder);

        if (! $courierOrder->consignment_id) {
            return back()->with('error', 'Cannot sync order without a consignment ID.');
        }

        $synced = $this->pathaoService->syncOrder($courierOrder);

        if ($synced) {
            return back()->with('success', "Status updated to {$courierOrder->status} for #{$courierOrder->consignment_id}.");
        }

        return back()->with('info', "Status checked for #{$courierOrder->consignment_id}. No status change.");
    }

    /**
     * Mark COD collection as settled.
     */
    public function settleCod(CourierOrder $courierOrder, Request $request): RedirectResponse
    {
        $this->authorize('settleCod', $courierOrder);

        try {
            $this->pathaoService->settleCod($courierOrder, $request->user());

            return back()->with('success', "COD amount (৳{$courierOrder->cod_amount}) marked as settled for consignment #{$courierOrder->consignment_id}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Test connection to Pathao Merchant API.
     */
    public function testConnection(): JsonResponse
    {
        $this->authorize('viewAny', CourierOrder::class);

        $result = $this->pathaoService->testConnection();

        return response()->json($result);
    }

    /**
     * Update courier order details with PRD safeguard.
     */
    public function update(CourierOrder $courierOrder, Request $request): RedirectResponse
    {
        $this->authorize('update', $courierOrder);

        $validated = $request->validate([
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'recipient_address' => ['nullable', 'string', 'min:10', 'max:500'],
            'recipient_city' => ['nullable', 'integer'],
            'recipient_zone' => ['nullable', 'integer'],
            'recipient_area' => ['nullable', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_type' => ['nullable', 'in:12,48'],
            'item_weight' => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'special_instruction' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->pathaoService->updateOrder($courierOrder, $validated);

            return back()->with('success', "Courier order #{$courierOrder->merchant_order_id} updated successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

