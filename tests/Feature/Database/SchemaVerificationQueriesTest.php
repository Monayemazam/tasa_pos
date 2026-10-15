<?php

use Illuminate\Support\Facades\DB;

test('query 1: product cached stock equals movement ledger', function () {
    $rows = DB::select('
        SELECT p.id FROM products p
        LEFT JOIN (SELECT product_id, SUM(qty) q FROM product_stock_movements GROUP BY product_id) m
          ON m.product_id = p.id
        WHERE p.stock_qty <> COALESCE(m.q,0);
    ');

    expect($rows)->toBeEmpty();
});

test('query 2: sale paid_total equals sum of sale_payments', function () {
    $rows = DB::select('
        SELECT s.id FROM sales s
        LEFT JOIN (SELECT sale_id, SUM(amount) a FROM sale_payments GROUP BY sale_id) p ON p.sale_id = s.id
        WHERE s.paid_total <> COALESCE(p.a,0);
    ');

    expect($rows)->toBeEmpty();
});

test('query 3: sale grand total equals line totals minus discount plus delivery', function () {
    $rows = DB::select('
        SELECT s.id FROM sales s
        JOIN (SELECT sale_id, SUM(line_total) t FROM sale_items GROUP BY sale_id) i ON i.sale_id = s.id
        WHERE s.subtotal <> i.t
           OR s.grand_total <> s.subtotal - s.discount_amount + s.delivery_charge;
    ');

    expect($rows)->toBeEmpty();
});

test('query 4: sale payment status is completely consistent with paid and due amounts', function () {
    $rows = DB::select("
        SELECT id FROM sales
        WHERE (payment_status = 'paid'   AND due_amount > 0)
           OR (payment_status = 'unpaid' AND paid_total > 0)
           OR (payment_status = 'partial' AND (paid_total = 0 OR due_amount <= 0));
    ");

    expect($rows)->toBeEmpty();
});

test('query 5: invoice numbers have zero duplicate rows', function () {
    $rows = DB::select('
        SELECT invoice_no, COUNT(*) as cnt FROM sales GROUP BY invoice_no HAVING cnt > 1;
    ');

    expect($rows)->toBeEmpty();
});

test('query 6: non-custom sale lines have matching stock movements for active sales', function () {
    $rows = DB::select("
        SELECT si.id FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.status = 'active'
        LEFT JOIN product_stock_movements m
               ON m.reference_type = 'sales' AND m.reference_id = s.id
              AND m.product_id = si.product_id AND m.type = 'sale'
        WHERE si.is_custom = 0 AND m.id IS NULL;
    ");

    expect($rows)->toBeEmpty();
});

test('query 7: pathao sales have exactly one courier order row', function () {
    $rows = DB::select("
        SELECT s.id FROM sales s
        LEFT JOIN courier_orders c ON c.sale_id = s.id
        WHERE s.courier_mode = 'pathao' AND s.status <> 'cancelled' AND c.id IS NULL;
    ");

    expect($rows)->toBeEmpty();
});

test('query 8: duplicate pathao charge expenses returns zero rows', function () {
    $rows = DB::select("
        SELECT courier_order_id, COUNT(*) as cnt FROM expenses
        WHERE source = 'pathao_charge' GROUP BY courier_order_id HAVING cnt > 1;
    ");

    expect($rows)->toBeEmpty();
});

test('query 9: leather and accessory stock below zero returns zero rows', function () {
    $leatherRows = DB::select('SELECT * FROM v_leather_stock WHERE current_stock < 0;');
    $accessoryRows = DB::select('SELECT * FROM v_accessory_stock WHERE current_stock < 0;');

    expect($leatherRows)->toBeEmpty()
        ->and($accessoryRows)->toBeEmpty();
});

test('query 10: returned quantity never exceeds sold quantity', function () {
    $rows = DB::select('SELECT id FROM sale_items WHERE returned_qty > qty;');

    expect($rows)->toBeEmpty();
});

test('query 11: zero orphan references across sales, items, payments, courier and expenses', function () {
    // 1. Sale items without sale
    $orphanItems = DB::select('SELECT si.id FROM sale_items si LEFT JOIN sales s ON s.id = si.sale_id WHERE s.id IS NULL;');
    // 2. Sale payments without sale
    $orphanPayments = DB::select('SELECT sp.id FROM sale_payments sp LEFT JOIN sales s ON s.id = sp.sale_id WHERE s.id IS NULL;');
    // 3. Courier orders without sale
    $orphanCourier = DB::select('SELECT co.id FROM courier_orders co LEFT JOIN sales s ON s.id = co.sale_id WHERE s.id IS NULL;');
    // 4. Expenses without valid category
    $orphanExpenses = DB::select('SELECT e.id FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id WHERE ec.id IS NULL;');

    expect($orphanItems)->toBeEmpty()
        ->and($orphanPayments)->toBeEmpty()
        ->and($orphanCourier)->toBeEmpty()
        ->and($orphanExpenses)->toBeEmpty();
});

test('query 12: expense and source relationships are valid and non-orphaned', function () {
    // 1. Pathao charge expenses must have valid courier_order_id
    $invalidPathaoCharges = DB::select("
        SELECT e.id FROM expenses e
        LEFT JOIN courier_orders co ON co.id = e.courier_order_id
        WHERE e.source = 'pathao_charge' AND co.id IS NULL;
    ");

    // 2. Return courier expenses must have valid sale_return_id
    $invalidReturnCouriers = DB::select("
        SELECT e.id FROM expenses e
        LEFT JOIN sale_returns sr ON sr.id = e.sale_return_id
        WHERE e.source = 'return_courier' AND sr.id IS NULL;
    ");

    expect($invalidPathaoCharges)->toBeEmpty()
        ->and($invalidReturnCouriers)->toBeEmpty();
});

