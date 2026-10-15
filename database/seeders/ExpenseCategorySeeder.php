<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Facebook Ads', 'pl_group' => 'marketing_facebook', 'is_system' => true],
            ['name' => 'Other Marketing', 'pl_group' => 'marketing_other', 'is_system' => false],
            ['name' => 'Packaging', 'pl_group' => 'packaging', 'is_system' => false],
            ['name' => 'Courier Charges', 'pl_group' => 'courier', 'is_system' => true],
            ['name' => 'Rent', 'pl_group' => 'rent_utilities', 'is_system' => false],
            ['name' => 'Electricity', 'pl_group' => 'rent_utilities', 'is_system' => false],
            ['name' => 'Internet', 'pl_group' => 'rent_utilities', 'is_system' => false],
            ['name' => 'Gas/Water', 'pl_group' => 'rent_utilities', 'is_system' => false],
            ['name' => 'Transport', 'pl_group' => 'other', 'is_system' => false],
            ['name' => 'Repair & Maintenance', 'pl_group' => 'other', 'is_system' => false],
            ['name' => 'Tools & Equipment', 'pl_group' => 'other', 'is_system' => false],
            ['name' => 'Miscellaneous', 'pl_group' => 'other', 'is_system' => false],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['name' => $cat['name']],
                [
                    'pl_group' => $cat['pl_group'],
                    'is_system' => $cat['is_system'],
                    'is_active' => true,
                ]
            );
        }
    }
}
