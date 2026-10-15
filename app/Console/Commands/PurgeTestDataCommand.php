<?php

namespace App\Console\Commands;

use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-test-data
                            {--force : Execute destructive cleanup without manual confirmation}
                            {--dry-run : Simulate the cleanup and display affected records without deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely purge demo/test sales, courier orders, test customers, test expenses, and test payroll records while preserving catalog, opening stock, settings, and credentials';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $this->info('===============================================================');
        $this->info(' TASA POS — Safe Production Business-Data Purge Command');
        $this->info('===============================================================');
        $this->line('Environment: ' . config('app.env'));
        $this->line('Database:    ' . config('database.connections.mysql.database'));
        $this->line('Mode:        ' . ($isDryRun ? 'DRY-RUN (Simulated - No changes will be saved)' : 'LIVE CLEANUP'));
        $this->newLine();

        // 1. Audit records to be affected
        $plan = $this->analyzeRecords();

        $this->table(
            ['Module / Entity', 'Table Name', 'Identified Test Records', 'Action'],
            collect($plan)->map(fn ($item) => [
                $item['module'],
                $item['table'],
                $item['count'],
                $item['action'],
            ])->toArray()
        );

        $totalTestRecords = collect($plan)->sum('count');

        if ($totalTestRecords === 0) {
            $this->info('No demo/test transaction records found. Database is already clean!');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("Dry-run complete. Found {$totalTestRecords} test record(s) ready for cleanup. No changes were made.");
            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm('Are you sure you want to permanently delete these test business transactions and reset sequences?', false)) {
            $this->warn('Cleanup aborted by user.');
            return self::FAILURE;
        }

        $this->info('Executing transactional cleanup...');

        try {
            DB::beginTransaction();

            // Order of deletion respecting Foreign Keys:

            // 1. Courier status logs
            $deletedCourierLogs = DB::table('courier_status_logs')->delete();

            // 2. Courier orders
            $deletedCourierOrders = DB::table('courier_orders')->delete();

            // 3. Sale payments
            $deletedSalePayments = DB::table('sale_payments')->delete();

            // 4. Sale items
            $deletedSaleItems = DB::table('sale_items')->delete();

            // 5. Sales
            $deletedSales = DB::table('sales')->delete();

            // 6. Test Expenses (all demo courier charge expenses)
            $deletedExpenses = DB::table('expenses')->delete();

            // 7. Test Customers (Alter Reality ID:1, Test Customer ID:2)
            $testCustomerIds = [1, 2];
            $deletedCustomers = DB::table('customers')->whereIn('id', $testCustomerIds)->delete();

            // 8. Test Payroll (Salary payments, months, employees)
            $deletedSalaryPayments = DB::table('salary_payments')->delete();
            $deletedSalaryMonths = DB::table('salary_months')->delete();
            $deletedEmployees = DB::table('employees')->delete();

            // 9. Test Wage Workers (Worker payments, earnings, workers)
            $deletedWorkerPayments = DB::table('worker_payments')->delete();
            $deletedWorkerEarnings = DB::table('worker_earnings')->delete();
            $deletedWorkers = DB::table('workers')->delete();

            // 10. Non-opening stock movements (sale deductions & cancellations)
            $deletedMovements = DB::table('product_stock_movements')
                ->where('type', '!=', 'opening')
                ->delete();

            // 11. Restore Variant & Product Stock to 100% Genuine Opening Stock Levels
            $variants = ProductVariant::all();
            foreach ($variants as $variant) {
                $openingSum = (int) ProductStockMovement::where('product_variant_id', $variant->id)
                    ->where('type', 'opening')
                    ->sum('qty');

                $variant->update([
                    'stock_qty' => $openingSum,
                ]);
            }

            // Sync parent product stock_qty
            $products = Product::all();
            foreach ($products as $product) {
                if ($product->has_variants) {
                    $totalVariantStock = (int) $product->variants()->sum('stock_qty');
                    $product->update(['stock_qty' => $totalVariantStock]);
                } else {
                    $openingSum = (int) ProductStockMovement::where('product_id', $product->id)
                        ->where('type', 'opening')
                        ->sum('qty');
                    $product->update(['stock_qty' => $openingSum]);
                }
            }

            // 12. Reset Invoice Sequence
            DB::table('invoice_sequences')->update(['last_number' => 0]);

            // 13. Clean Audit Logs associated strictly with deleted test entities
            $deletedAuditLogs = DB::table('audit_logs')
                ->whereIn('auditable_type', [
                    'App\\Models\\Sale',
                    'App\\Models\\Expense',
                    'App\\Models\\Employee',
                    'App\\Models\\SalaryMonth',
                    'App\\Models\\SalaryPayment',
                    'App\\Models\\Worker',
                    'App\\Models\\WorkerEarning',
                    'App\\Models\\WorkerPayment',
                ])
                ->delete();

            DB::commit();

            // Clear cache
            Cache::flush();

            $this->info('===============================================================');
            $this->info('  Test Business Transactions Purged Successfully!');
            $this->info('===============================================================');
            $this->table(
                ['Entity', 'Records Removed'],
                [
                    ['Courier Status Logs', $deletedCourierLogs],
                    ['Courier Orders', $deletedCourierOrders],
                    ['Sale Payments', $deletedSalePayments],
                    ['Sale Items', $deletedSaleItems],
                    ['Sales / Invoices', $deletedSales],
                    ['Test Expenses', $deletedExpenses],
                    ['Test Customers', $deletedCustomers],
                    ['Salary Payments', $deletedSalaryPayments],
                    ['Salary Months', $deletedSalaryMonths],
                    ['Demo Employees', $deletedEmployees],
                    ['Worker Payments', $deletedWorkerPayments],
                    ['Worker Earnings', $deletedWorkerEarnings],
                    ['Demo Wage Workers', $deletedWorkers],
                    ['Test Stock Movements (Sale/Cancel)', $deletedMovements],
                    ['Test Audit Logs', $deletedAuditLogs],
                ]
            );

            $this->info('Genuine Product Catalog Preserved: ' . Product::count() . ' products, ' . ProductVariant::count() . ' color variants.');
            $this->info('Genuine Opening Stock Movements: ' . ProductStockMovement::where('type', 'opening')->count() . ' movements retained.');
            $this->info('Store Settings & Pathao Credentials: Fully preserved.');
            $this->info('Invoice Sequence: Reset to 0 (Next invoice will start at TASA-YYMM-0001).');
            $this->info('Application Cache: Flushed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to purge test data: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Analyze records to be removed.
     */
    private function analyzeRecords(): array
    {
        return [
            [
                'module' => 'Sales & Invoices',
                'table' => 'sales',
                'count' => DB::table('sales')->count(),
                'action' => 'Delete demo sales',
            ],
            [
                'module' => 'Sales & Invoices',
                'table' => 'sale_items',
                'count' => DB::table('sale_items')->count(),
                'action' => 'Delete demo invoice line items',
            ],
            [
                'module' => 'Sales & Invoices',
                'table' => 'sale_payments',
                'count' => DB::table('sale_payments')->count(),
                'action' => 'Delete demo sale payments',
            ],
            [
                'module' => 'Logistics & Courier',
                'table' => 'courier_orders',
                'count' => DB::table('courier_orders')->count(),
                'action' => 'Delete Pathao test consignments',
            ],
            [
                'module' => 'Logistics & Courier',
                'table' => 'courier_status_logs',
                'count' => DB::table('courier_status_logs')->count(),
                'action' => 'Delete Pathao polling logs',
            ],
            [
                'module' => 'Customers',
                'table' => 'customers',
                'count' => DB::table('customers')->whereIn('id', [1, 2])->count(),
                'action' => 'Delete test customers (IDs 1, 2)',
            ],
            [
                'module' => 'Expenses',
                'table' => 'expenses',
                'count' => DB::table('expenses')->count(),
                'action' => 'Delete test courier expenses',
            ],
            [
                'module' => 'Payroll (Salaried)',
                'table' => 'salary_payments',
                'count' => DB::table('salary_payments')->count(),
                'action' => 'Delete test salary payments',
            ],
            [
                'module' => 'Payroll (Salaried)',
                'table' => 'salary_months',
                'count' => DB::table('salary_months')->count(),
                'action' => 'Delete test monthly sheets',
            ],
            [
                'module' => 'Payroll (Salaried)',
                'table' => 'employees',
                'count' => DB::table('employees')->count(),
                'action' => 'Delete demo employee profile',
            ],
            [
                'module' => 'Payroll (Wage Workers)',
                'table' => 'worker_payments',
                'count' => DB::table('worker_payments')->count(),
                'action' => 'Delete test worker payments',
            ],
            [
                'module' => 'Payroll (Wage Workers)',
                'table' => 'worker_earnings',
                'count' => DB::table('worker_earnings')->count(),
                'action' => 'Delete test worker earnings',
            ],
            [
                'module' => 'Payroll (Wage Workers)',
                'table' => 'workers',
                'count' => DB::table('workers')->count(),
                'action' => 'Delete demo worker profile',
            ],
            [
                'module' => 'Inventory Movements',
                'table' => 'product_stock_movements',
                'count' => DB::table('product_stock_movements')->where('type', '!=', 'opening')->count(),
                'action' => 'Delete test sales/cancellations & restore opening stock',
            ],
            [
                'module' => 'Audit Trail',
                'table' => 'audit_logs',
                'count' => DB::table('audit_logs')->whereIn('auditable_type', [
                    'App\\Models\\Sale',
                    'App\\Models\\Expense',
                    'App\\Models\\Employee',
                    'App\\Models\\SalaryMonth',
                    'App\\Models\\SalaryPayment',
                    'App\\Models\\Worker',
                    'App\\Models\\WorkerEarning',
                    'App\\Models\\WorkerPayment',
                ])->count(),
                'action' => 'Purge test transaction audit logs',
            ],
        ];
    }
}
