<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit & Loss Statement — {{ $from }} to {{ $to }}</title>
    <style>
        @page {
            margin: 20px 24px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #0F1B1A;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0B6E5E;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #0B6E5E;
            font-size: 18px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            font-size: 9px;
            color: #5F6F6D;
        }
        .badge-confidential {
            display: inline-block;
            background-color: #E6F2EF;
            color: #0B6E5E;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .kpi-cell {
            background-color: #F4F6F6;
            border: 1px solid #D5DCDB;
            padding: 8px 10px;
            width: 20%;
            vertical-align: top;
        }
        .kpi-title {
            font-size: 8px;
            text-transform: uppercase;
            color: #5F6F6D;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 13px;
            font-weight: bold;
            color: #0F1B1A;
        }
        .kpi-value.profit-positive {
            color: #1B7F3B;
        }
        .kpi-value.profit-negative {
            color: #B42318;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #0B6E5E;
            text-transform: uppercase;
            margin: 12px 0 6px 0;
            border-bottom: 1px solid #D5DCDB;
            padding-bottom: 3px;
        }
        table.pl-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.pl-table th {
            background-color: #0B6E5E;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 5px 6px;
            font-size: 9px;
            text-transform: uppercase;
        }
        table.pl-table td {
            border-bottom: 1px solid #EBEFEE;
            padding: 4px 6px;
            font-size: 9.5px;
        }
        table.pl-table tr.total-row td {
            font-weight: bold;
            background-color: #F0F7F5;
            border-top: 1px solid #0B6E5E;
            border-bottom: 1px solid #0B6E5E;
        }
        table.pl-table tr.net-profit-row td {
            font-weight: bold;
            font-size: 10.5px;
            background-color: #E6F2EF;
            border-top: 2px solid #0B6E5E;
            border-bottom: 2px solid #0B6E5E;
            color: #074538;
        }
        table.memo-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.memo-table th {
            background-color: #2B3A38;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 4px 6px;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        table.memo-table td {
            border-bottom: 1px solid #EBEFEE;
            padding: 4px 6px;
            font-size: 9px;
        }
        table.memo-table tr:nth-child(even) td {
            background-color: #FAFCFB;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .code-cell {
            font-weight: bold;
            color: #0B6E5E;
            width: 32px;
        }
        .footer {
            margin-top: 16px;
            border-top: 1px solid #D5DCDB;
            padding-top: 6px;
            font-size: 8px;
            color: #5F6F6D;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    @php
        $shopLogo = \App\Models\Setting::get('general.logo', '');
        $shopName = \App\Models\Setting::get('general.shop_name', 'TASA POS');
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
                    <span class="badge-confidential">Confidential — Owner Only</span>
                    <h1 style="margin: 2px 0 4px 0;">{{ $shopName }} — Statement of Profit & Loss</h1>
                    <div class="header-meta">
                        Period: <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong>
                        | Generated: {{ $generatedAt }}
                        @if(!empty($current['period']['salaries_whole_month_notice']))
                            | <em>Notice: Salaries are counted by whole month.</em>
                        @endif
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

    <!-- KPI Strip -->
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Net Profit / (Loss)</div>
                <div class="kpi-value {{ $current['net_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                    ৳{{ number_format($current['net_profit'], 2) }}
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Net Margin</div>
                <div class="kpi-value">{{ $current['net_margin_pct'] }}%</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Gross Margin</div>
                <div class="kpi-value">{{ $current['gross_margin_pct'] }}%</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Ad Spend % of Sales</div>
                <div class="kpi-value">{{ $current['marketing']['ad_spend_pct_sales'] }}%</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Facebook ROAS</div>
                <div class="kpi-value">{{ $current['marketing']['roas_formatted'] }}</div>
            </td>
        </tr>
    </table>

    <!-- Main P&L Statement (PRD §11.1) -->
    <div class="section-title">1. Financial Performance Statement (PRD §11.1)</div>
    <table class="pl-table">
        <thead>
            <tr>
                <th style="width: 32px;">Line</th>
                <th>Statement Item</th>
                <th class="text-right" style="width: 120px;">Amount (BDT)</th>
                <th style="width: 180px;">Formula / Definition</th>
            </tr>
        </thead>
        <tbody>
            <!-- REVENUE -->
            <tr>
                <td class="code-cell">A</td>
                <td>Gross sales (invoice subtotals)</td>
                <td class="text-right">৳{{ number_format($current['revenue']['gross_sales'], 2) }}</td>
                <td>Non-cancelled sales in period</td>
            </tr>
            <tr>
                <td class="code-cell">B</td>
                <td>− Discounts</td>
                <td class="text-right">৳{{ number_format($current['revenue']['discounts'], 2) }}</td>
                <td>Promotional and invoice discounts</td>
            </tr>
            <tr>
                <td class="code-cell">C</td>
                <td>− Sales returns / cancellations</td>
                <td class="text-right">৳{{ number_format($current['revenue']['returns'], 2) }}</td>
                <td>Returns by return-date period</td>
            </tr>
            <tr class="total-row">
                <td class="code-cell">D</td>
                <td>NET SALES</td>
                <td class="text-right">৳{{ number_format($current['revenue']['net_sales'], 2) }}</td>
                <td>A − B − C</td>
            </tr>
            <tr>
                <td class="code-cell">E</td>
                <td>+ Delivery charges collected from customers</td>
                <td class="text-right">৳{{ number_format($current['revenue']['delivery_income'], 2) }}</td>
                <td>Customer delivery fees billed</td>
            </tr>
            <tr class="total-row">
                <td class="code-cell">F</td>
                <td>TOTAL REVENUE</td>
                <td class="text-right">৳{{ number_format($current['revenue']['total_revenue'], 2) }}</td>
                <td>D + E</td>
            </tr>

            <!-- COGS -->
            <tr>
                <td class="code-cell">G</td>
                <td>Cost of items sold (COGS)</td>
                <td class="text-right">৳{{ number_format($current['cogs'], 2) }}</td>
                <td>Qty × snapshot unit cost − restocked return cost</td>
            </tr>
            <tr class="total-row">
                <td class="code-cell">H</td>
                <td>GROSS PROFIT</td>
                <td class="text-right">৳{{ number_format($current['gross_profit'], 2) }}</td>
                <td>F − G (Margin: {{ $current['gross_margin_pct'] }}%)</td>
            </tr>

            <!-- OPERATING EXPENSES -->
            <tr>
                <td class="code-cell">I1</td>
                <td>Marketing: Facebook Ads</td>
                <td class="text-right">৳{{ number_format($current['expenses']['facebook_ads'], 2) }}</td>
                <td>ROAS: {{ $current['marketing']['roas_formatted'] }}</td>
            </tr>
            <tr>
                <td class="code-cell">I2</td>
                <td>Marketing: Other marketing</td>
                <td class="text-right">৳{{ number_format($current['expenses']['other_marketing'], 2) }}</td>
                <td>Offline & promotional marketing</td>
            </tr>
            <tr>
                <td class="code-cell">J</td>
                <td>Courier charges</td>
                <td class="text-right">৳{{ number_format($current['expenses']['courier'], 2) }}</td>
                <td>Pathao delivery & return costs</td>
            </tr>
            <tr>
                <td class="code-cell">K</td>
                <td>Salaries (earned for period)</td>
                <td class="text-right">৳{{ number_format($current['expenses']['salaries'], 2) }}</td>
                <td>Accrued monthly payroll payable</td>
            </tr>
            <tr>
                <td class="code-cell">L</td>
                <td>Wages (earned for period)</td>
                <td class="text-right">৳{{ number_format($current['expenses']['wages'], 2) }}</td>
                <td>
                    @if($current['cost_includes_labour'])
                        <em>Excluded (Cost includes labour = Yes)</em>
                    @else
                        Accrued workshop wage earnings
                    @endif
                </td>
            </tr>
            <tr>
                <td class="code-cell">M</td>
                <td>Packaging</td>
                <td class="text-right">৳{{ number_format($current['expenses']['packaging'], 2) }}</td>
                <td>Boxes, polybags, tape, tags</td>
            </tr>
            <tr>
                <td class="code-cell">N</td>
                <td>Rent & utilities</td>
                <td class="text-right">৳{{ number_format($current['expenses']['rent_utilities'], 2) }}</td>
                <td>Rent, electricity, internet, gas/water</td>
            </tr>
            <tr>
                <td class="code-cell">O</td>
                <td>Other expenses</td>
                <td class="text-right">৳{{ number_format($current['expenses']['other'], 2) }}</td>
                <td>Tools, transport, repairs, misc</td>
            </tr>
            <tr class="total-row">
                <td class="code-cell">P</td>
                <td>TOTAL OPERATING EXPENSES</td>
                <td class="text-right">৳{{ number_format($current['expenses']['total'], 2) }}</td>
                <td>I1 + I2 + J + K + L + M + N + O</td>
            </tr>

            <!-- NET PROFIT -->
            <tr class="net-profit-row">
                <td class="code-cell">Q</td>
                <td>NET PROFIT / (LOSS)</td>
                <td class="text-right">৳{{ number_format($current['net_profit'], 2) }}</td>
                <td>H − P (Net Margin: {{ $current['net_margin_pct'] }}%)</td>
            </tr>
        </tbody>
    </table>

    <!-- Memo Section (PRD §11.2) -->
    <div class="section-title">2. Memo Section (Informational — Not in Profit Calculation)</div>
    <table class="memo-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Metric / Description</th>
                <th class="text-right">Quantity / Vol</th>
                <th class="text-right">Amount (BDT)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Leather (Raw Material)</td>
                <td>Leather purchased in period</td>
                <td class="text-right">{{ number_format($current['memo']['leather']['purchased_sqft'], 2) }} sq ft</td>
                <td class="text-right">৳{{ number_format($current['memo']['leather']['purchased_cost'], 2) }}</td>
            </tr>
            <tr>
                <td>Leather (Raw Material)</td>
                <td>Leather consumed in period</td>
                <td class="text-right">{{ number_format($current['memo']['leather']['consumed_sqft'], 2) }} sq ft</td>
                <td class="text-right">৳{{ number_format($current['memo']['leather']['consumed_value'], 2) }}</td>
            </tr>
            <tr>
                <td>Accessories</td>
                <td>Accessories purchased in period</td>
                <td class="text-right">{{ number_format($current['memo']['accessories']['purchased_qty'], 0) }} units</td>
                <td class="text-right">৳{{ number_format($current['memo']['accessories']['purchased_cost'], 2) }}</td>
            </tr>
            <tr>
                <td>Accessories</td>
                <td>Accessories consumed in period</td>
                <td class="text-right">{{ number_format($current['memo']['accessories']['consumed_qty'], 0) }} units</td>
                <td class="text-right">৳{{ number_format($current['memo']['accessories']['consumed_value'], 2) }}</td>
            </tr>
            <tr>
                <td>Inventory Assets</td>
                <td>Closing stock: Finished Products</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['closing_stock']['finished_products'], 2) }}</td>
            </tr>
            <tr>
                <td>Inventory Assets</td>
                <td>Closing stock: Raw Leather</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['closing_stock']['leather'], 2) }}</td>
            </tr>
            <tr>
                <td>Inventory Assets</td>
                <td>Closing stock: Accessories</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['closing_stock']['accessories'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #E6F2EF;">
                <td>Inventory Assets</td>
                <td>Total Inventory Closing Value</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['closing_stock']['total'], 2) }}</td>
            </tr>
            <tr>
                <td>Receivables</td>
                <td>COD receivable from Pathao (delivered, not yet settled)</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['cod_receivable'], 2) }}</td>
            </tr>
            <tr>
                <td>Receivables</td>
                <td>Customer dues (unpaid invoice balances)</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['customer_dues'], 2) }}</td>
            </tr>
            <tr>
                <td>Payroll Balances</td>
                <td>Payroll payable (owed to employees & workers)</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['payroll_balances']['owed'], 2) }}</td>
            </tr>
            <tr>
                <td>Payroll Balances</td>
                <td>Payroll advances / overpaid (owed to Tasa)</td>
                <td class="text-right">—</td>
                <td class="text-right">৳{{ number_format($current['memo']['payroll_balances']['advances'], 2) }}</td>
            </tr>
            <tr>
                <td>Cash Movement</td>
                <td>Cash received: Customer payments (৳{{ number_format($current['memo']['cash_summary']['cash_in']['customer_payments'], 2) }}) + COD settlements (৳{{ number_format($current['memo']['cash_summary']['cash_in']['cod_settlements'], 2) }})</td>
                <td class="text-right">Inflow</td>
                <td class="text-right" style="color: #1B7F3B; font-weight: bold;">৳{{ number_format($current['memo']['cash_summary']['cash_in']['total'], 2) }}</td>
            </tr>
            <tr>
                <td>Cash Movement</td>
                <td>Cash paid out: Expenses + Payroll + Material purchases + Customer refunds</td>
                <td class="text-right">Outflow</td>
                <td class="text-right" style="color: #B42318; font-weight: bold;">৳{{ number_format($current['memo']['cash_summary']['cash_out']['total'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #F0F7F5;">
                <td>Cash Movement</td>
                <td>Net Cash Movement (Inflow − Outflow)</td>
                <td class="text-right">—</td>
                <td class="text-right" style="color: {{ $current['memo']['cash_summary']['net_movement'] >= 0 ? '#1B7F3B' : '#B42318' }};">
                    ৳{{ number_format($current['memo']['cash_summary']['net_movement'], 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div>Tasa POS &bull; Precision Accounting &bull; Strict Source-Row Computation</div>
        <div>Page 1 of 1</div>
    </div>

</body>
</html>
