<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report — {{ $from }} to {{ $to }}</title>
    <style>
        @page {
            margin: 24px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #171717;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0B6E5E;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header h1 {
            color: #0B6E5E;
            font-size: 20px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            font-size: 10px;
            color: #525252;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .kpi-cell {
            background-color: #F8F9FA;
            border: 1px solid #E5E7EB;
            padding: 10px;
            width: 25%;
            vertical-align: top;
        }
        .kpi-title {
            font-size: 9px;
            text-transform: uppercase;
            color: #737373;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #171717;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0B6E5E;
            text-transform: uppercase;
            margin: 16px 0 8px 0;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 4px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th {
            background-color: #0B6E5E;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data-table td {
            border-bottom: 1px solid #E5E7EB;
            padding: 6px 8px;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #FAFAFA;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #E5E7EB;
            padding-top: 8px;
            font-size: 9px;
            color: #737373;
            text-align: center;
        }
        .badge-violet {
            color: #6B3FA0;
            font-weight: bold;
        }
    </style>
</head>
<body>

    @php
        $shopLogo = \App\Models\Setting::get('general.logo', '');
        $shopName = \App\Models\Setting::get('general.shop_name', 'TASA');
        $logoPath = '';
        if ($shopLogo) {
            $cleaned = ltrim($shopLogo, '/');
            $full = public_path($cleaned);
            if (file_exists($full)) {
                $logoPath = $full;
            }
        }
    @endphp
    <div class="header">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
            <tr>
                <td style="vertical-align: top; border: none; padding: 0;">
                    <h1 style="margin: 0 0 4px 0;">{{ $shopName }} — Sales Report</h1>
                    <div class="header-meta">
                        <strong>Period:</strong> {{ $from }} to {{ $to }} &nbsp;|&nbsp;
                        <strong>Generated:</strong> {{ $generatedAt }} &nbsp;|&nbsp;
                        <strong>Currency:</strong> BDT (৳)
                    </div>
                </td>
                @if($logoPath)
                <td style="width: 140px; text-align: right; vertical-align: top; border: none; padding: 0;">
                    <img src="{{ $logoPath }}" alt="Logo" style="max-height: 40px; max-width: 130px;">
                </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- Summary Metrics -->
    <div class="section-title">Executive Summary</div>
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Invoices Count</div>
                <div class="kpi-value">{{ number_format($summary['invoice_count']) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Gross Sales</div>
                <div class="kpi-value">৳ {{ number_format($summary['gross_sales'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Discounts Given</div>
                <div class="kpi-value">৳ {{ number_format($summary['discounts'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Sales Returns</div>
                <div class="kpi-value">৳ {{ number_format($summary['returns'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="kpi-cell" style="background-color: #F0FDF4;">
                <div class="kpi-title" style="color: #166534;">Net Sales Revenue</div>
                <div class="kpi-value" style="color: #166534;">৳ {{ number_format($summary['net_sales'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Delivery Collected</div>
                <div class="kpi-value">৳ {{ number_format($summary['delivery_charge'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Amount Received</div>
                <div class="kpi-value">৳ {{ number_format($summary['amount_received'], 2) }}</div>
            </td>
            <td class="kpi-cell" style="background-color: #FEF2F2;">
                <div class="kpi-title" style="color: #991B1B;">Amount Due</div>
                <div class="kpi-value" style="color: #991B1B;">৳ {{ number_format($summary['amount_due'], 2) }}</div>
            </td>
        </tr>
    </table>

    <!-- By Product Breakdown -->
    <div class="section-title">Sales by Product</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35%;">Product</th>
                <th style="width: 15%;">SKU</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 15%;">Revenue</th>
                @if($includeCostProfit)
                    <th class="text-right" style="width: 12%;">COGS</th>
                    <th class="text-right" style="width: 13%;">Gross Profit</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
                <tr>
                    <td><strong>{{ $p['name'] }}</strong></td>
                    <td><code>{{ $p['sku'] }}</code></td>
                    <td class="text-center">{{ number_format($p['qty_sold']) }}</td>
                    <td class="text-right">৳ {{ number_format($p['revenue'], 2) }}</td>
                    @if($includeCostProfit)
                        <td class="text-right">৳ {{ number_format($p['cogs'] ?? 0, 2) }}</td>
                        <td class="text-right">৳ {{ number_format($p['gross_profit'] ?? 0, 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $includeCostProfit ? 6 : 4 }}" class="text-center" style="padding: 15px; color: #737373;">
                        No sales recorded for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Daily Trend Summary -->
    @if(!empty($daily))
        <div class="section-title">Daily Trend Summary</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Date</th>
                    <th class="text-center" style="width: 15%;">Invoices</th>
                    <th class="text-right" style="width: 20%;">Gross Sales</th>
                    <th class="text-right" style="width: 15%;">Returns</th>
                    <th class="text-right" style="width: 20%;">Net Sales</th>
                    @if($includeCostProfit)
                        <th class="text-right" style="width: 10%;">Profit</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($daily as $d)
                    <tr>
                        <td>{{ $d['date'] }}</td>
                        <td class="text-center">{{ $d['invoice_count'] }}</td>
                        <td class="text-right">৳ {{ number_format($d['gross_sales'], 2) }}</td>
                        <td class="text-right">৳ {{ number_format($d['returns'], 2) }}</td>
                        <td class="text-right"><strong>৳ {{ number_format($d['net_sales'], 2) }}</strong></td>
                        @if($includeCostProfit)
                            <td class="text-right">৳ {{ number_format($d['gross_profit'] ?? 0, 2) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Confidential — Internal Tasa POS Record. All values computed strictly from primary transaction ledgers.
    </div>

</body>
</html>
