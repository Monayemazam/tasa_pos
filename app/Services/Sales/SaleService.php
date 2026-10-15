<?php

namespace App\Services\Sales;

use App\Jobs\DispatchPathaoOrderJob;
use App\Models\CourierOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        protected CustomerService $customerService
    ) {
    }

    /**
     * Create a new sale inside a concurrency-safe transaction with stock locking,
     * monthly invoice sequence generation, and idempotency protection.
     *
     * @return array{sale: Sale, is_duplicate: bool}
     */
    public function createSale(array $data, User $user): array
    {
        $idempotencyToken = $data['idempotency_token'] ?? null;

        // 1. Idempotency Check: if token exists, return existing sale without duplicate side-effects
        if ($idempotencyToken) {
            $existing = Sale::where('idempotency_token', $idempotencyToken)
                ->with(['items', 'payments', 'customer', 'courierOrder'])
                ->first();

            if ($existing) {
                return [
                    'sale' => $existing,
                    'is_duplicate' => true,
                ];
            }
        }

        return DB::transaction(function () use ($data, $user, $idempotencyToken) {
            // Re-check idempotency under transaction lock
            if ($idempotencyToken) {
                $existing = Sale::where('idempotency_token', $idempotencyToken)->lockForUpdate()->first();
                if ($existing) {
                    return [
                        'sale' => $existing->load(['items', 'payments', 'customer', 'courierOrder']),
                        'is_duplicate' => true,
                    ];
                }
            }

            // 2. Customer resolution / creation
            $customer = $this->customerService->createOrUpdateFromSale([
                'name' => $data['customer_name'],
                'phone' => $data['customer_phone'],
                'alt_phone' => $data['customer_alt_phone'] ?? null,
                'address' => $data['customer_address'],
                'pathao_city_id' => $data['city_id'] ?? null,
                'pathao_zone_id' => $data['zone_id'] ?? null,
                'pathao_area_id' => $data['area_id'] ?? null,
                'city_name' => $data['city_name'] ?? null,
                'zone_name' => $data['zone_name'] ?? null,
                'area_name' => $data['area_name'] ?? null,
                'notes' => $data['customer_notes'] ?? null,
            ]);

            // 3. Process Cart Items & Lock Stock
            $rawItems = $data['items'] ?? [];
            if (empty($rawItems)) {
                throw ValidationException::withMessages(['items' => 'Sale cart cannot be empty.']);
            }

            // Separate catalogue products/variants from custom items
            $catalogueQuantities = [];
            $variantQuantities = [];
            foreach ($rawItems as $index => $item) {
                $isCustom = ! empty($item['is_custom']);
                $qty = (int) ($item['qty'] ?? 1);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(["items.{$index}.qty" => 'Item quantity must be a positive integer.']);
                }

                if (! $isCustom) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $variantId = ! empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;

                    if ($productId <= 0 && ! $variantId) {
                        throw ValidationException::withMessages(["items.{$index}.product_id" => 'Valid product ID is required for catalogue items.']);
                    }

                    if ($productId > 0) {
                        $catalogueQuantities[$productId] = ($catalogueQuantities[$productId] ?? 0) + $qty;
                    }
                    if ($variantId) {
                        $variantQuantities[$variantId] = ($variantQuantities[$variantId] ?? 0) + $qty;
                    }
                }
            }

            // Lock catalogue variants
            $lockedVariants = collect();
            if (! empty($variantQuantities)) {
                $lockedVariants = ProductVariant::whereIn('id', array_keys($variantQuantities))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // If any product_ids were missing but variant has product_id, ensure parent product is queued to lock
                foreach ($lockedVariants as $v) {
                    if (! isset($catalogueQuantities[$v->product_id])) {
                        $catalogueQuantities[$v->product_id] = 0;
                    }
                }
            }

            // Lock catalogue products
            $lockedProducts = collect();
            if (! empty($catalogueQuantities)) {
                $lockedProducts = Product::whereIn('id', array_keys($catalogueQuantities))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            }

            $allowNegativeStock = in_array(Setting::get('inventory.allow_negative_stock', '0'), ['1', 1, true], true);

            // Validate catalogue products
            foreach ($catalogueQuantities as $productId => $totalQtyRequested) {
                $product = $lockedProducts->get($productId);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => "Product #{$productId} does not exist."]);
                }

                if (! $product->is_active) {
                    throw ValidationException::withMessages(['items' => "Product '{$product->name}' is inactive and cannot be sold."]);
                }

                // If non-variant product, check stock boundary
                if (! $product->has_variants && $totalQtyRequested > $product->stock_qty && ! $allowNegativeStock) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$product->name}'. Available: {$product->stock_qty}, requested: {$totalQtyRequested}.",
                    ]);
                }
            }

            // Validate variants
            foreach ($variantQuantities as $variantId => $totalQtyRequested) {
                $variant = $lockedVariants->get($variantId);
                if (! $variant) {
                    throw ValidationException::withMessages(['items' => "Product variant #{$variantId} does not exist."]);
                }

                if (! $variant->is_active) {
                    $pName = $variant->product?->name ?? 'Product';
                    throw ValidationException::withMessages(['items' => "Variant '{$variant->color_name}' of '{$pName}' is inactive and cannot be sold."]);
                }

                if ($totalQtyRequested > $variant->stock_qty && ! $allowNegativeStock) {
                    $pName = $variant->product?->name ?? 'Product';
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$pName} — {$variant->color_name}'. Available: {$variant->stock_qty}, requested: {$totalQtyRequested}.",
                    ]);
                }
            }

            // 4. Calculate Subtotal, Price permissions, and Line Totals
            $subtotal = 0.00;
            $processedItems = [];
            $canEditPrice = $user->hasRole('Owner') || $user->can('sales.edit_price');

            foreach ($rawItems as $index => $item) {
                $isCustom = ! empty($item['is_custom']);
                $qty = (int) ($item['qty'] ?? 1);
                $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

                if ($unitPrice < 0) {
                    throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Unit price cannot be negative.']);
                }

                if ($isCustom) {
                    $name = trim($item['name'] ?? 'Custom Item');
                    if (empty($name)) {
                        throw ValidationException::withMessages(["items.{$index}.name" => 'Custom item name cannot be empty.']);
                    }

                    $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== ''
                        ? round(max(0, (float) $item['unit_cost']), 2)
                        : 0.00;

                    $lineTotal = round($qty * $unitPrice, 2);
                    $subtotal += $lineTotal;

                    $processedItems[] = [
                        'product_id' => null,
                        'product_variant_id' => null,
                        'variant_name' => null,
                        'variant_sku' => null,
                        'is_custom' => true,
                        'name' => $name,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                    ];
                } else {
                    $variantId = ! empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                    $variant = $variantId ? $lockedVariants->get($variantId) : null;

                    $productId = (int) ($item['product_id'] ?? ($variant ? $variant->product_id : 0));
                    $product = $lockedProducts->get($productId);

                    if (! $product) {
                        throw ValidationException::withMessages(["items.{$index}.product_id" => 'Valid product is required.']);
                    }

                    if ($variant) {
                        if ($variant->product_id !== $product->id) {
                            throw ValidationException::withMessages([
                                "items.{$index}.product_variant_id" => "Selected variant does not belong to product '{$product->name}'.",
                            ]);
                        }

                        $expectedPrice = (float) $variant->selling_price;
                        if (abs($unitPrice - $expectedPrice) > 0.001 && ! $canEditPrice) {
                            throw ValidationException::withMessages([
                                "items.{$index}.unit_price" => "You do not have permission to modify selling price for '{$product->name} — {$variant->color_name}'.",
                            ]);
                        }

                        $unitCost = (float) $variant->cost_price;
                        $lineTotal = round($qty * $unitPrice, 2);
                        $subtotal += $lineTotal;

                        $processedItems[] = [
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'variant_name' => $variant->color_name ?: $variant->attribute_value,
                            'variant_sku' => $variant->sku,
                            'is_custom' => false,
                            'name' => "{$product->name} — {$variant->color_name}",
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'unit_cost' => $unitCost,
                            'line_total' => $lineTotal,
                        ];
                    } else {
                        // Product without variant selected
                        if ($product->has_variants) {
                            throw ValidationException::withMessages([
                                "items.{$index}.product_variant_id" => "Please select a {$product->variation_type} variant for '{$product->name}'.",
                            ]);
                        }

                        $expectedPrice = (float) $product->selling_price;
                        if (abs($unitPrice - $expectedPrice) > 0.001 && ! $canEditPrice) {
                            throw ValidationException::withMessages([
                                "items.{$index}.unit_price" => "You do not have permission to modify selling price for '{$product->name}'.",
                            ]);
                        }

                        $unitCost = (float) $product->cost_price;
                        $lineTotal = round($qty * $unitPrice, 2);
                        $subtotal += $lineTotal;

                        $processedItems[] = [
                            'product_id' => $product->id,
                            'product_variant_id' => null,
                            'variant_name' => null,
                            'variant_sku' => $product->sku,
                            'is_custom' => false,
                            'name' => $product->name,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'unit_cost' => $unitCost,
                            'line_total' => $lineTotal,
                        ];
                    }
                }
            }

            // 5. Discount, Delivery Charge, and Grand Total
            $discountType = ($data['discount_type'] ?? 'flat') === 'percent' ? 'percent' : 'flat';
            $discountValue = max(0, round((float) ($data['discount_value'] ?? 0), 2));

            if ($discountType === 'percent') {
                if ($discountValue > 100) {
                    throw ValidationException::withMessages(['discount_value' => 'Percentage discount cannot exceed 100%.']);
                }
                $discountAmount = round($subtotal * ($discountValue / 100), 2);
            } else {
                if ($discountValue > $subtotal) {
                    throw ValidationException::withMessages(['discount_value' => 'Discount cannot exceed subtotal.']);
                }
                $discountAmount = min($subtotal, $discountValue);
            }

            $deliveryCharge = max(0, round((float) ($data['delivery_charge'] ?? 0), 2));
            $grandTotal = round($subtotal - $discountAmount + $deliveryCharge, 2);

            if ($grandTotal < 0) {
                throw ValidationException::withMessages(['grand_total' => 'Grand total cannot be negative.']);
            }

            // 6. Payment & Due calculation
            $paidTotal = max(0, round((float) ($data['paid_amount'] ?? 0), 2));
            if ($paidTotal > $grandTotal) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot exceed grand total.']);
            }

            $dueAmount = round($grandTotal - $paidTotal, 2);

            if ($dueAmount <= 0.001) {
                $paymentStatus = 'paid';
            } elseif ($paidTotal > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            // 7. Monthly Sequential Invoice Number
            $saleDate = ! empty($data['sale_date']) ? Carbon::parse($data['sale_date']) : now();
            $invoiceNo = InvoiceSequence::nextInvoiceNo($saleDate, 'TASA');

            // 8. Delivery / Courier mode
            $courierMode = ($data['courier_mode'] ?? 'pathao') === 'none' ? 'none' : 'pathao';
            if ($courierMode === 'pathao') {
                if (empty($data['city_id']) || empty($data['zone_id']) || empty($data['area_id'])) {
                    throw ValidationException::withMessages(['city_id' => 'Pathao City, Zone, and Area are required when sending via courier.']);
                }
            }

            // 9. Persist Sale record
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'idempotency_token' => $idempotencyToken ?: (string) \Illuminate\Support\Str::uuid(),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_address' => $customer->address,
                'city_id' => $data['city_id'] ?? null,
                'zone_id' => $data['zone_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'city_name' => $data['city_name'] ?? null,
                'zone_name' => $data['zone_name'] ?? null,
                'area_name' => $data['area_name'] ?? null,
                'sale_date' => $saleDate->format('Y-m-d'),
                'sold_at' => now(),
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'delivery_charge' => $deliveryCharge,
                'grand_total' => $grandTotal,
                'paid_total' => $paidTotal,
                'payment_status' => $paymentStatus,
                'status' => 'active',
                'courier_mode' => $courierMode,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // 10. Persist Sale Items & Stock Movements
            foreach ($processedItems as $itemData) {
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'],
                    'variant_name' => $itemData['variant_name'],
                    'variant_sku' => $itemData['variant_sku'],
                    'is_custom' => $itemData['is_custom'],
                    'name' => $itemData['name'],
                    'qty' => $itemData['qty'],
                    'unit_price' => $itemData['unit_price'],
                    'unit_cost' => $itemData['unit_cost'],
                    'line_total' => $itemData['line_total'],
                ]);

                // Deduct stock only for catalogue items
                if (! $itemData['is_custom'] && $itemData['product_id']) {
                    $product = $lockedProducts->get($itemData['product_id']);
                    $variant = ! empty($itemData['product_variant_id'])
                        ? $lockedVariants->get($itemData['product_variant_id'])
                        : null;

                    if ($variant) {
                        // Decrement variant stock
                        $newVariantStock = $variant->stock_qty - $itemData['qty'];
                        $variant->stock_qty = $newVariantStock;
                        $variant->save();

                        // Decrement parent product stock
                        $newProductStock = $product->stock_qty - $itemData['qty'];
                        $product->stock_qty = $newProductStock;
                        $product->save();

                        ProductStockMovement::create([
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'type' => 'sale',
                            'qty' => -$itemData['qty'],
                            'balance_after' => $newVariantStock,
                            'reference_type' => 'sales',
                            'reference_id' => $sale->id,
                            'movement_date' => $saleDate->format('Y-m-d'),
                            'note' => "Sale {$sale->invoice_no} ({$variant->color_name})",
                            'created_by' => $user->id,
                        ]);
                    } else {
                        // Non-variant product
                        $newBalance = $product->stock_qty - $itemData['qty'];
                        $product->stock_qty = $newBalance;
                        $product->save();

                        ProductStockMovement::create([
                            'product_id' => $product->id,
                            'product_variant_id' => null,
                            'type' => 'sale',
                            'qty' => -$itemData['qty'],
                            'balance_after' => $newBalance,
                            'reference_type' => 'sales',
                            'reference_id' => $sale->id,
                            'movement_date' => $saleDate->format('Y-m-d'),
                            'note' => "Sale {$sale->invoice_no}",
                            'created_by' => $user->id,
                        ]);
                    }
                }
            }

            // 11. Record Initial Payment (if paid_total > 0)
            if ($paidTotal > 0) {
                $method = $data['payment_method'] ?? 'cash';
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'paid_on' => $saleDate->format('Y-m-d'),
                    'amount' => $paidTotal,
                    'method' => $method,
                    'reference' => $data['payment_reference'] ?? null,
                    'note' => $data['payment_note'] ?? 'Initial POS payment',
                    'created_by' => $user->id,
                ]);
            }

            // 12. Create Courier Order & Queue Job (if courier_mode === 'pathao')
            if ($courierMode === 'pathao') {
                $itemQuantity = array_sum(array_column($processedItems, 'qty'));
                $itemWeight = max(0.1, (float) ($data['item_weight'] ?? Setting::get('pathao.default_weight', 0.5)));
                $deliveryType = (int) ($data['delivery_type'] ?? Setting::get('pathao.default_delivery_type', 48));
                $storeId = (int) (Setting::get('pathao.store_id', 1) ?: 1);

                $itemNames = array_map(fn ($i) => $i['name'], $processedItems);
                $itemDescription = substr(implode(', ', $itemNames), 0, 250);

                $courierOrder = CourierOrder::create([
                    'sale_id' => $sale->id,
                    'merchant_order_id' => $sale->invoice_no,
                    'store_id' => $storeId,
                    'delivery_type' => $deliveryType,
                    'item_type' => 2, // Parcel
                    'item_quantity' => $itemQuantity,
                    'item_weight' => $itemWeight,
                    'item_description' => $itemDescription,
                    'special_instruction' => $data['special_instruction'] ?? null,
                    'recipient_name' => $sale->customer_name,
                    'recipient_phone' => $sale->customer_phone,
                    'recipient_address' => $sale->customer_address,
                    'recipient_city' => (int) $sale->city_id,
                    'recipient_zone' => (int) $sale->zone_id,
                    'recipient_area' => (int) $sale->area_id,
                    'cod_amount' => $sale->due_amount, // Collection amount = remaining balance due
                    'status' => 'queued',
                ]);

                // Dispatch queued job after commit
                DB::afterCommit(function () use ($courierOrder) {
                    DispatchPathaoOrderJob::dispatch($courierOrder->id);
                });
            }

            // 13. Audit Log
            AuditService::log('sale_created', Sale::class, null, [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'grand_total' => $sale->grand_total,
                'paid_total' => $sale->paid_total,
                'customer_phone' => $sale->customer_phone,
            ]);

            return [
                'sale' => $sale->load(['items', 'payments', 'customer', 'courierOrder']),
                'is_duplicate' => false,
            ];
        });
    }

    /**
     * Add a payment to an existing sale with remaining dues.
     */
    public function addPayment(Sale $sale, array $data, User $user): SalePayment
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $lockedSale = Sale::where('id', $sale->id)
                ->with(['courierOrder', 'returns'])
                ->lockForUpdate()
                ->first();

            if (! $lockedSale) {
                throw ValidationException::withMessages(['sale' => 'Sale not found.']);
            }

            if ($lockedSale->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cannot add payment to a cancelled sale.']);
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $currentDue = (float) $lockedSale->net_due_amount;
            if ($amount > $currentDue + 0.001) {
                throw ValidationException::withMessages(['amount' => "Payment amount (৳{$amount}) exceeds current balance due (৳{$currentDue})."]);
            }

            $payment = SalePayment::create([
                'sale_id' => $lockedSale->id,
                'paid_on' => ! empty($data['paid_on']) ? Carbon::parse($data['paid_on'])->format('Y-m-d') : now()->toDateString(),
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);

            $newPaidTotal = round((float) $lockedSale->paid_total + $amount, 2);
            $newDue = max(0, round((float) $lockedSale->grand_total - $newPaidTotal, 2));

            $paymentStatus = $newDue <= 0.001 ? 'paid' : 'partial';

            $lockedSale->update([
                'paid_total' => $newPaidTotal,
                'payment_status' => $paymentStatus,
                'updated_by' => $user->id,
            ]);

            // If courier order is queued, update COD collection amount
            if ($lockedSale->courierOrder && $lockedSale->courierOrder->status === 'queued') {
                $lockedSale->courierOrder->update([
                    'cod_amount' => max(0, $newDue),
                ]);
            }

            AuditService::log('sale_payment_added', Sale::class, [
                'sale_id' => $lockedSale->id,
                'previous_paid' => $lockedSale->paid_total,
            ], [
                'sale_id' => $lockedSale->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'new_paid_total' => $newPaidTotal,
            ]);

            return $payment;
        });
    }

    /**
     * Cancel an existing sale: Owner-only by default, restores stock exactly once,
     * retains invoice number & payments, audits old/new state.
     *
     * @return array{sale: Sale, warning: string|null}
     */
    public function cancelSale(Sale $sale, array $data, User $user): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Cancellation reason must be at least 5 characters.']);
        }

        return DB::transaction(function () use ($sale, $reason, $user) {
            $lockedSale = Sale::where('id', $sale->id)
                ->with(['items', 'courierOrder'])
                ->lockForUpdate()
                ->first();

            if (! $lockedSale) {
                throw ValidationException::withMessages(['sale' => 'Sale not found.']);
            }

            if ($lockedSale->status === 'cancelled') {
                throw ValidationException::withMessages(['reason' => 'This sale has already been cancelled.']);
            }

            $dispatchedWarning = null;
            if ($lockedSale->courierOrder) {
                $co = $lockedSale->courierOrder;
                if ($co->consignment_id || in_array($co->status, ['picked_up', 'in_transit', 'delivered'], true)) {
                    $dispatchedWarning = "Parcel #{$co->consignment_id} was already dispatched with Pathao. Please cancel or return it in the Pathao Merchant Panel.";
                } else {
                    $co->update(['status' => 'cancelled']);
                    $expense = Expense::where('courier_order_id', $co->id)
                        ->where('source', 'pathao_charge')
                        ->first();
                    if ($expense) {
                        $expense->delete();
                        AuditService::log('courier_expense_cancelled_sale', Expense::class, $expense->toArray(), [
                            'reason' => 'Sale cancelled before courier pickup',
                            'sale_id' => $lockedSale->id,
                        ]);
                    }
                }
            }

            // Restore stock for catalogue items that were not yet returned
            foreach ($lockedSale->items as $item) {
                if (! $item->is_custom && $item->product_id) {
                    $restoreQty = $item->qty - $item->returned_qty;
                    if ($restoreQty > 0) {
                        $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        $variant = $item->product_variant_id
                            ? ProductVariant::where('id', $item->product_variant_id)->lockForUpdate()->first()
                            : null;

                        if ($variant) {
                            $newVariantStock = $variant->stock_qty + $restoreQty;
                            $variant->stock_qty = $newVariantStock;
                            $variant->save();

                            if ($product) {
                                $product->stock_qty += $restoreQty;
                                $product->save();
                            }

                            ProductStockMovement::create([
                                'product_id' => $item->product_id,
                                'product_variant_id' => $variant->id,
                                'type' => 'sale_cancel',
                                'qty' => $restoreQty,
                                'balance_after' => $newVariantStock,
                                'reference_type' => 'sales',
                                'reference_id' => $lockedSale->id,
                                'movement_date' => now()->toDateString(),
                                'note' => "Sale cancel ({$lockedSale->invoice_no}): {$reason}",
                                'created_by' => $user->id,
                            ]);
                        } elseif ($product) {
                            $newStock = $product->stock_qty + $restoreQty;
                            $product->stock_qty = $newStock;
                            $product->save();

                            ProductStockMovement::create([
                                'product_id' => $product->id,
                                'product_variant_id' => null,
                                'type' => 'sale_cancel',
                                'qty' => $restoreQty,
                                'balance_after' => $newStock,
                                'reference_type' => 'sales',
                                'reference_id' => $lockedSale->id,
                                'movement_date' => now()->toDateString(),
                                'note' => "Sale cancel ({$lockedSale->invoice_no}): {$reason}",
                                'created_by' => $user->id,
                            ]);
                        }
                    }
                }
            }

            $oldState = [
                'status' => $lockedSale->status,
                'cancelled_at' => $lockedSale->cancelled_at,
                'cancel_reason' => $lockedSale->cancel_reason,
            ];

            $lockedSale->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            AuditService::log('sale_cancelled', Sale::class, $oldState, [
                'id' => $lockedSale->id,
                'invoice_no' => $lockedSale->invoice_no,
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_by' => $user->id,
            ]);

            return [
                'sale' => $lockedSale,
                'warning' => $dispatchedWarning,
            ];
        });
    }

    /**
     * Process a full or partial return on a sale.
     * Restores stock for returned catalogue items (if restock = true),
     * creates sale_returns & sale_return_items, records optional return courier expense.
     */
    public function processReturn(Sale $sale, array $data, User $user): SaleReturn
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Return reason must be at least 3 characters.']);
        }

        $itemsInput = $data['items'] ?? [];
        if (empty($itemsInput)) {
            throw ValidationException::withMessages(['items' => 'At least one item must be selected for return.']);
        }

        return DB::transaction(function () use ($sale, $data, $reason, $itemsInput, $user) {
            $lockedSale = Sale::where('id', $sale->id)
                ->with(['items'])
                ->lockForUpdate()
                ->first();

            if (! $lockedSale) {
                throw ValidationException::withMessages(['sale' => 'Sale not found.']);
            }

            if ($lockedSale->status === 'cancelled') {
                throw ValidationException::withMessages(['reason' => 'Cannot process a return on a cancelled sale.']);
            }

            $returnDate = ! empty($data['return_date']) ? Carbon::parse($data['return_date'])->format('Y-m-d') : now()->toDateString();
            $restock = ! isset($data['restock']) || (bool) $data['restock'];

            $totalReturnValue = 0.00;
            $returnQueue = [];

            foreach ($itemsInput as $index => $itemRow) {
                $saleItemId = (int) ($itemRow['sale_item_id'] ?? 0);
                $qty = (int) ($itemRow['qty'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $saleItem = $lockedSale->items->firstWhere('id', $saleItemId);
                if (! $saleItem) {
                    throw ValidationException::withMessages(["items.{$index}.sale_item_id" => "Item #{$saleItemId} does not belong to this sale."]);
                }

                $unreturnedQty = $saleItem->qty - $saleItem->returned_qty;
                if ($qty > $unreturnedQty) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => "Cannot return {$qty} units of '{$saleItem->name}'. Max returnable: {$unreturnedQty}.",
                    ]);
                }

                $lineReturnValue = round($qty * (float) $saleItem->unit_price, 2);
                $totalReturnValue += $lineReturnValue;

                $returnQueue[] = [
                    'sale_item' => $saleItem,
                    'qty' => $qty,
                    'unit_price' => (float) $saleItem->unit_price,
                    'unit_cost' => (float) $saleItem->unit_cost,
                    'line_return_value' => $lineReturnValue,
                ];
            }

            if (empty($returnQueue)) {
                throw ValidationException::withMessages(['items' => 'Please specify a return quantity greater than 0 for at least one item.']);
            }

            $refundedAmount = max(0, round((float) ($data['refunded_amount'] ?? 0), 2));
            if ($refundedAmount > (float) $lockedSale->paid_total) {
                throw ValidationException::withMessages([
                    'refunded_amount' => "Refunded amount (৳{$refundedAmount}) cannot exceed total paid by customer (৳{$lockedSale->paid_total}).",
                ]);
            }

            // Create SaleReturn
            $saleReturn = SaleReturn::create([
                'sale_id' => $lockedSale->id,
                'returned_on' => $returnDate,
                'reason' => $reason,
                'return_value' => $totalReturnValue,
                'refunded_amount' => $refundedAmount,
                'restock' => $restock,
                'created_by' => $user->id,
            ]);

            // Create Return items and handle restock
            foreach ($returnQueue as $entry) {
                $saleItem = $entry['sale_item'];
                $qty = $entry['qty'];

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'qty' => $qty,
                    'unit_price' => $entry['unit_price'],
                    'unit_cost' => $entry['unit_cost'],
                ]);

                $saleItem->increment('returned_qty', $qty);

                if ($restock && ! $saleItem->is_custom && $saleItem->product_id) {
                    $product = Product::where('id', $saleItem->product_id)->lockForUpdate()->first();
                    $variant = $saleItem->product_variant_id
                        ? ProductVariant::where('id', $saleItem->product_variant_id)->lockForUpdate()->first()
                        : null;

                    if ($variant) {
                        $newVariantStock = $variant->stock_qty + $qty;
                        $variant->stock_qty = $newVariantStock;
                        $variant->save();

                        if ($product) {
                            $product->stock_qty += $qty;
                            $product->save();
                        }

                        ProductStockMovement::create([
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'type' => 'return',
                            'qty' => $qty,
                            'balance_after' => $newVariantStock,
                            'reference_type' => 'sale_returns',
                            'reference_id' => $saleReturn->id,
                            'movement_date' => $returnDate,
                            'note' => "Return ({$lockedSale->invoice_no}): {$reason}",
                            'created_by' => $user->id,
                        ]);
                    } elseif ($product) {
                        $newStock = $product->stock_qty + $qty;
                        $product->stock_qty = $newStock;
                        $product->save();

                        ProductStockMovement::create([
                            'product_id' => $product->id,
                            'product_variant_id' => null,
                            'type' => 'return',
                            'qty' => $qty,
                            'balance_after' => $newStock,
                            'reference_type' => 'sale_returns',
                            'reference_id' => $saleReturn->id,
                            'movement_date' => $returnDate,
                            'note' => "Return ({$lockedSale->invoice_no}): {$reason}",
                            'created_by' => $user->id,
                        ]);
                    }
                }
            }

            // Determine if fully or partially returned
            $lockedSale->load('items');
            $allReturned = $lockedSale->items->every(fn ($i) => $i->returned_qty >= $i->qty);
            $newStatus = $allReturned ? 'returned' : 'partially_returned';

            $lockedSale->update([
                'status' => $newStatus,
                'updated_by' => $user->id,
            ]);

            // Optional return courier cost
            $returnCourierCost = max(0, round((float) ($data['return_courier_cost'] ?? 0), 2));
            if ($returnCourierCost > 0) {
                $courierCategory = ExpenseCategory::where('pl_group', 'courier')->first()
                    ?? ExpenseCategory::firstOrCreate(
                        ['name' => 'Courier Charges'],
                        ['pl_group' => 'courier', 'is_system' => true, 'is_active' => true]
                    );

                Expense::create([
                    'expense_category_id' => $courierCategory->id,
                    'expense_date' => $returnDate,
                    'amount' => $returnCourierCost,
                    'payment_method' => $data['return_courier_payment_method'] ?? 'cash',
                    'vendor' => 'Pathao',
                    'source' => 'return_courier',
                    'sale_id' => $lockedSale->id,
                    'sale_return_id' => $saleReturn->id,
                    'note' => "Return courier charge for Invoice {$lockedSale->invoice_no}",
                    'created_by' => $user->id,
                ]);
            }

            AuditService::log('sale_returned', SaleReturn::class, null, [
                'id' => $saleReturn->id,
                'sale_id' => $lockedSale->id,
                'invoice_no' => $lockedSale->invoice_no,
                'return_value' => $totalReturnValue,
                'refunded_amount' => $refundedAmount,
                'status' => $newStatus,
                'reason' => $reason,
                'restock' => $restock,
            ]);

            return $saleReturn->load(['items', 'sale']);
        });
    }
}
