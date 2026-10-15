<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AddStockRequest;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Inventory\ProductImportService;
use App\Services\Inventory\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected ProductImportService $importService
    ) {}

    /**
     * Display a listing of finished goods products with variant support.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $user = $request->user();
        $canViewCost = $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');

        $query = Product::with(['category', 'variants']);

        // Search by Name, SKU, or Variant Color/SKU
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('color_name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('attribute_value', 'like', "%{$search}%");
                    });
            });
        }

        // Active/Inactive filter
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('product_category_id', $categoryId);
        }

        // Low stock filter
        if ($request->boolean('low_stock')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereColumn('stock_qty', '<=', 'min_stock')
                        ->orWhereHas('variants', function ($vq) {
                            $vq->where('is_active', true)
                                ->whereColumn('stock_qty', '<=', 'min_stock');
                        });
                });
        }

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'sku', 'stock_qty', 'selling_price', 'created_at'];
        if ($canViewCost) {
            $allowedSorts[] = 'cost_price';
        }

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        $products = $query->paginate(25)->withQueryString();

        // Transform collection to strictly redact cost price for unauthorized users
        $products->through(function (Product $product) use ($canViewCost) {
            $variants = $product->variants->map(function (ProductVariant $v) use ($canViewCost) {
                return [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'sku' => $v->sku,
                    'color_name' => $v->color_name,
                    'attribute_name' => $v->attribute_name,
                    'attribute_value' => $v->attribute_value,
                    'cost_price' => $canViewCost ? (float) $v->cost_price : null,
                    'selling_price' => (float) $v->selling_price,
                    'stock_qty' => (int) $v->stock_qty,
                    'min_stock' => (int) $v->min_stock,
                    'barcode' => $v->barcode,
                    'is_active' => (bool) $v->is_active,
                    'is_low_stock' => (bool) ($v->is_active && $v->stock_qty <= $v->min_stock),
                ];
            })->values()->all();

            $hasLowStockVariant = collect($variants)->contains(fn ($v) => $v['is_low_stock']);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'has_variants' => (bool) $product->has_variants,
                'variation_type' => $product->variation_type ?: 'Color',
                'sku' => $product->sku,
                'product_category_id' => $product->product_category_id,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'cost_price' => $canViewCost ? (float) $product->cost_price : null,
                'selling_price' => (float) $product->selling_price,
                'stock_qty' => (int) $product->stock_qty,
                'min_stock' => (int) $product->min_stock,
                'is_active' => (bool) $product->is_active,
                'is_low_stock' => (bool) (($product->is_active && $product->stock_qty <= $product->min_stock) || $hasLowStockVariant),
                'variants' => $variants,
                'variants_count' => count($variants),
                'created_at' => $product->created_at?->format('d M Y'),
            ];
        });

        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'low_stock_count' => Product::where('is_active', true)
                ->where(function ($q) {
                    $q->whereColumn('stock_qty', '<=', 'min_stock')
                        ->orWhereHas('variants', function ($vq) {
                            $vq->where('is_active', true)->whereColumn('stock_qty', '<=', 'min_stock');
                        });
                })->count(),
            'total_stock_qty' => (int) Product::sum('stock_qty'),
        ];

        return Inertia::render('inventory/Products', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'category_id' => $request->input('category_id', ''),
                'low_stock' => $request->boolean('low_stock'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'canViewCost' => $canViewCost,
            'canManageProducts' => $user->can('products.create') || $user->can('products.update'),
            'canDeleteProducts' => $user->can('products.delete'),
            'canImport' => $user->can('products.import'),
            'canAdjust' => $user->can('adjustments.create'),
            'stats' => $stats,
            'allowNegativeStock' => (string) Setting::get('inventory.allow_negative_stock', '0') === '1',
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = $this->productService->createProduct($request->validated(), $request->user());

        return back()->with('success', "Product '{$product->name}' created successfully.");
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->productService->updateProduct($product, $request->validated(), $request->user());

        return back()->with('success', "Product '{$product->name}' updated successfully.");
    }

    /**
     * Soft-delete/archive the specified product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $name = $product->name;
        $this->productService->deleteProduct($product, request()->user());

        return back()->with('success', "Product '{$name}' archived successfully.");
    }

    /**
     * Inline update for minimum stock only.
     */
    public function updateMinStock(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'min_stock' => ['required', 'integer', 'min:0'],
        ]);

        $updatedProduct = $this->productService->updateMinStock($product, (int) $validated['min_stock'], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'min_stock' => $updatedProduct->min_stock,
                'message' => 'Minimum stock updated.',
            ]);
        }

        return back()->with('success', 'Minimum stock updated.');
    }

    /**
     * Add stock / production entry (with variant support).
     */
    public function addStock(AddStockRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('addStock', $product);

        $validated = $request->validated();
        $variantId = ! empty($validated['variant_id']) ? (int) $validated['variant_id'] : null;

        $this->productService->addProductionEntry(
            $product,
            (int) $validated['qty'],
            $validated['date'],
            $validated['note'] ?? null,
            $request->user(),
            $variantId
        );

        return back()->with('success', "Added {$validated['qty']} pieces to {$product->name} stock.");
    }

    /**
     * Stock adjustment for finished goods (with variant support).
     */
    public function adjustStock(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);

        $validated = $request->validated();
        $adjustedOn = $validated['adjusted_on'] ?? $validated['date'] ?? now()->toDateString();
        $variantId = ! empty($validated['variant_id'])
            ? (int) $validated['variant_id']
            : (! empty($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null);

        $this->productService->adjustStock(
            $product,
            (int) $validated['qty'],
            $adjustedOn,
            $validated['reason'],
            $validated['note'] ?? null,
            $request->user(),
            $variantId
        );

        $sign = $validated['qty'] > 0 ? "+{$validated['qty']}" : "{$validated['qty']}";

        return back()->with('success', "Stock adjustment ({$sign} pcs) recorded for {$product->name}.");
    }

    /**
     * Get stock movement history for a product and its variants.
     */
    public function movements(Product $product): JsonResponse
    {
        $this->authorize('viewMovements', $product);

        $movements = $product->movements()
            ->with(['creator:id,name', 'variant:id,color_name,sku'])
            ->orderBy('id', 'desc')
            ->paginate(50);

        $transformed = $movements->getCollection()->map(function ($m) {
            $variantLabel = $m->variant ? " [{$m->variant->color_name}]" : '';

            return [
                'id' => $m->id,
                'product_variant_id' => $m->product_variant_id,
                'variant_name' => $m->variant?->color_name,
                'variant_sku' => $m->variant?->sku,
                'type' => $m->type,
                'qty' => (int) $m->qty,
                'balance_after' => (int) $m->balance_after,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'movement_date' => $m->movement_date?->format('d M Y'),
                'note' => $m->note ? "{$m->note}{$variantLabel}" : $variantLabel,
                'creator_name' => $m->creator?->name ?? 'System',
                'created_at' => $m->created_at?->format('d M Y, h:i A'),
            ];
        });

        $paginated = $movements->toArray();
        $paginated['data'] = $transformed->values()->all();

        return response()->json($paginated);
    }

    /**
     * Download Excel or CSV import template.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorize('import', Product::class);

        $format = $request->query('format', 'xlsx');

        return $this->importService->downloadTemplate($format);
    }

    /**
     * Preview parsed rows before committing import.
     */
    public function previewImport(Request $request): JsonResponse
    {
        $this->authorize('import', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $canViewCost = $request->user()->hasRole('Owner')
            || $request->user()->can('inventory.view_costs')
            || $request->user()->can('inventory.cost.view');

        $preview = $this->importService->preview($request->file('file'), $canViewCost);

        return response()->json($preview);
    }

    /**
     * Commit confirmed valid import rows.
     */
    public function commitImport(Request $request): JsonResponse
    {
        $this->authorize('import', Product::class);

        $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->importService->commit(
            $request->input('rows'),
            $request->input('filename'),
            $request->user()
        );

        return response()->json([
            'message' => "Successfully imported {$result['success_count']} products.",
            'result' => $result,
        ]);
    }
}
