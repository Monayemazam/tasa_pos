<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __construct(
        protected SalesReportService $salesReportService
    ) {
    }

    /**
     * Display sales report with summary cards, by-product breakdown, daily trend,
     * payment methods, courier breakdown, and top customers.
     */
    public function index(Request $request): Response
    {
        $this->authorize('reports.sales');

        $user = $request->user();
        $canViewCostProfit = $user && ($user->hasRole('Owner') || $user->can('reports.sales_profit'));

        $dateRange = $this->salesReportService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $from = $dateRange['from'];
        $to = $dateRange['to'];

        $summary = $this->salesReportService->getSummary($from, $to);
        $products = $this->salesReportService->getByProduct($from, $to, $canViewCostProfit);
        $daily = $this->salesReportService->getByDay($from, $to, $canViewCostProfit);
        $paymentMethods = $this->salesReportService->getByPaymentMethod($from, $to);
        $courierStatuses = $this->salesReportService->getByCourierStatus($from, $to);
        $topCustomers = $this->salesReportService->getTopCustomers($from, $to);

        return Inertia::render('reports/SalesReport', [
            'filters' => [
                'preset' => $dateRange['preset'],
                'from' => $from,
                'to' => $to,
                'label' => $dateRange['label'],
            ],
            'summary' => $summary,
            'products' => $products,
            'daily' => $daily,
            'paymentMethods' => $paymentMethods,
            'courierStatuses' => $courierStatuses,
            'topCustomers' => $topCustomers,
            'canViewCostProfit' => $canViewCostProfit,
        ]);
    }

    /**
     * Fetch drill-down details for a specific KPI, product, day, payment method, or customer.
     */
    public function drilldown(Request $request): JsonResponse
    {
        $this->authorize('reports.sales');

        $user = $request->user();
        $canViewCostProfit = $user && ($user->hasRole('Owner') || $user->can('reports.sales_profit'));

        $type = (string) $request->input('type', 'invoices');
        $from = (string) $request->input('from');
        $to = (string) $request->input('to');
        $params = $request->all();

        $data = $this->salesReportService->getDrilldown($type, $from, $to, $params, $canViewCostProfit);

        return response()->json($data);
    }

    /**
     * Export sales report as CSV.
     */
    public function exportCsv(Request $request): HttpResponse
    {
        $this->authorize('reports.sales');

        $user = $request->user();
        $canViewCostProfit = $user && ($user->hasRole('Owner') || $user->can('reports.sales_profit'));

        $dateRange = $this->salesReportService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $csv = $this->salesReportService->exportCsv($dateRange['from'], $dateRange['to'], $canViewCostProfit);
        $filename = "sales-report-{$dateRange['from']}-{$dateRange['to']}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export sales report as Excel spreadsheet (.xlsx).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorize('reports.sales');

        $user = $request->user();
        $canViewCostProfit = $user && ($user->hasRole('Owner') || $user->can('reports.sales_profit'));

        $dateRange = $this->salesReportService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $spreadsheet = $this->salesReportService->exportExcel($dateRange['from'], $dateRange['to'], $canViewCostProfit);
        $filename = "sales-report-{$dateRange['from']}-{$dateRange['to']}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export sales report as formatted PDF.
     */
    public function exportPdf(Request $request): HttpResponse
    {
        $this->authorize('reports.sales');

        $user = $request->user();
        $canViewCostProfit = $user && ($user->hasRole('Owner') || $user->can('reports.sales_profit'));

        $dateRange = $this->salesReportService->resolveDateRange(
            $request->input('date_preset', 'this_month'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $pdf = $this->salesReportService->exportPdf($dateRange['from'], $dateRange['to'], $canViewCostProfit);
        $filename = "sales-report-{$dateRange['from']}-{$dateRange['to']}.pdf";

        return $pdf->download($filename);
    }
}
