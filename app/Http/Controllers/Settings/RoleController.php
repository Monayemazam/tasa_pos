<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Section definitions mapping sections to action permissions.
     */
    protected array $sections = [
        'Dashboard' => [
            'description' => 'Summary metrics, charts, and financial indicators',
            'permissions' => [
                'view' => 'dashboard.view',
                'special' => [
                    ['key' => 'dashboard.view_financials', 'label' => 'View Financials (Profit/Payroll KPIs)'],
                ],
            ],
        ],
        'POS & Sales' => [
            'description' => 'Cart, barcode/manual sale entry, and invoice generation',
            'permissions' => [
                'view' => 'sales.view',
                'create' => 'sales.create',
                'special' => [
                    ['key' => 'sales.edit_price', 'label' => 'Edit Selling Price at Sale'],
                    ['key' => 'sales.add_payment', 'label' => 'Record Additional Payment'],
                    ['key' => 'sales.cancel', 'label' => 'Cancel Invoices (Owner Default)'],
                    ['key' => 'sales.return', 'label' => 'Process Item Returns (Owner Default)'],
                ],
            ],
        ],
        'Customers' => [
            'description' => 'Customer profile, order history, and contact details',
            'permissions' => [
                'view' => 'customers.view',
                'update' => 'customers.update',
            ],
        ],
        'Courier (Pathao)' => [
            'description' => 'Courier consignments, dispatch, and COD tracking',
            'permissions' => [
                'view' => 'courier.view',
                'special' => [
                    ['key' => 'courier.retry', 'label' => 'Retry Failed Courier Orders'],
                    ['key' => 'courier.settle_cod', 'label' => 'Settle Cash On Delivery (COD)'],
                ],
            ],
        ],
        'Inventory — Finished Products' => [
            'description' => 'Product catalogue, prices, and stock movements',
            'permissions' => [
                'view' => 'products.view',
                'create' => 'products.create',
                'update' => 'products.update',
                'delete' => 'products.delete',
                'special' => [
                    ['key' => 'products.import', 'label' => 'Bulk Import via Excel'],
                    ['key' => 'inventory.view_costs', 'label' => 'View Cost & Purchase Prices'],
                ],
            ],
        ],
        'Inventory — Raw Leather' => [
            'description' => 'Leather grades, sq ft purchases, and cutting consumptions',
            'permissions' => [
                'view' => 'leather.view',
                'create' => 'leather.create',
                'update' => 'leather.update',
                'delete' => 'leather.delete',
            ],
        ],
        'Inventory — Accessories' => [
            'description' => 'Hardware, threads, buckles, and consumables stock',
            'permissions' => [
                'view' => 'accessories.view',
                'create' => 'accessories.create',
                'update' => 'accessories.update',
                'delete' => 'accessories.delete',
            ],
        ],
        'Stock Adjustments' => [
            'description' => 'Manual write-offs, lost items, and recount balances',
            'permissions' => [
                'view' => 'adjustments.view',
                'create' => 'adjustments.create',
            ],
        ],
        'Expenses' => [
            'description' => 'Operational expenditures, categories, and bills',
            'permissions' => [
                'view' => 'expenses.view',
                'create' => 'expenses.create',
                'update' => 'expenses.update',
                'delete' => 'expenses.delete',
            ],
        ],
        'Payroll (Salaries & Wages)' => [
            'description' => 'Monthly staff salary sheets and daily/piece-rate wage ledger',
            'permissions' => [
                'view' => 'payroll.view',
                'create' => 'payroll.create',
                'update' => 'payroll.update',
                'delete' => 'payroll.delete',
            ],
        ],
        'Reports' => [
            'description' => 'Sales reports, profitability analysis, and P&L statements',
            'permissions' => [
                'view' => 'reports.sales',
                'special' => [
                    ['key' => 'reports.sales_profit', 'label' => 'Sales Profit & Margin Columns'],
                    ['key' => 'reports.profit_loss', 'label' => 'Profit & Loss (P&L) Statement'],
                ],
            ],
        ],
        'Settings & Administration' => [
            'description' => 'Shop info, invoice branding, roles, and audit trail',
            'permissions' => [
                'view' => 'settings.manage',
                'special' => [
                    ['key' => 'users.manage', 'label' => 'Manage User Accounts'],
                    ['key' => 'roles.manage', 'label' => 'Manage Roles & Permissions'],
                ],
            ],
        ],
    ];

    /**
     * Display a listing of the roles.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderByDesc('is_locked')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_locked' => (bool) $role->is_locked,
                    'users_count' => $role->users_count,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                ];
            });

        $allPermissions = Permission::orderBy('name')->pluck('name')->toArray();

        return Inertia::render('settings/Roles', [
            'roles' => $roles,
            'sections' => $this->sections,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'description' => $validated['description'] ?? null,
            'is_locked' => false,
        ]);

        $role->syncPermissions($validated['permissions']);

        // Invalidate cached permissions so changes take effect immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        AuditService::log(
            'role_created',
            $role,
            null,
            [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $validated['permissions'],
            ]
        );

        return back()->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        if ($role->is_locked) {
            abort(403, 'The Owner role is locked with full access and cannot be modified.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name,'.$role->id],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();
        $oldRoleData = [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $oldPermissions,
        ];

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->syncPermissions($validated['permissions']);

        // Invalidate cached permissions immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newRoleData = [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $validated['permissions'],
        ];

        AuditService::log(
            'role_updated',
            $role,
            $oldRoleData,
            $newRoleData
        );

        return back()->with('success', "Permissions for '{$role->name}' updated successfully.");
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->is_locked) {
            abort(403, 'The locked Owner role cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->withErrors([
                'error' => "Cannot delete role '{$role->name}' because it has {$role->users()->count()} assigned user(s). Reassign them first.",
            ]);
        }

        $roleData = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->toArray(),
        ];

        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        AuditService::log(
            'role_deleted',
            $role,
            $roleData,
            ['deleted_at' => now()->toDateTimeString()]
        );

        return back()->with('success', "Role '{$roleData['name']}' deleted successfully.");
    }
}
