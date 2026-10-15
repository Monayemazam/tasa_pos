<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Unified top-bar global search across invoices, customers, and products.
     * Permission-aware, fast execution (<300ms SLA target), and strictly NO cost/profit data.
     */
    public function search(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $user = $request->user();
        $query = trim((string) $request->input('q', ''));

        if (mb_strlen($query) < 1) {
            return response()->json([
                'query' => '',
                'invoices' => [],
                'customers' => [],
                'products' => [],
                'total_count' => 0,
                'elapsed_ms' => 0,
            ]);
        }

        $canViewSales = $user->hasRole('Owner') || $user->can('sales.view');
        $canViewCustomers = $user->hasRole('Owner') || $user->can('customers.view') || $user->can('sales.view');
        $canViewProducts = $user->hasRole('Owner') || $user->can('products.view');

        $invoices = [];
        $customers = [];
        $products = [];

        // 1. Invoices Search (Invoice number, customer name, customer phone)
        if ($canViewSales) {
            $invoices = Sale::where(function ($q) use ($query) {
                $q->where('invoice_no', 'like', "%{$query}%")
                    ->orWhere('customer_name', 'like', "%{$query}%")
                    ->orWhere('customer_phone', 'like', "%{$query}%");
            })
                ->select([
                    'id',
                    'invoice_no',
                    'customer_name',
                    'customer_phone',
                    'sale_date',
                    'grand_total',
                    'payment_status',
                    'status',
                ])
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'invoice_no' => $s->invoice_no,
                    'customer_name' => $s->customer_name,
                    'customer_phone' => $s->customer_phone,
                    'sale_date' => $s->sale_date ? $s->sale_date->format('Y-m-d') : null,
                    'grand_total' => (float) $s->grand_total,
                    'payment_status' => $s->payment_status,
                    'status' => $s->status,
                    'url' => "/sales/invoices/{$s->id}",
                ])
                ->all();
        }

        // 2. Customers Search (Customer name, phone number, alt phone)
        if ($canViewCustomers) {
            $customers = Customer::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('alt_phone', 'like', "%{$query}%");
            })
                ->select(['id', 'name', 'phone', 'city_name', 'address'])
                ->limit(5)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'city_name' => $c->city_name,
                    'address' => $c->address,
                    'url' => "/sales/customers?search=" . urlencode($c->phone),
                ])
                ->all();
        }

        // 3. Products Search (Name, SKU, Variant Color/SKU) - strictly NO cost_price or profit data exposed
        if ($canViewProducts) {
            $products = Product::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhereHas('variants', function ($vq) use ($query) {
                            $vq->where('color_name', 'like', "%{$query}%")
                                ->orWhere('sku', 'like', "%{$query}%")
                                ->orWhere('barcode', 'like', "%{$query}%")
                                ->orWhere('attribute_value', 'like', "%{$query}%");
                        });
                })
                ->with(['variants' => fn ($vq) => $vq->where('is_active', true)])
                ->select(['id', 'name', 'has_variants', 'variation_type', 'sku', 'selling_price', 'stock_qty', 'min_stock'])
                ->limit(8)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'has_variants' => (bool) $p->has_variants,
                    'variation_type' => $p->variation_type ?: 'Color',
                    'variants_summary' => $p->has_variants ? $p->variants->pluck('color_name')->implode(', ') : null,
                    'selling_price' => (float) $p->selling_price,
                    'stock_qty' => (int) $p->stock_qty,
                    'unit' => 'pcs',
                    'is_low_stock' => $p->stock_qty <= $p->min_stock,
                    'url' => "/inventory/products?search=" . urlencode($p->sku),
                ])
                ->all();
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'query' => $query,
            'invoices' => $invoices,
            'customers' => $customers,
            'products' => $products,
            'total_count' => count($invoices) + count($customers) + count($products),
            'elapsed_ms' => $elapsedMs,
        ]);
    }
}
