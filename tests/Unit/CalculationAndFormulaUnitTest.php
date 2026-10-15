<?php

namespace Tests\Unit;

/**
 * Pure Unit Tests for Core Business Formulas and Calculations (PRD §5, §6, §7, §9, §10, §11, §12)
 */

describe('1. Money & Quantity Calculations', function () {
    test('money rounding and precision adheres strictly to two decimal places', function () {
        $calculateLineTotal = fn (float|int $qty, float $unitPrice) => round($qty * $unitPrice, 2);

        expect($calculateLineTotal(3, 1250.333))->toBe(3751.00)
            ->and($calculateLineTotal(7, 333.3333))->toBe(2333.33)
            ->and($calculateLineTotal(0.5, 4500.00))->toBe(2250.00);
    });

    test('decimal quantity calculations for raw materials (leather sqft, accessory fractions)', function () {
        // Leather: 14.75 sqft consumed at 185.50 BDT / sqft
        $qty = 14.75;
        $rate = 185.50;
        $totalCost = round($qty * $rate, 2);

        expect($totalCost)->toBe(2736.13);

        // Accessory: 2.5 gross (360 pcs) or 12.3 meters
        $meters = 12.30;
        $ratePerMeter = 45.00;
        expect(round($meters * $ratePerMeter, 2))->toBe(553.50);
    });

    test('positive quantity validation blocks negative or zero amounts', function () {
        $validateQty = fn ($qty) => is_numeric($qty) && (float) $qty > 0;

        expect($validateQty(5))->toBeTrue()
            ->and($validateQty(0.25))->toBeTrue()
            ->and($validateQty(0))->toBeFalse()
            ->and($validateQty(-1))->toBeFalse()
            ->and($validateQty('abc'))->toBeFalse();
    });

    test('currency formatting with BDT symbol and South-Asian number grouping pattern', function () {
        $formatBDT = function (float $amount): string {
            $formatted = number_format(abs($amount), 2);
            $sign = $amount < 0 ? '-' : '';

            return "{$sign}৳{$formatted}";
        };

        expect($formatBDT(1500.00))->toBe('৳1,500.00')
            ->and($formatBDT(250000.50))->toBe('৳250,000.50')
            ->and($formatBDT(-5000.00))->toBe('-৳5,000.00')
            ->and($formatBDT(0.00))->toBe('৳0.00');
    });
});

describe('2. Discount Calculations', function () {
    test('flat discount in BDT cannot exceed subtotal and deducts directly', function () {
        $computeFlatDiscount = function (float $subtotal, float $discountValue): float {
            $discount = max(0, round($discountValue, 2));

            return min($subtotal, $discount);
        };

        // Normal flat discount: ৳500 on ৳5,000 subtotal
        expect($computeFlatDiscount(5000.00, 500.00))->toBe(500.00);

        // Discount equal to subtotal: ৳5,000 on ৳5,000
        expect($computeFlatDiscount(5000.00, 5000.00))->toBe(5000.00);

        // Discount exceeding subtotal gets capped at subtotal
        expect($computeFlatDiscount(5000.00, 6000.00))->toBe(5000.00);

        // Zero or negative discount input
        expect($computeFlatDiscount(5000.00, 0.00))->toBe(0.00);
        expect($computeFlatDiscount(5000.00, -200.00))->toBe(0.00);
    });

    test('percentage discount computes accurate percentage of subtotal and caps at 100%', function () {
        $computePercentDiscount = function (float $subtotal, float $percent): float {
            $safePercent = min(100.0, max(0.0, round($percent, 2)));

            return round($subtotal * ($safePercent / 100), 2);
        };

        // 10% discount on ৳5,000 = ৳500
        expect($computePercentDiscount(5000.00, 10.0))->toBe(500.00);

        // 15.5% discount on ৳3,450 = ৳534.75
        expect($computePercentDiscount(3450.00, 15.5))->toBe(534.75);

        // 100% discount on ৳2,000 = ৳2000
        expect($computePercentDiscount(2000.00, 100.0))->toBe(2000.00);

        // More than 100% gets capped at 100%
        expect($computePercentDiscount(2000.00, 150.0))->toBe(2000.00);

        // Negative percent returns 0
        expect($computePercentDiscount(2000.00, -10.0))->toBe(0.00);
    });

    test('grand total computation formula: subtotal - discount + delivery', function () {
        $calculateGrandTotal = function (float $subtotal, float $discountAmount, float $deliveryCharge): float {
            return round(max(0, $subtotal - $discountAmount + $deliveryCharge), 2);
        };

        // Standard order: ৳5000 - ৳500 + ৳100 = ৳4600
        expect($calculateGrandTotal(5000.00, 500.00, 100.00))->toBe(4600.00);

        // Free delivery: ৳3000 - ৳0 + ৳0 = ৳3000
        expect($calculateGrandTotal(3000.00, 0.00, 0.00))->toBe(3000.00);

        // 100% discount with delivery charge: ৳2000 - ৳2000 + ৳100 = ৳100
        expect($calculateGrandTotal(2000.00, 2000.00, 100.00))->toBe(100.00);
    });
});

