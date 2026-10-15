<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStockLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:verify-stock {--fix : Automatically repair product stock quantities to match movement ledger totals}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that cached product stock quantities match the exact sum of product stock movements (DB Integrity Query §9.1)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verifying finished goods stock ledger integrity...');

        $discrepancies = DB::table('products as p')
            ->leftJoinSub(
                DB::table('product_stock_movements')
                    ->select('product_id', DB::raw('SUM(qty) as ledger_qty'))
                    ->groupBy('product_id'),
                'm',
                'm.product_id',
                '=',
                'p.id'
            )
            ->select('p.id', 'p.name', 'p.sku', 'p.stock_qty', DB::raw('COALESCE(m.ledger_qty, 0) as ledger_qty'))
            ->whereRaw('p.stock_qty <> COALESCE(m.ledger_qty, 0)')
            ->get();

        if ($discrepancies->isEmpty()) {
            $this->info(' Integrity verified: 100% of products match movement ledger sum. 0 discrepancies found.');

            return self::SUCCESS;
        }

        $this->error(sprintf('Found %d discrepancies between products.stock_qty and product_stock_movements ledger!', $discrepancies->count()));

        $rows = $discrepancies->map(fn ($d) => [
            'ID' => $d->id,
            'Name' => $d->name,
            'SKU' => $d->sku,
            'Cached Stock' => $d->stock_qty,
            'Movement Sum' => $d->ledger_qty,
            'Difference' => $d->stock_qty - $d->ledger_qty,
        ])->toArray();

        $this->table(['ID', 'Name', 'SKU', 'Cached Stock', 'Movement Sum', 'Difference'], $rows);

        if ($this->option('fix')) {
            $this->warn('Fixing stock quantities...');
            DB::transaction(function () use ($discrepancies) {
                foreach ($discrepancies as $d) {
                    Product::where('id', $d->id)->update([
                        'stock_qty' => (int) $d->ledger_qty,
                    ]);
                }
            });
            $this->info('All discrepancies repaired successfully.');

            return self::SUCCESS;
        }

        $this->line('Run with --fix to automatically synchronize cached stock_qty with movement sums.');

        return self::FAILURE;
    }
}
