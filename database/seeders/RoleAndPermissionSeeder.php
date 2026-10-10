<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',
            'dashboard.view_financials',

            // POS / Sales
            'sales.view',
            'sales.create',
            'sales.edit_price',
            'sales.cancel',
            'sales.return',
            'sales.add_payment',

            // Customers
            'customers.view',
            'customers.update',

            // Courier
            'courier.view',
            'courier.retry',
            'courier.settle_cod',

            // Products
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.import',

            // Leather
            'leather.view',
            'leather.create',
            'leather.update',
            'leather.delete',

            // Accessories
            'accessories.view',
            'accessories.create',
            'accessories.update',
            'accessories.delete',

            // Stock adjustments
            'adjustments.view',
            'adjustments.create',

            // Cost visibility
            'inventory.view_costs',

            // Expenses
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',

            // Payroll
            'payroll.view',
            'payroll.create',
            'payroll.update',
            'payroll.delete',

            // Reports
            'reports.sales',
            'reports.sales_profit',
            'reports.profit_loss',

            // Settings
            'settings.manage',
            'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 1. Create or update Owner role (is_locked = 1)
        $ownerRole = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'web'],
            ['is_locked' => true, 'description' => 'Full access to all modules, financial metrics, and settings.']
        );
        $ownerRole->update(['is_locked' => true, 'description' => 'Full access to all modules, financial metrics, and settings.']);
        $ownerRole->syncPermissions(Permission::all());

        // 2. Create or update Staff role (is_locked = 0)
        $staffRole = Role::firstOrCreate(
            ['name' => 'Staff', 'guard_name' => 'web'],
            ['is_locked' => false, 'description' => 'Operational access to POS, invoices, inventory, and expense logging.']
        );

        $staffPermissions = [
            'dashboard.view',
            'sales.view',
            'sales.create',
            'sales.edit_price',
            'sales.add_payment',
            'customers.view',
            'customers.update',
            'courier.view',
            'courier.retry',
            'products.view',
            'products.create',
            'products.update',
            'leather.view',
            'leather.create',
            'leather.update',
            'accessories.view',
            'accessories.create',
            'accessories.update',
            'adjustments.view',
            'adjustments.create',
            'expenses.view',
            'expenses.create',
            'reports.sales',
        ];

        $staffRole->syncPermissions($staffPermissions);
    }
}
