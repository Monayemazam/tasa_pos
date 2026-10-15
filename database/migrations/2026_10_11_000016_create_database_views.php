<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_leather_stock AS
            SELECT li.id, li.name, li.colour_grade, li.min_stock,
                   COALESCE(p.qty,0)  AS purchased,
                   COALESCE(c.qty,0)  AS consumed,
                   COALESCE(a.qty,0)  AS adjusted,
                   COALESCE(p.qty,0) - COALESCE(c.qty,0) + COALESCE(a.qty,0) AS current_stock,
                   CASE WHEN COALESCE(p.qty,0) = 0 THEN 0
                        ELSE COALESCE(p.cost,0) / p.qty END                  AS avg_cost,
                   (COALESCE(p.qty,0) - COALESCE(c.qty,0) + COALESCE(a.qty,0))
                     * CASE WHEN COALESCE(p.qty,0) = 0 THEN 0 ELSE p.cost / p.qty END AS stock_value
            FROM leather_items li
            LEFT JOIN (SELECT leather_item_id, SUM(qty_sqft) qty, SUM(total_cost) cost
                         FROM leather_purchases WHERE deleted_at IS NULL GROUP BY leather_item_id) p
                   ON p.leather_item_id = li.id
            LEFT JOIN (SELECT leather_item_id, SUM(qty_sqft) qty
                         FROM leather_consumptions WHERE deleted_at IS NULL GROUP BY leather_item_id) c
                   ON c.leather_item_id = li.id
            LEFT JOIN (SELECT item_id, SUM(qty) qty
                         FROM stock_adjustments WHERE item_type = 'leather' GROUP BY item_id) a
                   ON a.item_id = li.id
            WHERE li.deleted_at IS NULL;
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_accessory_stock AS
            SELECT ai.id, ai.name, ai.unit, ai.min_stock,
                   COALESCE(p.qty,0)  AS purchased,
                   COALESCE(c.qty,0)  AS consumed,
                   COALESCE(a.qty,0)  AS adjusted,
                   COALESCE(p.qty,0) - COALESCE(c.qty,0) + COALESCE(a.qty,0) AS current_stock,
                   CASE WHEN COALESCE(p.qty,0) = 0 THEN 0
                        ELSE COALESCE(p.cost,0) / p.qty END                  AS avg_cost,
                   (COALESCE(p.qty,0) - COALESCE(c.qty,0) + COALESCE(a.qty,0))
                     * CASE WHEN COALESCE(p.qty,0) = 0 THEN 0 ELSE p.cost / p.qty END AS stock_value
            FROM accessory_items ai
            LEFT JOIN (SELECT accessory_item_id, SUM(qty) qty, SUM(total_cost) cost
                         FROM accessory_purchases WHERE deleted_at IS NULL GROUP BY accessory_item_id) p
                   ON p.accessory_item_id = ai.id
            LEFT JOIN (SELECT accessory_item_id, SUM(qty) qty
                         FROM accessory_consumptions WHERE deleted_at IS NULL GROUP BY accessory_item_id) c
                   ON c.accessory_item_id = ai.id
            LEFT JOIN (SELECT item_id, SUM(qty) qty
                         FROM stock_adjustments WHERE item_type = 'accessory' GROUP BY item_id) a
                   ON a.item_id = ai.id
            WHERE ai.deleted_at IS NULL;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_accessory_stock;');
        DB::statement('DROP VIEW IF EXISTS v_leather_stock;');
    }
};