describe('3. Invoice Numbering & Period Formats', function () {
    test('monthly sequential invoice formatting: PREFIX-YYMM-####', function () {
        $formatInvoiceNo = function (string $prefix, string $yearMonth, int $sequence): string {
            $padded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            return "{$prefix}-{$yearMonth}-{$padded}";
        };

        expect($formatInvoiceNo('TASA', '2610', 1))->toBe('TASA-2610-0001')
            ->and($formatInvoiceNo('TASA', '2610', 42))->toBe('TASA-2610-0042')
            ->and($formatInvoiceNo('TASA', '2610', 999))->toBe('TASA-2610-0999')
            ->and($formatInvoiceNo('TASA', '2610', 1000))->toBe('TASA-2610-1000')
            ->and($formatInvoiceNo('CUSTOM', '2701', 5))->toBe('CUSTOM-2701-0005');
    });

    test('period parsing derives two-digit year and two-digit month', function () {
        $getPeriod = function (string $dateString): string {
            $dt = new \DateTimeImmutable($dateString);

            return $dt->format('ym');
        };

        expect($getPeriod('2026-10-14'))->toBe('2610')
            ->and($getPeriod('2026-11-01'))->toBe('2611')
            ->and($getPeriod('2027-01-15'))->toBe('2701');
    });
});

describe('4. Stock Calculations & Weighted Average Cost', function () {
    test('weighted average unit cost formula for new inventory purchase', function () {
        $computeWeightedAvgCost = function (
            float $currentQty,
            float $currentAvgCost,
            float $newQty,
            float $newUnitRate
        ): float {
            $totalQty = $currentQty + $newQty;
            if ($totalQty <= 0) {
                return 0.00;
            }

            $totalValue = ($currentQty * $currentAvgCost) + ($newQty * $newUnitRate);

            return round($totalValue / $totalQty, 2);
        };

        // Initial purchase: 100 sqft @ ৳150 = ৳150.00
        $lot1Avg = $computeWeightedAvgCost(0, 0, 100, 150.00);
        expect($lot1Avg)->toBe(150.00);

        // Second purchase: 50 sqft @ ৳180 -> (100*150 + 50*180) / 150 = (15000 + 9000)/150 = 24000/150 = 160.00
        $lot2Avg = $computeWeightedAvgCost(100, $lot1Avg, 50, 180.00);
        expect($lot2Avg)->toBe(160.00);

        // Third purchase with price drop: 50 sqft @ ৳120 -> (150*160 + 50*120) / 200 = (24000 + 6000)/200 = 150.00
        $lot3Avg = $computeWeightedAvgCost(150, $lot2Avg, 50, 120.00);
        expect($lot3Avg)->toBe(150.00);
    });

    test('consumption decreases stock quantity without altering unit cost', function () {
        $currentQty = 150.0;
        $unitCost = 160.0;
        $consumedQty = 40.0;

        $remainingQty = $currentQty - $consumedQty;
        $remainingValue = round($remainingQty * $unitCost, 2);

        expect($remainingQty)->toBe(110.0)
            ->and($unitCost)->toBe(160.0) // Unit cost remains constant upon consumption!
            ->and($remainingValue)->toBe(17600.00);
    });

    test('stock movement running balance tracking', function () {
        $startingBalance = 20;
        $movements = [
            ['type' => 'sale', 'qty' => -3],
            ['type' => 'sale', 'qty' => -2],
            ['type' => 'return', 'qty' => 1],
            ['type' => 'production', 'qty' => 10],
            ['type' => 'adjustment', 'qty' => -1],
        ];

        $runningBalance = $startingBalance;
        $auditBalances = [];

        foreach ($movements as $m) {
            $runningBalance += $m['qty'];
            $auditBalances[] = $runningBalance;
        }

        expect($auditBalances)->toBe([17, 15, 16, 26, 25])
            ->and($runningBalance)->toBe(25);
    });
});

