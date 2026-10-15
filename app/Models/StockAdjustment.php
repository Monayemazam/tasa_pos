<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StockAdjustment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'item_type',
        'item_id',
        'product_variant_id',
        'qty',
        'reason',
        'note',
        'adjusted_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'adjusted_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Stock adjustments are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Stock adjustments are append-only and cannot be deleted.');
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
