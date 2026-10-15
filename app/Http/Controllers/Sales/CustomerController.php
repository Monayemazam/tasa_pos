<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\Courier\PathaoService;
use App\Services\Sales\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected PathaoService $pathaoService
    ) {
    }

    /**
     * Display customer directory with computed lifetime metrics.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['search', 'has_due']);
        $customers = $this->customerService->paginate($filters, 15);
        $cities = $this->pathaoService->getCities();

        return Inertia::render('sales/Customers', [
            'customers' => $customers,
            'filters' => $filters,
            'cities' => $cities,
        ]);
    }

    /**
     * Get detailed customer profile with complete order history.
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $profile = $this->customerService->getProfile($customer);
        $successRate = $this->pathaoService->getCustomerSuccessRate($customer->phone);
        $profile['success_rate_hint'] = $successRate;

        return response()->json($profile);
    }

    /**
     * Store new customer.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        AuditService::log('customer_created', Customer::class, null, $customer->toArray());

        return back()->with('success', "Customer {$customer->name} created successfully.");
    }

    /**
     * Update customer profile.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $old = $customer->toArray();
        $customer->update($request->validated());

        AuditService::log('customer_updated', Customer::class, $old, $customer->toArray());

        return back()->with('success', "Customer {$customer->name} updated successfully.");
    }

    /**
     * Delete customer (Owner only).
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        // Safety check: soft-delete customer while preserving sales history
        $customer->delete();

        AuditService::log('customer_deleted', Customer::class, ['id' => $customer->id, 'phone' => $customer->phone], null);

        return back()->with('success', "Customer {$customer->name} archived successfully.");
    }
}
