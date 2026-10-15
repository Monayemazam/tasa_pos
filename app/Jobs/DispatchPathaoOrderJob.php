<?php

namespace App\Jobs;

use App\Exceptions\CourierPermanentValidationException;
use App\Exceptions\CourierTransientException;
use App\Models\CourierOrder;
use App\Services\Courier\PathaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchPathaoOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $courierOrderId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(PathaoService $pathaoService): void
    {
        $courierOrder = CourierOrder::find($this->courierOrderId);
        if (! $courierOrder) {
            return;
        }

        // Avoid re-dispatching already created or delivered orders
        if (in_array($courierOrder->status, ['created', 'picked_up', 'in_transit', 'delivered', 'returned', 'cancelled'], true) && $courierOrder->consignment_id) {
            return;
        }

        $courierOrder->update(['status' => 'processing']);

        try {
            $pathaoService->createOrder($courierOrder, throwOnFailure: true);
        } catch (CourierPermanentValidationException $e) {
            // Permanent failure: validation error, invalid address/zone, forbidden.
            // Do NOT retry automatically. Mark failed and preserve diagnostic details.
            $courierOrder->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            Log::info("Permanent validation failure on CourierOrder #{$courierOrder->id}; halting auto-retries: " . $e->getMessage());
        } catch (\Throwable $e) {
            // Transient failure: connection timeout, 5xx gateway error, rate limit
            $retryAfterSecs = ($e instanceof CourierTransientException)
                ? $e->getRetryAfterSeconds()
                : ($this->backoff * $this->attempts());

            if ($this->attempts() >= $this->tries) {
                $errMsg = "Exceeded maximum retry attempts ({$this->tries}). Last error: " . $e->getMessage();
                $courierOrder->update([
                    'status' => 'failed',
                    'last_error' => $errMsg,
                ]);
                throw $e; // Re-throw to record in failed_jobs table!
            }

            $courierOrder->update([
                'status' => 'awaiting_retry',
                'last_error' => "Attempt {$this->attempts()}/{$this->tries} transient failure (retrying in {$retryAfterSecs}s): " . $e->getMessage(),
            ]);

            $this->release($retryAfterSecs);
        }
    }
}
