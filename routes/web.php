<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Inventory\AccessoryController;
use App\Http\Controllers\Inventory\LeatherController;
use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Payroll\EmployeeController;
use App\Http\Controllers\Payroll\SalaryMonthController;
use App\Http\Controllers\Payroll\SalaryPaymentController;
use App\Http\Controllers\Payroll\WorkerController;
use App\Http\Controllers\Payroll\WorkerEarningController;
use App\Http\Controllers\Payroll\WorkerPaymentController;
use App\Http\Controllers\Reports\ProfitLossController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\CourierOrderController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\InvoiceController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\SalePaymentController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : inertia('Welcome', [
            'canResetPassword' => \Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::resetPasswords()),
            'status' => session('status'),
        ]);
})->name('home');

// Dedicated Design System & Foundation Preview Route (Design & UX Guideline §13 item 1)
Route::inertia('/design', 'DesignSystem')->name('design');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'search'])->name('global.search');

    // Sales Module (PRD §6 Sales & POS)
    Route::prefix('sales')->name('sales.')->group(function () {
        // POS New Sale routes
        Route::get('/create', [PosController::class, 'create'])->name('create');
        Route::post('/', [PosController::class, 'store'])->name('store');
        Route::get('/customers/lookup', [PosController::class, 'customerLookup'])->name('customers.lookup');
        Route::get('/courier/zones', [PosController::class, 'zones'])->name('courier.zones');
        Route::get('/courier/areas', [PosController::class, 'areas'])->name('courier.areas');
        Route::get('/courier/success-rate', [PosController::class, 'successRate'])->name('courier.success-rate');

        // Customer Directory routes
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // Sale Payments route
        Route::post('/{sale}/payments', [SalePaymentController::class, 'store'])->name('payments.store');

        // Invoices / Sales List & Actions (PRD §6.2, §6.3)
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
        Route::get('/invoices/{sale}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{sale}/items', [InvoiceController::class, 'items'])->name('invoices.items');
        Route::post('/invoices/{sale}/payments', [InvoiceController::class, 'addPayment'])->name('invoices.payments');
        Route::post('/invoices/{sale}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('/invoices/{sale}/return', [InvoiceController::class, 'return'])->name('invoices.return');
        Route::post('/invoices/{sale}/retry-courier', [InvoiceController::class, 'retryCourier'])->name('invoices.retry-courier');
        Route::get('/invoices/{sale}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::get('/invoices/{sale}/print', [InvoiceController::class, 'print'])->name('invoices.print');

        // Courier Orders (Pathao) routes (PRD §7)
        Route::get('/courier', [CourierOrderController::class, 'index'])->name('courier');
        Route::post('/courier/{courierOrder}/retry', [CourierOrderController::class, 'retry'])->name('courier.retry');
        Route::post('/courier/{courierOrder}/sync', [CourierOrderController::class, 'sync'])->name('courier.sync');
        Route::post('/courier/{courierOrder}/settle-cod', [CourierOrderController::class, 'settleCod'])->name('courier.settle-cod');
        Route::put('/courier/{courierOrder}', [CourierOrderController::class, 'update'])->name('courier.update');
        Route::get('/courier/test-connection', [CourierOrderController::class, 'testConnection'])->name('courier.test-connection');
    });

    // Inventory Module (PRD §8.1 Finished Goods)
    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Products routes
        Route::get('/products', [ProductController::class, 'index'])->name('products');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::patch('/products/{product}/min-stock', [ProductController::class, 'updateMinStock'])->name('products.min-stock');
        Route::post('/products/{product}/add-stock', [ProductController::class, 'addStock'])->name('products.add-stock');
        Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
        Route::get('/products/{product}/movements', [ProductController::class, 'movements'])->name('products.movements');

        // Import routes
        Route::get('/products/import/template', [ProductController::class, 'downloadTemplate'])->name('products.import.template');
        Route::post('/products/import/preview', [ProductController::class, 'previewImport'])->name('products.import.preview');
        Route::post('/products/import/commit', [ProductController::class, 'commitImport'])->name('products.import.commit');

        // Product categories routes
        Route::get('/categories', [ProductCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [ProductCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [ProductCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

        // Leather routes (PRD §8.2 Raw Material)
        Route::get('/leather', [LeatherController::class, 'index'])->name('leather');
        Route::post('/leather/items', [LeatherController::class, 'storeItem'])->name('leather.items.store');
        Route::put('/leather/items/{item}', [LeatherController::class, 'updateItem'])->name('leather.items.update');
        Route::delete('/leather/items/{item}', [LeatherController::class, 'destroyItem'])->name('leather.items.destroy');
        Route::post('/leather/purchases', [LeatherController::class, 'storePurchase'])->name('leather.purchases.store');
        Route::post('/leather/consumptions', [LeatherController::class, 'storeConsumption'])->name('leather.consumptions.store');
        Route::post('/leather/adjust-stock', [LeatherController::class, 'adjustStock'])->name('leather.adjust-stock');
        Route::post('/leather/adjustments', [LeatherController::class, 'adjustStock']);
        Route::get('/leather/items/{item}/movements', [LeatherController::class, 'movements'])->name('leather.movements');
        Route::get('/leather/{item}/movements', [LeatherController::class, 'movements']);

        // Accessories routes (PRD §8.3 Raw Materials)
        Route::get('/accessories', [AccessoryController::class, 'index'])->name('accessories');
        Route::post('/accessories/items', [AccessoryController::class, 'storeItem'])->name('accessories.items.store');
        Route::put('/accessories/items/{item}', [AccessoryController::class, 'updateItem'])->name('accessories.items.update');
        Route::delete('/accessories/items/{item}', [AccessoryController::class, 'destroyItem'])->name('accessories.items.destroy');
        Route::post('/accessories/purchases', [AccessoryController::class, 'storePurchase'])->name('accessories.purchases.store');
        Route::post('/accessories/consumptions', [AccessoryController::class, 'storeConsumption'])->name('accessories.consumptions.store');
        Route::post('/accessories/adjust-stock', [AccessoryController::class, 'adjustStock'])->name('accessories.adjust-stock');
        Route::post('/accessories/adjustments', [AccessoryController::class, 'adjustStock']);
        Route::get('/accessories/items/{item}/movements', [AccessoryController::class, 'movements'])->name('accessories.movements');
        Route::get('/accessories/{item}/movements', [AccessoryController::class, 'movements']);

        // Universal Stock Adjustments routes (PRD §8.4)
        Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/adjustments/items', [StockAdjustmentController::class, 'getItemsByType'])->name('adjustments.items');

        // Global Inventory Search (PRD §8.5 / UI)
        Route::get('/search', [StockAdjustmentController::class, 'globalSearch'])->name('inventory.search');
    });

    // Expenses Module (PRD §9 Operating Expenses & Categories)
    Route::get('/expenses/list', [ExpenseController::class, 'index'])->name('expenses');
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');

        // Categories
        Route::post('/categories', [ExpenseController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [ExpenseController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('categories.destroy');

        // Recurring
        Route::post('/recurring', [ExpenseController::class, 'storeRecurring'])->name('recurring.store');
        Route::put('/recurring/{recurring}', [ExpenseController::class, 'updateRecurring'])->name('recurring.update');
        Route::delete('/recurring/{recurring}', [ExpenseController::class, 'destroyRecurring'])->name('recurring.destroy');
        Route::post('/recurring/{recurring}/confirm', [ExpenseController::class, 'confirmRecurring'])->name('recurring.confirm');

        // Expense Wildcard Routes
        Route::get('/{expense}/attachment', [ExpenseController::class, 'downloadAttachment'])->name('attachment');
        Route::match(['put', 'post'], '/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    });

    // Payroll Module (PRD §10.1 Salaried Employees & Monthly Sheets)
    Route::prefix('payroll')->name('payroll.')->middleware('can:payroll.view')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->withTrashed();
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/{id}/restore', [EmployeeController::class, 'restore'])->name('employees.restore');
        Route::get('/employees/{employee}/statement', [EmployeeController::class, 'statement'])->name('employees.statement')->withTrashed();

        // Salary Months (Sheet) routes
        Route::post('/months/generate', [SalaryMonthController::class, 'generate'])->name('months.generate');
        Route::put('/months/{salaryMonth?}', [SalaryMonthController::class, 'update'])->name('months.update');

        // Payments routes
        Route::post('/payments', [SalaryPaymentController::class, 'store'])->name('payments.store');
        Route::put('/payments/{salaryPayment}', [SalaryPaymentController::class, 'update'])->name('payments.update');

        // Wage Workers (PRD §10.2 Wage Workers, Running Ledgers & Weekly Payout)
        Route::get('/workers', [WorkerController::class, 'index'])->name('workers');
        Route::post('/workers', [WorkerController::class, 'store'])->name('workers.store');
        Route::get('/workers/{worker}', [WorkerController::class, 'show'])->name('workers.show')->withTrashed();
        Route::put('/workers/{worker}', [WorkerController::class, 'update'])->name('workers.update');
        Route::delete('/workers/{worker}', [WorkerController::class, 'destroy'])->name('workers.destroy');
        Route::post('/workers/{id}/restore', [WorkerController::class, 'restore'])->name('workers.restore');
        Route::get('/workers/{worker}/statement', [WorkerController::class, 'statement'])->name('workers.statement')->withTrashed();
        Route::post('/workers/batch-payout', [WorkerController::class, 'batchPayout'])->name('workers.batch_payout');

        // Worker Earnings & Payments routes
        Route::post('/workers/earnings', [WorkerEarningController::class, 'store'])->name('workers.earnings.store');
        Route::put('/workers/earnings/{workerEarning}', [WorkerEarningController::class, 'update'])->name('workers.earnings.update');
        Route::post('/workers/payments', [WorkerPaymentController::class, 'store'])->name('workers.payments.store');
        Route::put('/workers/payments/{workerPayment}', [WorkerPaymentController::class, 'update'])->name('workers.payments.update');
    });

    // Reports Module (PRD §6.5 Sales Report & §11 Profit & Loss)
    Route::prefix('reports')->name('reports.')->group(function () {
        // Sales Report (PRD §6.5 & Prompt 14)
        Route::get('/sales', [SalesReportController::class, 'index'])->name('sales')->middleware('can:reports.sales');
        Route::get('/sales/drilldown', [SalesReportController::class, 'drilldown'])->name('sales.drilldown')->middleware('can:reports.sales');
        Route::get('/sales/export/csv', [SalesReportController::class, 'exportCsv'])->name('sales.export.csv')->middleware('can:reports.sales');
        Route::get('/sales/export/excel', [SalesReportController::class, 'exportExcel'])->name('sales.export.excel')->middleware('can:reports.sales');
        Route::get('/sales/export/pdf', [SalesReportController::class, 'exportPdf'])->name('sales.export.pdf')->middleware('can:reports.sales');

        // Profit & Loss Statement (PRD §11 & Prompt 15 - Owner Only)
        Route::get('/profit-loss', [ProfitLossController::class, 'index'])->name('profit-loss')->middleware('can:reports.profit_loss');
        Route::get('/profit-loss/drilldown', [ProfitLossController::class, 'drilldown'])->name('profit-loss.drilldown')->middleware('can:reports.profit_loss');
        Route::get('/profit-loss/export/excel', [ProfitLossController::class, 'exportExcel'])->name('profit-loss.export.excel')->middleware('can:reports.profit_loss');
        Route::get('/profit-loss/export/pdf', [ProfitLossController::class, 'exportPdf'])->name('profit-loss.export.pdf')->middleware('can:reports.profit_loss');
    });

    Route::redirect('/profit-loss', '/reports/profit-loss');
});

require __DIR__.'/settings.php';
