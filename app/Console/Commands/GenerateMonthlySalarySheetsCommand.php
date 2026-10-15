<?php

namespace App\Console\Commands;

use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateMonthlySalarySheetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate-monthly-sheets {--period= : Target period in YYYY-MM format (defaults to current month)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly salary sheets for all active salaried employees (PRD §10.1)';

    /**
     * Execute the console command.
     */
    public function handle(PayrollService $payrollService): int
    {
        $period = $this->option('period') ?: CarbonImmutable::now()->format('Y-m');

        $this->info("Generating salary sheets for period: {$period}...");

        $count = $payrollService->generateMonthlySheets($period);

        $this->info("Successfully generated {$count} salary sheet record(s).");

        return self::SUCCESS;
    }
}
