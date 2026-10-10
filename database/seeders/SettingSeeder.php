<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'general.shop_name' => 'Tasa Leather Goods',
            'general.address' => '',
            'general.phone' => '',
            'general.logo' => '',
            'general.currency_symbol' => '৳',
            'invoice.prefix' => 'TASA',
            'invoice.paper_size' => 'A4',
            'invoice.footer_note' => '',
            'invoice.terms' => '',
            'invoice.custom_template' => null,
            'inventory.allow_negative_stock' => '0',
            'payroll.cost_price_includes_labour' => '0',
            'pathao.store_id' => '',
            'pathao.sender_name' => '',
            'pathao.sender_phone' => '',
            'pathao.default_delivery_type' => '48',
            'pathao.default_item_type' => '2',
            'pathao.default_weight' => '0.5',
            'pathao.show_success_rate' => '1',
        ];

        foreach ($defaults as $compositeKey => $defaultValue) {
            [$group, $key] = explode('.', $compositeKey, 2);
            Setting::firstOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $defaultValue]
            );
        }
    }
}
