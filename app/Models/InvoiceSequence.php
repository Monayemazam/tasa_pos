<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        // Must run inside lockForUpdate
        $sequence = static::where('period', $period)->lockForUpdate()->first();

        if (!$sequence) {
            $sequence = static::create([
                'period' => $period,
                'last_number' => 0,
            ]);
            // Re-fetch with lock to be completely safe against concurrent creates
            $sequence = static::where('period', $period)->lockForUpdate()->first();
        }

        $nextNumber = $sequence->last_number + 1;
        $sequence->update(['last_number' => $nextNumber]);

        return sprintf('%s-%s-%04d', $prefix, $period, $nextNumber);
    }
}
