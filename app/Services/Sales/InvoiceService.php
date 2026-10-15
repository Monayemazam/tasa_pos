<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Documented safe placeholders allowed in custom templates.
     */
    public const ALLOWED_PLACEHOLDERS = [
        '{{shop_logo}}',
        '{{shop_name}}',
        '{{shop_address}}',
        '{{shop_phone}}',
        '{{invoice_no}}',
        '{{sale_date}}',
        '{{customer_name}}',
        '{{customer_phone}}',
        '{{customer_address}}',
        '{{items_table}}',
        '{{subtotal}}',
        '{{discount}}',
        '{{delivery_charge}}',
        '{{grand_total}}',
        '{{paid_total}}',
        '{{due_amount}}',
        '{{payment_methods}}',
        '{{consignment_id}}',
        '{{footer_note}}',
        '{{terms}}',
    ];

    /**
     * Get complete, permission-safe invoice data snapshot.
     * Sensitive internal product cost and profit data are strictly omitted.
     *
     * @return array<string, mixed>
     */
    public function getInvoiceData(Sale $sale, ?User $user = null): array
    {
        $sale->loadMissing(['items', 'payments', 'customer', 'courierOrder', 'creator']);

        $shopName = (string) Setting::get('general.shop_name', 'Tasa Leather Goods');
        $shopAddress = (string) Setting::get('general.address', 'Dhaka, Bangladesh');
        $shopPhone = (string) Setting::get('general.phone', '+880 1700-000000');
        $shopLogo = (string) Setting::get('general.logo', '');
        $currency = (string) Setting::get('general.currency_symbol', '৳');

        $paperSize = (string) Setting::get('invoice.paper_size', 'A4');
        $footerNote = (string) Setting::get('invoice.footer_note', 'Thank you for shopping with Tasa Leather Goods!');
        $terms = (string) Setting::get('invoice.terms', 'Exchange within 7 days with original invoice. No cash refunds.');
        $selectableFields = Setting::get('invoice.selectable_fields', [
            'show_logo' => true,
            'show_shop_address' => true,
            'show_shop_phone' => true,
            'show_discount' => true,
            'show_delivery_charge' => true,
            'show_consignment_id' => true,
            'show_payment_methods' => true,
            'show_footer_note' => true,
            'show_terms' => true,
        ]);

        // Format items: preserve exact sale-time unit price snapshot; NO cost price
        $items = $sale->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->name,
                'variant_name' => $item->variant_name,
                'variant_sku' => $item->variant_sku,
                'is_custom' => (bool) $item->is_custom,
                'qty' => (int) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ];
        })->values()->all();

        // Payments summary
        $paymentMethods = $sale->payments->map(function ($p) {
            $name = match ($p->method) {
                'cash' => 'Cash',
                'cod' => 'Cash on Delivery',
                'bkash' => 'bKash',
                'bank_transfer' => 'Bank Transfer',
                default => ucfirst((string) $p->method),
            };

            return $p->reference ? "{$name} ({$p->reference})" : $name;
        })->unique()->implode(', ');

        if (empty($paymentMethods)) {
            $paymentMethods = 'Unpaid';
        }

        $saleDate = $sale->sale_date ? Carbon::parse($sale->sale_date)->format('d M Y') : now()->format('d M Y');

        return [
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'sale_date' => $saleDate,
            'sold_at' => $sale->sold_at?->format('d M Y, h:i A'),
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'currency' => $currency,
            'shop' => [
                'name' => $shopName,
                'address' => $shopAddress,
                'phone' => $shopPhone,
                'logo' => $shopLogo,
            ],
            'customer' => [
                'name' => $sale->customer_name,
                'phone' => $sale->customer_phone,
                'address' => $sale->customer_address,
                'notes' => $sale->notes,
            ],
            'courier' => [
                'mode' => $sale->courier_mode,
                'consignment_id' => $sale->courierOrder?->consignment_id,
                'status' => $sale->courierOrder?->status,
                'city' => $sale->city_name,
                'zone' => $sale->zone_name,
                'area' => $sale->area_name,
            ],
            'items' => $items,
            'financials' => [
                'subtotal' => (float) $sale->subtotal,
                'discount_type' => $sale->discount_type,
                'discount_value' => (float) $sale->discount_value,
                'discount_amount' => (float) $sale->discount_amount,
                'delivery_charge' => (float) $sale->delivery_charge,
                'grand_total' => (float) $sale->grand_total,
                'paid_total' => (float) $sale->paid_total,
                'due_amount' => (float) $sale->due_amount,
                'payment_methods' => $paymentMethods,
            ],
            'branding' => [
                'paper_size' => $paperSize,
                'footer_note' => $footerNote,
                'terms' => $terms,
                'selectable_fields' => $selectableFields,
            ],
        ];
    }

    /**
     * Render HTML document for the invoice according to paper size (A4, A5, 80mm).
     */
    public function renderHtml(Sale $sale, ?string $paperSize = null): string
    {
        $data = $this->getInvoiceData($sale);
        $paperSize = strtoupper($paperSize ?: $data['branding']['paper_size']);
        if (! in_array($paperSize, ['A4', 'A5', '80MM'], true)) {
            $paperSize = 'A4';
        }

        $customTemplate = Setting::get('invoice.custom_template');
        if (! empty($customTemplate)) {
            return $this->renderCustomTemplate($customTemplate, $data, $paperSize);
        }

        return $this->renderDefaultTemplate($data, $paperSize);
    }

    /**
     * Generate real server-side PDF document instance using Barryvdh\DomPDF.
     */
    public function generatePdf(Sale $sale, ?string $paperSize = null): DomPdfWrapper
    {
        $html = $this->renderHtml($sale, $paperSize);
        $paperSize = strtoupper($paperSize ?: Setting::get('invoice.paper_size', 'A4'));

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadHTML($html);

        if ($paperSize === '80MM') {
            // 80mm roll: 80mm in points is (80 / 25.4) * 72 ≈ 226.77 pt
            // Estimate height based on items count
            $itemCount = $sale->items()->count();
            $calculatedHeightPt = max(400, 260 + ($itemCount * 30));
            $pdf->setPaper([0, 0, 226.77, $calculatedHeightPt], 'portrait');
        } elseif ($paperSize === 'A5') {
            $pdf->setPaper('a5', 'portrait');
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf;
    }

    /**
     * Render the default built-in responsive template for A4, A5, or 80mm.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderDefaultTemplate(array $data, string $paperSize): string
    {
        $isThermal = ($paperSize === '80MM');
        $isA5 = ($paperSize === 'A5');

        $fontFamily = "'DejaVu Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
        $currency = htmlspecialchars($data['currency'], ENT_QUOTES, 'UTF-8');

        $subtotal = number_format($data['financials']['subtotal'], 2);
        $discount = number_format($data['financials']['discount_amount'], 2);
        $delivery = number_format($data['financials']['delivery_charge'], 2);
        $grandTotal = number_format($data['financials']['grand_total'], 2);
        $paidTotal = number_format($data['financials']['paid_total'], 2);
        $dueAmount = number_format($data['financials']['due_amount'], 2);

        $customerName = htmlspecialchars($data['customer']['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($data['customer']['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerAddress = nl2br(htmlspecialchars($data['customer']['address'] ?? '', ENT_QUOTES, 'UTF-8'));

        $shopName = htmlspecialchars($data['shop']['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopAddress = htmlspecialchars($data['shop']['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopPhone = htmlspecialchars($data['shop']['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopLogo = htmlspecialchars($data['shop']['logo'] ?? '', ENT_QUOTES, 'UTF-8');

        $invoiceNo = htmlspecialchars($data['invoice_no'], ENT_QUOTES, 'UTF-8');
        $saleDate = htmlspecialchars($data['sale_date'], ENT_QUOTES, 'UTF-8');
        $paymentMethods = htmlspecialchars($data['financials']['payment_methods'], ENT_QUOTES, 'UTF-8');
        $consignmentId = htmlspecialchars($data['courier']['consignment_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $footerNote = htmlspecialchars($data['branding']['footer_note'] ?? '', ENT_QUOTES, 'UTF-8');
        $terms = htmlspecialchars($data['branding']['terms'] ?? '', ENT_QUOTES, 'UTF-8');

        // Item rows
        $itemsHtml = '';
        foreach ($data['items'] as $index => $item) {
            $num = $index + 1;
            $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
            $variantSku = htmlspecialchars($item['variant_sku'] ?? '', ENT_QUOTES, 'UTF-8');
            $qty = $item['qty'];
            $price = number_format($item['unit_price'], 2);
            $total = number_format($item['line_total'], 2);

            if ($isThermal) {
                $skuLine = $variantSku ? "<div style='font-size: 9px; color: #444; font-weight: normal;'>SKU: {$variantSku}</div>" : '';
                $itemsHtml .= "
                    <tr>
                        <td colspan='3' style='padding-top: 4px; font-weight: bold;'>{$name}{$skuLine}</td>
                    </tr>
                    <tr style='border-bottom: 1px dashed #bbb;'>
                        <td style='padding-bottom: 4px;'>{$qty} x {$currency}{$price}</td>
                        <td style='text-align: right; padding-bottom: 4px;' colspan='2'>{$currency}{$total}</td>
                    </tr>
                ";
            } else {
                $skuLine = $variantSku ? "<div style='font-size: 10px; color: #52605C; font-family: monospace; margin-top: 2px;'>SKU: {$variantSku}</div>" : '';
                $itemsHtml .= "
                    <tr style='border-bottom: 1px solid #EBEFEE;'>
                        <td style='padding: 8px 10px; width: 30px; color: #666;'>{$num}</td>
                        <td style='padding: 8px 10px; font-weight: 500;'>
                            <div>{$name}</div>
                            {$skuLine}
                        </td>
                        <td style='padding: 8px 10px; text-align: center; width: 50px;'>{$qty}</td>
                        <td style='padding: 8px 10px; text-align: right; width: 90px; font-family: monospace;'>{$currency}{$price}</td>
                        <td style='padding: 8px 10px; text-align: right; width: 100px; font-weight: bold; font-family: monospace;'>{$currency}{$total}</td>
                    </tr>
                ";
            }
        }

        // Layout styles
        if ($isThermal) {
            return "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='utf-8'>
                <title>Invoice {$invoiceNo}</title>
                <style>
                    @page { margin: 2mm 3mm; size: 80mm auto; }
                    body {
                        font-family: {$fontFamily};
                        font-size: 11px;
                        line-height: 1.3;
                        color: #000;
                        margin: 0;
                        padding: 2mm;
                        background: #fff;
                    }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .bold { font-weight: bold; }
                    .divider { border-top: 1px dashed #000; margin: 6px 0; }
                    .table { width: 100%; border-collapse: collapse; font-size: 11px; }
                    .totals td { padding: 2px 0; }
                    @media print {
                        .no-print { display: none !important; }
                        body { padding: 0 !important; }
                    }
                </style>
            </head>
            <body>
                <div class='text-center'>
                    <div style='font-size: 15px; font-weight: 900; letter-spacing: 0.5px;'>{$shopName}</div>
                    ".($shopAddress ? "<div style='font-size: 10px;'>{$shopAddress}</div>" : '')."
                    ".($shopPhone ? "<div style='font-size: 10px;'>Phone: {$shopPhone}</div>" : '')."
                </div>

                <div class='divider'></div>

                <div>
                    <div><strong>Invoice:</strong> {$invoiceNo}</div>
                    <div><strong>Date:</strong> {$saleDate}</div>
                    <div><strong>Customer:</strong> {$customerName}</div>
                    <div><strong>Phone:</strong> {$customerPhone}</div>
                    <div><strong>Address:</strong> {$customerAddress}</div>
                    ".($consignmentId ? "<div><strong>Consignment:</strong> {$consignmentId}</div>" : '')."
                </div>

                <div class='divider'></div>

                <table class='table'>
                    <thead>
                        <tr style='border-bottom: 1px dashed #000;'>
                            <th style='text-align: left; padding-bottom: 3px;'>Item</th>
                            <th style='text-align: right; padding-bottom: 3px;' colspan='2'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>

                <div class='divider'></div>

                <table class='table totals'>
                    <tr>
                        <td>Subtotal:</td>
                        <td class='text-right'>{$currency}{$subtotal}</td>
                    </tr>
                    ".($data['financials']['discount_amount'] > 0 ? "
                    <tr>
                        <td>Discount:</td>
                        <td class='text-right'>-{$currency}{$discount}</td>
                    </tr>" : '')."
                    ".($data['financials']['delivery_charge'] > 0 ? "
                    <tr>
                        <td>Delivery:</td>
                        <td class='text-right'>{$currency}{$delivery}</td>
                    </tr>" : '')."
                    <tr style='font-size: 13px; font-weight: bold; border-top: 1px dashed #000; border-bottom: 1px dashed #000;'>
                        <td style='padding: 4px 0;'>Grand Total:</td>
                        <td class='text-right' style='padding: 4px 0;'>{$currency}{$grandTotal}</td>
                    </tr>
                    <tr>
                        <td style='padding-top: 3px;'>Paid ({$paymentMethods}):</td>
                        <td class='text-right' style='padding-top: 3px;'>{$currency}{$paidTotal}</td>
                    </tr>
                    <tr style='font-weight: bold;'>
                        <td>Balance Due:</td>
                        <td class='text-right'>{$currency}{$dueAmount}</td>
                    </tr>
                </table>

                ".($footerNote || $terms ? "
                <div class='divider'></div>
                <div class='text-center' style='font-size: 10px; color: #444;'>
                    ".($footerNote ? "<div>{$footerNote}</div>" : '')."
                    ".($terms ? "<div style='font-style: italic; margin-top: 2px;'>{$terms}</div>" : '')."
                </div>" : '')."
            </body>
            </html>
            ";
        }

        // A4 and A5 layout
        $bodyPadding = $isA5 ? '12px' : '28px';
        $titleSize = $isA5 ? '20px' : '26px';
        $fontSize = $isA5 ? '11px' : '12px';

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='utf-8'>
            <title>Invoice {$invoiceNo}</title>
            <style>
                @page {
                    margin: 8mm 10mm;
                    size: ".($isA5 ? 'a5 portrait' : 'a4 portrait').";
                }
                body {
                    font-family: {$fontFamily};
                    font-size: {$fontSize};
                    line-height: 1.45;
                    color: #1A2725;
                    margin: 0;
                    padding: {$bodyPadding};
                    background: #fff;
                    box-sizing: border-box;
                }
                .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .billing-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table th { background: #E6F2EF; color: #0B6E5E; font-weight: bold; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; padding: 8px 10px; }
                .summary-container { width: 100%; margin-top: 10px; page-break-inside: avoid; }
                .summary-table { width: 280px; margin-left: auto; border-collapse: collapse; }
                .summary-table td { padding: 4px 6px; }
                .grand-total-row { background: #E6F2EF; font-weight: bold; font-size: 14px; color: #0B6E5E; }
                .footer-box { margin-top: 25px; padding-top: 12px; border-top: 1px solid #D5DCDB; font-size: 10px; color: #52605C; page-break-inside: avoid; }
                .badge { display: inline-block; padding: 2px 6px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
                .badge-paid { background: #E7F6EC; color: #0A5E2E; border: 1px solid #A6E3B8; }
                .badge-partial { background: #FEF6EE; color: #8C4405; border: 1px solid #F9DBAF; }
                .badge-unpaid { background: #FDECEA; color: #8E1B12; border: 1px solid #F9B6AF; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0 !important; }
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <table class='header-table'>
                <tr>
                    <td style='vertical-align: top; width: 60%;'>
                        ".($shopLogo ? "<img src='{$shopLogo}' alt='Logo' style='max-height: 48px; margin-bottom: 6px;'><br>" : '')."
                        <h1 style='margin: 0; font-size: {$titleSize}; font-weight: 800; color: #0B6E5E; letter-spacing: -0.5px;'>{$shopName}</h1>
                        ".($shopAddress ? "<div style='color: #4A5957; font-size: 11px; margin-top: 2px;'>{$shopAddress}</div>" : '')."
                        ".($shopPhone ? "<div style='color: #4A5957; font-size: 11px;'>Phone: {$shopPhone}</div>" : '')."
                    </td>
                    <td style='vertical-align: top; text-align: right; width: 40%;'>
                        <div style='font-size: 18px; font-weight: 800; color: #1A2725;'>INVOICE</div>
                        <div style='font-family: monospace; font-size: 13px; font-weight: bold; color: #0B6E5E; margin-top: 2px;'>{$invoiceNo}</div>
                        <div style='color: #4A5957; font-size: 11px; margin-top: 4px;'><strong>Date:</strong> {$saleDate}</div>
                        <div style='margin-top: 6px;'>
                            <span class='badge ".($data['payment_status'] === 'paid' ? 'badge-paid' : ($data['payment_status'] === 'partial' ? 'badge-partial' : 'badge-unpaid'))."'>
                                ".strtoupper($data['payment_status'])."
                            </span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Billed To & Delivery -->
            <table class='billing-table' style='background: #FAFBFB; border: 1px solid #D5DCDB;'>
                <tr>
                    <td style='padding: 10px 14px; vertical-align: top; width: 55%;'>
                        <div style='font-size: 10px; font-weight: bold; text-transform: uppercase; color: #0B6E5E; letter-spacing: 0.5px;'>Billed To / Customer</div>
                        <div style='font-size: 13px; font-weight: bold; margin-top: 3px;'>{$customerName}</div>
                        <div style='font-family: monospace; font-size: 12px; color: #333;'>Phone: {$customerPhone}</div>
                        <div style='color: #4A5957; font-size: 11px; margin-top: 2px;'>{$customerAddress}</div>
                    </td>
                    <td style='padding: 10px 14px; vertical-align: top; width: 45%; border-left: 1px solid #D5DCDB;'>
                        <div style='font-size: 10px; font-weight: bold; text-transform: uppercase; color: #0B6E5E; letter-spacing: 0.5px;'>Delivery Details</div>
                        <div style='font-size: 12px; margin-top: 3px;'>
                            <strong>Mode:</strong> ".($data['courier']['mode'] === 'pathao' ? 'Pathao Courier' : 'Hand-delivered / No Courier')."
                        </div>
                        ".($consignmentId ? "<div style='font-size: 11px; margin-top: 2px;'><strong>Consignment ID:</strong> <span style='font-family: monospace;'>{$consignmentId}</span></div>" : '')."
                        ".($data['courier']['city'] ? "<div style='font-size: 11px; color: #4A5957;'>{$data['courier']['area']}, {$data['courier']['zone']}, {$data['courier']['city']}</div>" : '')."
                        <div style='font-size: 11px; margin-top: 2px;'><strong>Payment Method:</strong> {$paymentMethods}</div>
                    </td>
                </tr>
            </table>

            <!-- Items Table -->
            <table class='items-table'>
                <thead>
                    <tr>
                        <th style='text-align: left; width: 30px;'>#</th>
                        <th style='text-align: left;'>Item Description</th>
                        <th style='text-align: center; width: 50px;'>Qty</th>
                        <th style='text-align: right; width: 90px;'>Unit Price</th>
                        <th style='text-align: right; width: 100px;'>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsHtml}
                </tbody>
            </table>

            <!-- Summary Table -->
            <div class='summary-container'>
                <table class='summary-table'>
                    <tr>
                        <td style='color: #4A5957;'>Subtotal:</td>
                        <td style='text-align: right; font-family: monospace;'>{$currency}{$subtotal}</td>
                    </tr>
                    ".($data['financials']['discount_amount'] > 0 ? "
                    <tr>
                        <td style='color: #8E1B12;'>Discount:</td>
                        <td style='text-align: right; font-family: monospace; color: #8E1B12;'>-{$currency}{$discount}</td>
                    </tr>" : '')."
                    ".($data['financials']['delivery_charge'] > 0 ? "
                    <tr>
                        <td style='color: #4A5957;'>Delivery Charge:</td>
                        <td style='text-align: right; font-family: monospace;'>{$currency}{$delivery}</td>
                    </tr>" : '')."
                    <tr class='grand-total-row'>
                        <td style='padding: 6px;'>Grand Total:</td>
                        <td style='text-align: right; padding: 6px; font-family: monospace;'>{$currency}{$grandTotal}</td>
                    </tr>
                    <tr>
                        <td style='color: #4A5957;'>Amount Paid:</td>
                        <td style='text-align: right; font-family: monospace; font-weight: bold;'>{$currency}{$paidTotal}</td>
                    </tr>
                    <tr style='border-top: 1px solid #D5DCDB; font-weight: bold;'>
                        <td>Balance Due:</td>
                        <td style='text-align: right; font-family: monospace; color: ".($data['financials']['due_amount'] > 0 ? '#8E1B12' : '#0B6E5E').";'>{$currency}{$dueAmount}</td>
                    </tr>
                </table>
            </div>

            <!-- Footer / Terms -->
            ".($footerNote || $terms ? "
            <div class='footer-box'>
                ".($footerNote ? "<div style='font-size: 11px; color: #1A2725; margin-bottom: 3px;'>{$footerNote}</div>" : '')."
                ".($terms ? "<div style='font-style: italic;'>{$terms}</div>" : '')."
            </div>" : '')."
        </body>
        </html>
        ";
    }

    /**
     * Render a custom template by strictly replacing safe documented placeholders.
     * Escapes customer variables to prevent XSS / markup injection.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderCustomTemplate(string $template, array $data, string $paperSize): string
    {
        $sanitizedTemplate = $this->sanitizeTemplate($template);

        $currency = htmlspecialchars($data['currency'], ENT_QUOTES, 'UTF-8');
        $customerName = htmlspecialchars($data['customer']['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($data['customer']['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerAddress = nl2br(htmlspecialchars($data['customer']['address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $invoiceNo = htmlspecialchars($data['invoice_no'], ENT_QUOTES, 'UTF-8');
        $saleDate = htmlspecialchars($data['sale_date'], ENT_QUOTES, 'UTF-8');
        $shopName = htmlspecialchars($data['shop']['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopAddress = htmlspecialchars($data['shop']['address'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopPhone = htmlspecialchars($data['shop']['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $shopLogo = $data['shop']['logo'] ? "<img src='".htmlspecialchars($data['shop']['logo'], ENT_QUOTES, 'UTF-8')."' style='max-height:48px;'>" : '';
        $paymentMethods = htmlspecialchars($data['financials']['payment_methods'], ENT_QUOTES, 'UTF-8');
        $consignmentId = htmlspecialchars($data['courier']['consignment_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $footerNote = htmlspecialchars($data['branding']['footer_note'] ?? '', ENT_QUOTES, 'UTF-8');
        $terms = htmlspecialchars($data['branding']['terms'] ?? '', ENT_QUOTES, 'UTF-8');

        // Render items table for custom template
        $itemsHtml = "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>
            <thead>
                <tr style='background: #E6F2EF; color: #0B6E5E;'>
                    <th style='padding: 6px; text-align: left;'>#</th>
                    <th style='padding: 6px; text-align: left;'>Item</th>
                    <th style='padding: 6px; text-align: center;'>Qty</th>
                    <th style='padding: 6px; text-align: right;'>Price</th>
                    <th style='padding: 6px; text-align: right;'>Total</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($data['items'] as $idx => $it) {
            $num = $idx + 1;
            $iName = htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8');
            $iSku = htmlspecialchars($it['variant_sku'] ?? '', ENT_QUOTES, 'UTF-8');
            $skuSub = $iSku ? "<div style='font-size: 10px; color: #666; font-family: monospace;'>SKU: {$iSku}</div>" : '';
            $iQty = $it['qty'];
            $iPrice = number_format($it['unit_price'], 2);
            $iTot = number_format($it['line_total'], 2);
            $itemsHtml .= "<tr style='border-bottom: 1px solid #EBEFEE;'>
                <td style='padding: 6px;'>{$num}</td>
                <td style='padding: 6px;'>
                    <div>{$iName}</div>
                    {$skuSub}
                </td>
                <td style='padding: 6px; text-align: center;'>{$iQty}</td>
                <td style='padding: 6px; text-align: right; font-family: monospace;'>{$currency}{$iPrice}</td>
                <td style='padding: 6px; text-align: right; font-weight: bold; font-family: monospace;'>{$currency}{$iTot}</td>
            </tr>";
        }
        $itemsHtml .= '</tbody></table>';

        $replacements = [
            '{{shop_logo}}' => $shopLogo,
            '{{shop_name}}' => $shopName,
            '{{shop_address}}' => $shopAddress,
            '{{shop_phone}}' => $shopPhone,
            '{{invoice_no}}' => $invoiceNo,
            '{{sale_date}}' => $saleDate,
            '{{customer_name}}' => $customerName,
            '{{customer_phone}}' => $customerPhone,
            '{{customer_address}}' => $customerAddress,
            '{{items_table}}' => $itemsHtml,
            '{{subtotal}}' => $currency.number_format($data['financials']['subtotal'], 2),
            '{{discount}}' => $currency.number_format($data['financials']['discount_amount'], 2),
            '{{delivery_charge}}' => $currency.number_format($data['financials']['delivery_charge'], 2),
            '{{grand_total}}' => $currency.number_format($data['financials']['grand_total'], 2),
            '{{paid_total}}' => $currency.number_format($data['financials']['paid_total'], 2),
            '{{due_amount}}' => $currency.number_format($data['financials']['due_amount'], 2),
            '{{payment_methods}}' => $paymentMethods,
            '{{consignment_id}}' => $consignmentId,
            '{{footer_note}}' => $footerNote,
            '{{terms}}' => $terms,
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $sanitizedTemplate);

        $sizeCss = match ($paperSize) {
            '80MM' => 'size: 80mm auto;',
            'A5' => 'size: a5 portrait;',
            default => 'size: a4 portrait;',
        };

        return "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Invoice {$invoiceNo}</title>
            <style>
                @page { margin: 8mm; {$sizeCss} }
                body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 10px; background: #fff; }
                @media print { .no-print { display: none !important; } }
            </style>
        </head>
        <body>
            {$rendered}
        </body>
        </html>";
    }

    /**
     * Strictly sanitize template string: strip executable PHP, Blade, scripts, and unsafe tags.
     */
    public function sanitizeTemplate(string $raw): string
    {
        // Remove PHP tags and contents
        $clean = preg_replace('/<\?php.*?\?>/is', '', $raw);
        $clean = preg_replace('/<\?=.*?\?>/is', '', $clean);
        $clean = preg_replace('/<\?.*?\?>/is', '', $clean);

        // Remove script, iframe, object, embed tags and their contents
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $clean);
        $clean = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $clean);
        $clean = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $clean);
        $clean = preg_replace('/<embed\b[^>]*>(.*?)<\/embed>/is', '', $clean);

        // Remove Blade directives like @php, @include
        $clean = preg_replace('/@(php|include|extends|section|yield|component)\b/i', '', $clean);
        $clean = preg_replace('/\{\{--.*?--\}\}/s', '', $clean);

        // Remove inline event handlers (onload, onerror, onclick, etc.)
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

        // Remove javascript: and data: pseudo protocols
        $clean = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $clean);
        $clean = preg_replace('/src\s*=\s*["\']?\s*data:text\/html[^"\'>\s]*/i', 'src="#"', $clean);

        return $clean;
    }
}
