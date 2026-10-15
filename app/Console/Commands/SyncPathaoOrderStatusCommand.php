<?php

namespace App\Console\Commands;

use App\Services\Courier\PathaoService;
use Illuminate\Console\Command;

class SyncPathaoOrderStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'courier:sync-pathao';

    /**
     * The console command description.
     */
    protected $description = 'Poll and synchronize delivery statuses for active non-final Pathao courier orders';

    /**
     * Execute the console command.
     */
    public function handle(PathaoService $pathaoService): int
    {
        $this->info('Starting Pathao status synchronization...');

        $syncedCount = $pathaoService->pollNonFinalOrders();

        $this->info("Successfully synced {$syncedCount} active Pathao orders.");

        return self::SUCCESS;
    }
}
