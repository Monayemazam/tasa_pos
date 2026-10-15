<?php

namespace App\Services\Inventory;

use App\Models\ImportLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportService
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Generate downloadable Excel template (or CSV).
     */
    public function downloadTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Product name',
            'Variation attribute',
            'Variation value',
            'SKU',
            'Category',
            'Cost price',
            'Selling price',
            'Opening stock',
            'Min stock',
        ];

        $sampleRows = [
            ['Eyewear Case Series 1', 'Color', 'Brown', 'EWC-S1-BRN', 'Eyewear Cases', '450.00', '850.00', '12', '5'],
            ['Eyewear Case Series 1', 'Color', 'Black', 'EWC-S1-BLK', 'Eyewear Cases', '450.00', '850.00', '8', '5'],
            ['Classic Bifold Wallet', '', '', 'P-000101', 'Wallets', '850.00', '1450.00', '20', '5'],
        ];

        if ($format === 'csv') {
            $filename = 'tasa_product_import_template.csv';

            return response()->streamDownload(function () use ($headers, $sampleRows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);
                foreach ($sampleRows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Template');

        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        foreach ($headers as $idx => $header) {
            $sheet->setCellValue("{$colLetters[$idx]}1", $header);
            $sheet->getStyle("{$colLetters[$idx]}1")->getFont()->setBold(true);
        }

        foreach ($sampleRows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            foreach ($row as $colIdx => $val) {
                $sheet->setCellValue("{$colLetters[$colIdx]}{$rowNum}", $val);
            }
        }

        foreach ($colLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'tasa_product_import_template.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Parse uploaded file and return row-by-row preview with validation errors.
     */
    public function preview(UploadedFile $file, bool $canViewCost = true): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded file must be an Excel spreadsheet (.xlsx, .xls) or CSV (.csv).',
            ]);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'File size cannot exceed 5MB.',
            ]);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        if (empty($rawRows) || count($rawRows) < 2) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded spreadsheet contains no data rows.',
            ]);
        }

        // Parse header row
        $headerRow = array_map(fn ($h) => strtolower(trim((string) $h)), $rawRows[0]);
        $headerMap = [];
        foreach ($headerRow as $colIdx => $colName) {
            if (str_contains($colName, 'attribute') || str_contains($colName, 'variation type')) {
                $headerMap['variation_attribute'] = $colIdx;
            } elseif (str_contains($colName, 'value') || str_contains($colName, 'color') || str_contains($colName, 'variant')) {
                $headerMap['variation_value'] = $colIdx;
            } elseif (str_contains($colName, 'name')) {
                $headerMap['name'] = $colIdx;
            } elseif (str_contains($colName, 'sku') || str_contains($colName, 'code')) {
                $headerMap['sku'] = $colIdx;
            } elseif (str_contains($colName, 'category')) {
                $headerMap['category'] = $colIdx;
            } elseif (str_contains($colName, 'cost')) {
                $headerMap['cost_price'] = $colIdx;
            } elseif (str_contains($colName, 'sell') || str_contains($colName, 'price')) {
                $headerMap['selling_price'] = $colIdx;
            } elseif (str_contains($colName, 'opening')) {
                $headerMap['opening_stock'] = $colIdx;
            } elseif (str_contains($colName, 'min')) {
                $headerMap['min_stock'] = $colIdx;
            }
        }

        if (! isset($headerMap['name'])) {
            throw ValidationException::withMessages([
                'file' => 'Missing required "Name" column header in spreadsheet.',
            ]);
        }

        $parsedRows = [];
        $fileSkus = [];
        $fileKeys = [];

        for ($i = 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            // Check if entire row is empty
            $rowString = implode('', array_filter($row, fn ($val) => $val !== null && trim((string) $val) !== ''));
            if ($rowString === '') {
                continue;
            }

            $name = isset($headerMap['name']) ? trim((string) ($row[$headerMap['name']] ?? '')) : '';
            $variationAttribute = isset($headerMap['variation_attribute']) ? trim((string) ($row[$headerMap['variation_attribute']] ?? '')) : '';
            $variationValue = isset($headerMap['variation_value']) ? trim((string) ($row[$headerMap['variation_value']] ?? '')) : '';
            $sku = isset($headerMap['sku']) ? trim((string) ($row[$headerMap['sku']] ?? '')) : '';
            $category = isset($headerMap['category']) ? trim((string) ($row[$headerMap['category']] ?? '')) : '';
            $costPriceRaw = isset($headerMap['cost_price']) ? trim((string) ($row[$headerMap['cost_price']] ?? '')) : '0';
            $sellingPriceRaw = isset($headerMap['selling_price']) ? trim((string) ($row[$headerMap['selling_price']] ?? '')) : '0';
            $openingStockRaw = isset($headerMap['opening_stock']) ? trim((string) ($row[$headerMap['opening_stock']] ?? '')) : '0';
            $minStockRaw = isset($headerMap['min_stock']) ? trim((string) ($row[$headerMap['min_stock']] ?? '')) : '0';

            $errors = [];

            // Name validation
            if ($name === '') {
                $errors[] = 'Product name is required.';
            } elseif (mb_strlen($name) > 190) {
                $errors[] = 'Product name cannot exceed 190 characters.';
            }

            // Variation value validation
            if ($variationValue !== '' && mb_strlen($variationValue) > 50) {
                $errors[] = 'Variation value cannot exceed 50 characters.';
            }

            // SKU validation
            if ($sku !== '') {
                if (mb_strlen($sku) > 50) {
                    $errors[] = 'SKU cannot exceed 50 characters.';
                }
                if (in_array(strtolower($sku), $fileSkus, true)) {
                    $errors[] = "Duplicate SKU '{$sku}' found in this upload.";
                } else {
                    $fileSkus[] = strtolower($sku);
                }
            }

            // Duplicate item/variant check within file
            if ($name !== '') {
                $itemKey = strtolower($name) . '::' . strtolower($variationValue);
                if (in_array($itemKey, $fileKeys, true)) {
                    $desc = $variationValue !== '' ? "variant '{$name} ({$variationValue})'" : "product '{$name}'";
                    $errors[] = "Duplicate {$desc} found in this upload.";
                } else {
                    $fileKeys[] = $itemKey;
                }
            }

            // Numeric checks
            $costPrice = 0.00;
            if ($costPriceRaw !== '') {
                if (! is_numeric($costPriceRaw)) {
                    $errors[] = 'Cost price must be a valid number.';
                } elseif ((float) $costPriceRaw < 0) {
                    $errors[] = 'Cost price cannot be negative.';
                } else {
                    $costPrice = (float) $costPriceRaw;
                }
            }

            $sellingPrice = 0.00;
            if ($sellingPriceRaw !== '') {
                if (! is_numeric($sellingPriceRaw)) {
                    $errors[] = 'Selling price must be a valid number.';
                } elseif ((float) $sellingPriceRaw < 0) {
                    $errors[] = 'Selling price cannot be negative.';
                } else {
                    $sellingPrice = (float) $sellingPriceRaw;
                }
            }

            $openingStock = 0;
            if ($openingStockRaw !== '') {
                if (! ctype_digit(ltrim($openingStockRaw, '+')) && ! is_numeric($openingStockRaw)) {
                    $errors[] = 'Opening stock must be a whole number.';
                } elseif ((int) $openingStockRaw < 0) {
                    $errors[] = 'Opening stock cannot be negative.';
                } else {
                    $openingStock = (int) $openingStockRaw;
                }
            }

            $minStock = 0;
            if ($minStockRaw !== '') {
                if (! ctype_digit(ltrim($minStockRaw, '+')) && ! is_numeric($minStockRaw)) {
                    $errors[] = 'Minimum stock must be a whole number.';
                } elseif ((int) $minStockRaw < 0) {
                    $errors[] = 'Minimum stock cannot be negative.';
                } else {
                    $minStock = (int) $minStockRaw;
                }
            }

            $parsedRows[] = [
                'row_number' => $i + 1,
                'name' => $name,
                'variation_attribute' => $variationAttribute ?: ($variationValue !== '' ? 'Color' : null),
                'variation_value' => $variationValue ?: null,
                'sku' => $sku,
                'category' => $category,
                'cost_price' => $canViewCost ? number_format($costPrice, 2, '.', '') : null,
                'selling_price' => number_format($sellingPrice, 2, '.', ''),
                'opening_stock' => $openingStock,
                'min_stock' => $minStock,
                'errors' => $errors,
                'is_valid' => empty($errors),
            ];
        }

        // Database SKU collisions check across products and variants
        $providedSkus = array_filter(array_column($parsedRows, 'sku'));
        if (! empty($providedSkus)) {
            $existingProductSkus = Product::withTrashed()
                ->whereIn('sku', $providedSkus)
                ->pluck('sku')
                ->map(fn ($s) => strtolower($s))
                ->toArray();

            $existingVariantSkus = ProductVariant::withTrashed()
                ->whereIn('sku', $providedSkus)
                ->pluck('sku')
                ->map(fn ($s) => strtolower($s))
                ->toArray();

            $allExistingSkus = array_unique(array_merge($existingProductSkus, $existingVariantSkus));

            if (! empty($allExistingSkus)) {
                foreach ($parsedRows as &$pRow) {
                    if ($pRow['sku'] !== '' && in_array(strtolower($pRow['sku']), $allExistingSkus, true)) {
                        $pRow['errors'][] = "SKU '{$pRow['sku']}' already exists in database.";
                        $pRow['is_valid'] = false;
                    }
                }
                unset($pRow);
            }
        }

        $validCount = count(array_filter($parsedRows, fn ($r) => $r['is_valid']));
        $invalidCount = count($parsedRows) - $validCount;

        return [
            'filename' => $file->getClientOriginalName(),
            'total_rows' => count($parsedRows),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Commit confirmed valid rows to database in a single transaction.
     */
    public function commit(array $confirmedRows, string $filename, ?User $user = null): array
    {
        if (empty($confirmedRows)) {
            throw ValidationException::withMessages([
                'rows' => 'No valid rows provided to import.',
            ]);
        }

        return DB::transaction(function () use ($confirmedRows, $filename, $user) {
            $successRows = 0;
            $failedRows = 0;
            $rowErrors = [];

            // Group rows by product name to support family variants
            $groupedByName = [];
            foreach ($confirmedRows as $row) {
                $groupKey = mb_strtolower(trim($row['name']));
                $groupedByName[$groupKey][] = $row;
            }

            foreach ($groupedByName as $productGroup) {
                $firstRow = $productGroup[0];
                $hasVariants = false;
                foreach ($productGroup as $r) {
                    if (! empty($r['variation_value'])) {
                        $hasVariants = true;
                        break;
                    }
                }

                if ($hasVariants) {
                    // Check if parent product already exists
                    $existingProduct = Product::where('name', $firstRow['name'])->first();

                    if ($existingProduct && $existingProduct->has_variants) {
                        // Add new variants to existing parent
                        foreach ($productGroup as $r) {
                            try {
                                if (empty($r['variation_value'])) {
                                    continue;
                                }

                                $colorName = trim($r['variation_value']);
                                $existingVariant = $existingProduct->variants()
                                    ->where('attribute_value', $colorName)
                                    ->first();

                                if ($existingVariant) {
                                    $failedRows++;
                                    $rowErrors[] = [
                                        'row' => $r['row_number'] ?? null,
                                        'name' => "{$r['name']} ({$colorName})",
                                        'error' => "Variant '{$colorName}' already exists on product.",
                                    ];
                                    continue;
                                }

                                $sku = ! empty($r['sku'])
                                    ? trim($r['sku'])
                                    : $this->productService->generateVariantSku($existingProduct->sku, $colorName);

                                $costPrice = (float) ($r['cost_price'] ?? $existingProduct->cost_price ?? 0);
                                $sellingPrice = (float) ($r['selling_price'] ?? $existingProduct->selling_price);
                                $openingStock = (int) ($r['opening_stock'] ?? 0);
                                $minStock = (int) ($r['min_stock'] ?? $existingProduct->min_stock);

                                $variant = $existingProduct->variants()->create([
                                    'sku' => $sku,
                                    'color_name' => $colorName,
                                    'attribute_name' => $existingProduct->variation_type ?: 'Color',
                                    'attribute_value' => $colorName,
                                    'cost_price' => $costPrice,
                                    'selling_price' => $sellingPrice,
                                    'stock_qty' => $openingStock,
                                    'min_stock' => $minStock,
                                    'is_active' => true,
                                    'created_by' => $user?->id,
                                    'updated_by' => $user?->id,
                                ]);

                                if ($openingStock > 0) {
                                    \App\Models\ProductStockMovement::create([
                                        'product_id' => $existingProduct->id,
                                        'product_variant_id' => $variant->id,
                                        'movement_date' => now()->toDateString(),
                                        'type' => 'opening',
                                        'qty' => $openingStock,
                                        'balance_after' => $openingStock,
                                        'note' => 'Excel import opening stock',
                                        'created_by' => $user?->id,
                                    ]);
                                }

                                // Sync parent stock
                                $existingProduct->stock_qty = (int) $existingProduct->variants()->sum('stock_qty');
                                $existingProduct->save();

                                $successRows++;
                            } catch (\Throwable $e) {
                                $failedRows++;
                                $rowErrors[] = [
                                    'row' => $r['row_number'] ?? null,
                                    'name' => $r['name'] ?? null,
                                    'error' => $e->getMessage(),
                                ];
                            }
                        }
                    } else {
                        // Create new parent product with variants array
                        try {
                            $variantsPayload = [];
                            foreach ($productGroup as $r) {
                                $variantsPayload[] = [
                                    'color_name' => trim($r['variation_value'] ?? 'Default'),
                                    'sku' => ! empty($r['sku']) ? trim($r['sku']) : '',
                                    'cost_price' => (float) ($r['cost_price'] ?? 0),
                                    'selling_price' => (float) ($r['selling_price'] ?? 0),
                                    'opening_stock' => (int) ($r['opening_stock'] ?? 0),
                                    'min_stock' => (int) ($r['min_stock'] ?? 0),
                                    'is_active' => true,
                                ];
                            }

                            $this->productService->createProduct([
                                'name' => $firstRow['name'],
                                'sku' => '',
                                'category_name' => $firstRow['category'] ?? null,
                                'cost_price' => (float) ($firstRow['cost_price'] ?? 0),
                                'selling_price' => (float) ($firstRow['selling_price'] ?? 0),
                                'min_stock' => (int) ($firstRow['min_stock'] ?? 0),
                                'opening_stock' => 0,
                                'is_active' => true,
                                'has_variants' => true,
                                'variation_type' => $firstRow['variation_attribute'] ?: 'Color',
                                'variants' => $variantsPayload,
                            ], $user);

                            $successRows += count($productGroup);
                        } catch (\Throwable $e) {
                            $failedRows += count($productGroup);
                            foreach ($productGroup as $r) {
                                $rowErrors[] = [
                                    'row' => $r['row_number'] ?? null,
                                    'name' => $r['name'] ?? null,
                                    'error' => $e->getMessage(),
                                ];
                            }
                        }
                    }
                } else {
                    // Normal single product
                    foreach ($productGroup as $row) {
                        try {
                            $this->productService->createProduct([
                                'name' => $row['name'],
                                'sku' => $row['sku'] ?? '',
                                'category_name' => $row['category'] ?? null,
                                'cost_price' => (float) ($row['cost_price'] ?? 0.00),
                                'selling_price' => (float) ($row['selling_price'] ?? 0.00),
                                'min_stock' => (int) ($row['min_stock'] ?? 0),
                                'opening_stock' => (int) ($row['opening_stock'] ?? 0),
                                'opening_note' => 'Excel import opening stock',
                                'is_active' => true,
                                'has_variants' => false,
                            ], $user);

                            $successRows++;
                        } catch (\Throwable $e) {
                            $failedRows++;
                            $rowErrors[] = [
                                'row' => $row['row_number'] ?? null,
                                'name' => $row['name'] ?? null,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }
            }

            // Create ImportLog row
            $importLog = ImportLog::create([
                'user_id' => $user?->id,
                'type' => 'products',
                'filename' => $filename,
                'total_rows' => count($confirmedRows),
                'success_rows' => $successRows,
                'failed_rows' => $failedRows,
                'errors' => ! empty($rowErrors) ? $rowErrors : null,
            ]);

            AuditService::log('imported', 'App\Models\Product', null, [
                'import_log_id' => $importLog->id,
                'filename' => $filename,
                'total_rows' => count($confirmedRows),
                'success_rows' => $successRows,
                'failed_rows' => $failedRows,
            ], $user?->id);

            return [
                'import_log_id' => $importLog->id,
                'total' => count($confirmedRows),
                'success_count' => $successRows,
                'failed_count' => $failedRows,
                'errors' => $rowErrors,
            ];
        });
    }
}