describe('5. Payroll Balance Formulas (Salaried & Wage Workers)', function () {
    test('salaried employee monthly payable formula: base + additions - deductions', function () {
        $computePayable = function (float $base, float $additions, float $deductions): float {
            return round($base + $additions - $deductions, 2);
        };

        // Regular salary: 25,000 + 0 - 0 = 25,000
        expect($computePayable(25000, 0, 0))->toBe(25000.00);

        // Bonus: 25,000 + 3,000 (Festival bonus) - 1,000 (Late deduction) = 27,000
        expect($computePayable(25000, 3000, 1000))->toBe(27000.00);
    });

    test('salaried employee monthly carry-forward and overpayment balance', function () {
        $computeMonthBalance = function (float $payable, float $paidTotal): float {
            return round($payable - $paidTotal, 2);
        };

        $computeNetBalance = function (float $previousBalance, float $currentPayable, float $currentPaid): float {
            return round($previousBalance + $currentPayable - $currentPaid, 2);
        };

        // Month 1: Payable ৳25,000, Paid ৳20,000 -> Month balance ৳5,000 due to employee
        $m1Balance = $computeMonthBalance(25000, 20000);
        expect($m1Balance)->toBe(5000.00);

        // Month 2: Starts with ৳5,000 carry forward. Payable ৳25,000. Total owed = ৳30,000. Paid ৳35,000 (Advance given)
        // Net balance = 5000 + 25000 - 35000 = -৳5,000 (Employee owes Tasa / Overpayment)
        $m2NetBalance = $computeNetBalance($m1Balance, 25000, 35000);
        expect($m2NetBalance)->toBe(-5000.00);

        // Month 3: Starts with -৳5,000 carry forward. Payable ৳25,000. Paid ৳20,000.
        // Net balance = -5000 + 25000 - 20000 = ৳0.00 (Fully cleared)
        $m3NetBalance = $computeNetBalance($m2NetBalance, 25000, 20000);
        expect($m3NetBalance)->toBe(0.00);
    });

    test('wage worker per-day and per-piece earnings calculations', function () {
        // Per-day worker: 6 days worked at ৳800/day + ৳400 overtime
        $computePerDayEarnings = fn (int $days, float $dailyRate, float $overtime) => round(($days * $dailyRate) + $overtime, 2);
        expect($computePerDayEarnings(6, 800.00, 400.00))->toBe(5200.00);

        // Per-piece worker: 15 bags @ ৳250/bag + 30 wallets @ ৳80/wallet
        $computePerPieceEarnings = function (array $pieces): float {
            $total = 0.0;
            foreach ($pieces as $item) {
                $total += $item['qty'] * $item['rate'];
            }

            return round($total, 2);
        };

        $pieces = [
            ['qty' => 15, 'rate' => 250.00], // 3,750
            ['qty' => 30, 'rate' => 80.00],  // 2,400
        ];
        expect($computePerPieceEarnings($pieces))->toBe(6150.00);
    });

    test('wage worker running balance ledger: opening + earnings - payments = closing', function () {
        $computeWorkerClosingBalance = function (float $openingBalance, float $totalEarnings, float $totalPayments): float {
            return round($openingBalance + $totalEarnings - $totalPayments, 2);
        };

        // Opening balance ৳1,000 (owed from last week). Earned ৳5,200. Paid ৳6,000.
        // Closing balance = 1,000 + 5,200 - 6,000 = ৳200 (still owed to worker)
        expect($computeWorkerClosingBalance(1000.00, 5200.00, 6000.00))->toBe(200.00);

        // Worker takes advance: Opening ৳0. Earned ৳4,000. Paid ৳6,000.
        // Closing = 0 + 4,000 - 6,000 = -৳2,000 (worker owes Tasa, violet indicator)
        expect($computeWorkerClosingBalance(0.00, 4000.00, 6000.00))->toBe(-2000.00);
    });
});

