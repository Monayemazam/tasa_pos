<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', ProductCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:product_categories,name'],
        ]);

        $category = ProductCategory::create([
            'name' => trim($validated['name']),
        ]);

        AuditService::log('created', $category, null, $category->toArray(), $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category created successfully.',
                'category' => $category,
            ], 201);
        }

        return back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')->ignore($category->id)],
        ]);

        $old = $category->toArray();
        $category->update([
            'name' => trim($validated['name']),
        ]);

        AuditService::log('updated', $category, $old, $category->toArray(), $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category updated successfully.',
                'category' => $category,
            ]);
        }

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, ProductCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $category);

        $old = $category->toArray();
        $category->delete();

        AuditService::log('deleted', $category, $old, null, $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category deleted successfully.',
            ]);
        }

        return back()->with('success', 'Category deleted successfully.');
    }
}
