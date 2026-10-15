<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerService
{
    /**
     * Look up a single customer by phone with computed totals and recent orders.
     */
    public function lookup(string $phone): ?array
    {
        $cleanPhone = trim($phone);
        if (empty($cleanPhone)) {
            return null;
        }

        $customer = Customer::where('phone', $cleanPhone)->first();
        if (! $customer) {
            return null;
        }

        return $this->formatCustomerData($customer);
    }

    /**
     * Search customers by phone or name for quick lookup.
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $clean = trim($query);
        if (empty($clean)) {
            return collect();
        }

        return Customer::query()
            ->where(function (Builder $q) use ($clean) {
                $q->where('phone', 'like', "%{$clean}%")
                    ->orWhere('name', 'like', "%{$clean}%")
                    ->orWhere('alt_phone', 'like', "%{$clean}%");
            })
            ->withCount(['sales as total_orders' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }])
            ->withSum(['sales as total_spent' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'grand_total')
            ->withSum(['sales as outstanding_due' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'due_amount')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $c) => $this->formatCustomerData($c));
    }

    /**
     * Paginated list of customers for Customers page.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->withCount(['sales as total_orders' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }])
            ->withSum(['sales as total_spent' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'grand_total')
            ->withSum(['sales as outstanding_due' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'due_amount');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('alt_phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['has_due']) && $filters['has_due'] === '1') {
            $query->having('outstanding_due', '>', 0);
        }

        return $query->latest('id')->paginate($perPage)->through(fn (Customer $c) => $this->formatCustomerData($c));
    }

    /**
     * Get detailed profile of a customer with order history.
     */
    public function getProfile(Customer $customer): array
    {
        $orders = Sale::where('customer_id', $customer->id)
            ->with(['items', 'courierOrder', 'payments'])
            ->latest('sold_at')
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'sale_date' => $sale->sale_date?->format('Y-m-d'),
                    'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                    'grand_total' => (float) $sale->grand_total,
                    'paid_total' => (float) $sale->paid_total,
                    'due_amount' => (float) $sale->due_amount,
                    'payment_status' => $sale->payment_status,
                    'status' => $sale->status,
                    'courier_mode' => $sale->courier_mode,
                    'courier_status' => $sale->courierOrder?->status,
                    'consignment_id' => $sale->courierOrder?->consignment_id,
                    'items_count' => $sale->items->count(),
                    'items_summary' => $sale->items->map(fn ($i) => "{$i->name} (x{$i->qty})")->implode(', '),
                ];
            });

        $data = $this->formatCustomerData($customer);
        $data['order_history'] = $orders;

        return $data;
    }

    /**
     * Find or create/update customer during sale creation.
     */
    public function createOrUpdateFromSale(array $data): Customer
    {
        $customer = Customer::where('phone', $data['phone'])->first();

        $updateData = [
            'name' => $data['name'],
            'address' => $data['address'],
        ];

        if (array_key_exists('alt_phone', $data)) {
            $updateData['alt_phone'] = $data['alt_phone'];
        }

        if (! empty($data['pathao_city_id'])) {
            $updateData['pathao_city_id'] = $data['pathao_city_id'];
            $updateData['city_name'] = $data['city_name'] ?? null;
        }

        if (! empty($data['pathao_zone_id'])) {
            $updateData['pathao_zone_id'] = $data['pathao_zone_id'];
            $updateData['zone_name'] = $data['zone_name'] ?? null;
        }

        if (! empty($data['pathao_area_id'])) {
            $updateData['pathao_area_id'] = $data['pathao_area_id'];
            $updateData['area_name'] = $data['area_name'] ?? null;
        }

        if (! empty($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        if ($customer) {
            $customer->update($updateData);

            return $customer;
        }

        $updateData['phone'] = $data['phone'];

        return Customer::create($updateData);
    }

    /**
     * Format customer data array with clean attributes.
     */
    protected function formatCustomerData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'alt_phone' => $customer->alt_phone,
            'address' => $customer->address,
            'pathao_city_id' => $customer->pathao_city_id,
            'pathao_zone_id' => $customer->pathao_zone_id,
            'pathao_area_id' => $customer->pathao_area_id,
            'city_name' => $customer->city_name,
            'zone_name' => $customer->zone_name,
            'area_name' => $customer->area_name,
            'notes' => $customer->notes,
            'total_orders' => (int) ($customer->total_orders ?? $customer->total_orders_attribute ?? 0),
            'total_spent' => (float) ($customer->total_spent ?? $customer->total_spent_attribute ?? 0),
            'outstanding_due' => (float) ($customer->outstanding_due ?? $customer->outstanding_due_attribute ?? 0),
            'created_at' => $customer->created_at?->format('Y-m-d H:i'),
        ];
    }
}