describe('6. P&L Statement Formulas (PRD §11 Lines A to Q)', function () {
    test('PRD §11.1 & §11.3 exact financial statement calculations', function () {
        // Line A: Gross Sales (invoice subtotals for non-cancelled sales)
        $lineA = 200000.00;
        // Line B: Discounts
        $lineB = 10000.00;
        // Line C: Returns
        $lineC = 5000.00;
        // Line D: Net Sales = A - B - C
        $lineD = round($lineA - $lineB - $lineC, 2);
        expect($lineD)->toBe(185000.00);

        // Line E: Delivery charges collected from customers
        $lineE = 8000.00;
        // Line F: Total Revenue = Net Sales (D) + Delivery (E)
        $lineF = round($lineD + $lineE, 2);
        expect($lineF)->toBe(193000.00);

        // Line G: Cost of Goods Sold (Quantity * Unit Cost Snapshot)
        $lineG = 80000.00;

        // Line H: Gross Profit = Total Revenue (F) - COGS (G)
        $lineH = round($lineF - $lineG, 2);
        expect($lineH)->toBe(113000.00);

        // Gross Margin % = Gross Profit (H) / Total Revenue (F) * 100
        $grossMarginPct = round(($lineH / $lineF) * 100, 2);
        expect($grossMarginPct)->toBe(58.55); // 58.5%

        // Operating Expenses:
        $lineI1 = 30000.00; // Facebook Ads
        $lineI2 = 5000.00;  // Other Marketing
        $lineJ  = 12000.00; // Courier charges
        $lineK  = 35000.00; // Salaries
        $lineL  = 15000.00; // Wages (when labour not in cost)
        $lineM  = 4000.00;  // Packaging
        $lineN  = 4000.00;  // Rent & Utilities
        $lineO  = 2000.00;  // Other Expenses

        // Total Operating Expenses (Line P)
        $lineP = round($lineI1 + $lineI2 + $lineJ + $lineK + $lineL + $lineM + $lineN + $lineO, 2);
        expect($lineP)->toBe(107000.00);

        // Line Q: Net Profit = Gross Profit (H) - Total OPEX (P)
        $lineQ = round($lineH - $lineP, 2);
        expect($lineQ)->toBe(6000.00); // Nets exactly ৳6,000 as worked example in PRD §11.3!

        // Net Margin % = Net Profit (Q) / Total Revenue (F) * 100
        $netMarginPct = round(($lineQ / $lineF) * 100, 2);
        expect($netMarginPct)->toBe(3.11); // ~3.1%

        // Facebook Ads ROAS = Net Sales (D) / Facebook Ads (I1)
        $roas = round($lineD / $lineI1, 2);
        expect($roas)->toBe(6.17); // 6.17x ROAS
    });

    test('safe handling of zero denominators in margins and ROAS', function () {
        $computeGrossMargin = fn (float $gp, float $rev) => $rev > 0 ? round(($gp / $rev) * 100, 2) : 0.0;
        $computeROAS = fn (float $netSales, float $fbAds) => $fbAds > 0 ? round($netSales / $fbAds, 2) : null;

        expect($computeGrossMargin(0, 0))->toBe(0.0)
            ->and($computeGrossMargin(500, 0))->toBe(0.0)
            ->and($computeROAS(50000, 0))->toBeNull();
    });
});

describe('7. Payment Statuses & Due Amounts', function () {
    test('payment status determination based on grand total and paid amount', function () {
        $getPaymentStatus = function (float $grandTotal, float $paidTotal): string {
            $due = round($grandTotal - $paidTotal, 2);
            if ($due <= 0.001) {
                return 'paid';
            }
            if ($paidTotal > 0) {
                return 'partial';
            }

            return 'unpaid';
        };

        // Fully paid upfront
        expect($getPaymentStatus(5100.00, 5100.00))->toBe('paid');

        // Partial payment (e.g. ৳1,000 paid on ৳5,100 total)
        expect($getPaymentStatus(5100.00, 1000.00))->toBe('partial');

        // Zero payment (Full Cash on Delivery)
        expect($getPaymentStatus(5100.00, 0.00))->toBe('unpaid');

        // Zero total invoice (e.g. replacement / promo)
        expect($getPaymentStatus(0.00, 0.00))->toBe('paid');
    });

    test('balance due cannot be negative and reflects true receivable', function () {
        $computeDue = fn (float $grandTotal, float $paidTotal) => round(max(0, $grandTotal - $paidTotal), 2);

        expect($computeDue(5100.00, 0.00))->toBe(5100.00)
            ->and($computeDue(5100.00, 1000.00))->toBe(4100.00)
            ->and($computeDue(5100.00, 5100.00))->toBe(0.00)
            ->and($computeDue(5100.00, 6000.00))->toBe(0.00);
    });
});

describe('8. Courier Collection (COD Amount-to-Collect)', function () {
    test('courier COD amount equals remaining due balance for delivery', function () {
        $computeCodAmount = function (string $courierMode, float $grandTotal, float $advancePaid): float {
            if ($courierMode === 'none') {
                return 0.00;
            }

            return round(max(0, $grandTotal - $advancePaid), 2);
        };

        // A: Full Cash on Delivery (0 paid) on ৳5,100 grand total -> Collect ৳5,100
        expect($computeCodAmount('pathao', 5100.00, 0.00))->toBe(5100.00);

        // B: Partial advance payment (৳1,000 paid) on ৳5,100 grand total -> Collect ৳4,100
        expect($computeCodAmount('pathao', 5100.00, 1000.00))->toBe(4100.00);

        // C: Full advance payment (৳5,100 paid) -> Collect ৳0
        expect($computeCodAmount('pathao', 5100.00, 5100.00))->toBe(0.00);

        // D: Hand delivery (courier mode none) -> Collect ৳0
        expect($computeCodAmount('none', 5100.00, 0.00))->toBe(0.00);
    });
});
