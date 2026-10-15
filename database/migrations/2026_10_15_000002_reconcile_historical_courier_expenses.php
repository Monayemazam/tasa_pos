<?php

use App\Models\AuditLog;
use App\Models\Expense;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reconcile historical automatic courier expenses so customer-funded and cancelled delivery charges
     * are excluded from business operating expenses, while preserving full audit history.
     */
    public function up(): void
    {
        $expenses = Expense::where('source', 'pathao_charge')
            ->with(['courierOrder', 'sale'])
            ->get();

        foreach ($expenses as $expense) {
            $courierOrder = $expense->courierOrder;
            $sale = $expense->sale;

            if (! $courierOrder || ! $sale) {
                continue;
            }

            // Case 1: Order was cancelled or failed in courier gateway / POS
            if (in_array($courierOrder->status, ['cancelled', 'failed'], true) || $sale->status === 'cancelled') {
                $oldData = $expense->toArray();
                $expense->delete();

                AuditLog::create([
                    'user_id' => $expense->created_by,
                    'event' => 'expense_reconciled',
                    'auditable_type' => Expense::class,
                    'auditable_id' => $expense->id,
                    'old_values' => $oldData,
                    'new_values' => [
                        'status' => 'reversed_cancelled_order',
                        'reason' => "Soft-deleted courier expense #{$expense->id} because courier order #{$courierOrder->merchant_order_id} was {$courierOrder->status}.",
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Migration/2026_10_15_000002_reconcile_historical_courier_expenses',
                ]);

                continue;
            }

            // Case 2: Customer delivery charge funded the courier fee
            $customerDeliveryCharge = (float) $sale->delivery_charge;
            $actualCourierFee = (float) ($courierOrder->courier_charge ?? $expense->amount);
            $businessBorneCost = max(0.00, round($actualCourierFee - $customerDeliveryCharge, 2));

            if ($businessBorneCost <= 0.00) {
                // Customer funded the entire delivery charge (or more)
                $oldData = $expense->toArray();
                $expense->delete();

                AuditLog::create([
                    'user_id' => $expense->created_by,
                    'event' => 'expense_reconciled',
                    'auditable_type' => Expense::class,
                    'auditable_id' => $expense->id,
                    'old_values' => $oldData,
                    'new_values' => [
                        'status' => 'reversed_customer_funded',
                        'customer_delivery_charge' => $customerDeliveryCharge,
                        'actual_courier_fee' => $actualCourierFee,
                        'reason' => "Soft-deleted courier expense #{$expense->id} because customer funded delivery charge of ৳{$customerDeliveryCharge} for order #{$courierOrder->merchant_order_id} (Pathao fee: ৳{$actualCourierFee}).",
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Migration/2026_10_15_000002_reconcile_historical_courier_expenses',
                ]);
            } elseif ($businessBorneCost != (float) $expense->amount) {
                // Customer partially funded; update to only the unrecovered portion
                $oldAmount = $expense->amount;
                $expense->update([
                    'amount' => $businessBorneCost,
                    'note' => "Business-borne courier delivery fee for {$courierOrder->merchant_order_id} (Fee: ৳{$actualCourierFee}, Customer Paid: ৳{$customerDeliveryCharge}, Business Borne: ৳{$businessBorneCost})",
                ]);

                AuditLog::create([
                    'user_id' => $expense->created_by,
                    'event' => 'expense_reconciled',
                    'auditable_type' => Expense::class,
                    'auditable_id' => $expense->id,
                    'old_values' => ['amount' => $oldAmount],
                    'new_values' => [
                        'amount' => $businessBorneCost,
                        'reason' => "Adjusted courier expense #{$expense->id} to business-borne unrecovered portion: ৳{$businessBorneCost}.",
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Migration/2026_10_15_000002_reconcile_historical_courier_expenses',
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore reconciled expenses if rollback is required
        Expense::onlyTrashed()
            ->where('source', 'pathao_charge')
            ->restore();
    }
};
