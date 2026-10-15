<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProfitLossService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitLossController extends Controller
{
    public function __construct(
        protected ProfitLossService $profitLossService
    ) {
    }

    /**
     * Display Owner-only Profit & Loss Statement (PRD §11).
     */
    public function index(Request $request): Response
    {
        $this->authorize('reports.profit_loss');

        $request->validate([
            'date_preset' => ['nullable', 'string', 'in:today,yesterday,this_week,last_week,this_month,last_month,this_quarter,last_quarter,this_year,last_year,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'compare' => ['nullable', 'boolean'],
        ]);

        $preset = $request->input('date_preset', 'this_month');
        $from = $request->input('date_from');
        $to = $request->input('date_to');
        $compare = $request->boolean('compare');

        $dateRange = $this->profitLossService->resolveDateRange($preset, $from, $to);
        $fromDate = $dateRange['from'];
        $toDate = $dateRange['to'];

        if ($compare) {
            $prevPeriod = $this->profitLossService->resolvePreviousPeriod($dateRange['preset'], $fromDate, $toDate);
            $comparative = $this->profitLossService->getComparative($fromDate, $toDate, $prevPeriod['from'], $prevPeriod['to']);
            $reportData = $comparative['current'];
        } else {
            $prevPeriod = null;
            $comparative = null;
            $reportData = $this->profitLossService->calculate($fromDate, $toDate);
        }

        return Inertia::render('reports/ProfitLoss', [
            'filters' => [
                'preset' => $dateRange['preset'],
                'from' => $fromDate,
                'to' => $toDate,
                'label' => $dateRange['label'],
                'compare' => $compare,
                'prev_from' => $prevPeriod['from'] ?? null,
                'prev_to' => $prevPeriod['to'] ?? null,
                'prev_label' => $prevPeriod['label'] ?? null,
            ],
            'report' => $reportData,
            'comparative' => $comparative,
        ]);
    }

    /**
     * Fetch drill-down details for any clicked P&L line or memo figure.
     * Recomputes from source records; strictly protected by P&L authorization.
     */
    public function drilldown(Request $request): JsonResponse
    {
        $this->authorize('reports.profit_loss');

        $request->validate([
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $type = (string) $request->input('type', 'gross_sales');
        $from = (string) $request->input('from');
        $to = (string) $request->input('to');
        $params = $request->all();

        $data = $this->profitLossService->getDrilldown($type, $from, $to, $params);

        return response()->json($data);
    }

    /**
     * Export Owner Profit & Loss report as Excel spreadsheet (.xlsx).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorize('reports.profit_loss');

        $request->validate([
            'date_preset' => ['nullable', 'string', 'in:today,yesterday,this_week,last_week,this_month,last_month,this_quarter,last_quarter,this_year,last_year,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateRange = $this->profitLossService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $spreadsheet = $this->profitLossService->exportExcel($dateRange['from'], $dateRange['to']);
        $filename = "profit-loss-{$dateRange['from']}-{$dateRange['to']}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Owner Profit & Loss report as formatted PDF.
     */
    public function exportPdf(Request $request): HttpResponse
    {
        $this->authorize('reports.profit_loss');

        $request->validate([
            'date_preset' => ['nullable', 'string', 'in:today,yesterday,this_week,last_week,this_month,last_month,this_quarter,last_quarter,this_year,last_year,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateRange = $this->profitLossService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $pdf = $this->profitLossService->exportPdf($dateRange['from'], $dateRange['to']);
        $filename = "profit-loss-{$dateRange['from']}-{$dateRange['to']}.pdf";

        return $pdf->download($filename);
    }
}
