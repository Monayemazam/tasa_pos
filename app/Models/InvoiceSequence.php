<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'period';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'period',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }

    /**
     * Atomically generate next invoice number in format TASA-YYMM-0001.
     */
    public static function nextInvoiceNo(?\DateTimeInterface $date = null, string $prefix = 'TASA'): string
    {
        $date = $date ?? now();
        $period = $date->format('ym'); // e.g. '2610'

        // Ensure row exists concurrency-safely
        static::insertOrIgnore([
            ['period' => $period, 'last_number' => 0],
        ]);

        // Must run inside lockForUpdate
        $sequence = static::where('period', $period)->lockForUpdate()->first();

        $nextNumber = $sequence->last_number + 1;
        $sequence->update(['last_number' => $nextNumber]);

        return sprintf('%s-%s-%04d', $prefix, $period, $nextNumber);
    }
}
