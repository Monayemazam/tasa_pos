<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AddSalePaymentRequest;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalePaymentController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {
    }

    /**
     * Record a payment for an existing sale with remaining dues.
     */
    public function store(AddSalePaymentRequest $request, Sale $sale): JsonResponse|RedirectResponse
    {
        $this->authorize('addPayment', $sale);

        $payment = $this->saleService->addPayment($sale, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'payment' => $payment,
                'sale' => $sale->fresh(['payments', 'customer', 'courierOrder']),
                'message' => 'Payment recorded successfully.',
            ]);
        }

        return back()->with('success', "Payment of ৳{$payment->amount} recorded for invoice {$sale->invoice_no}.");
    }
}
