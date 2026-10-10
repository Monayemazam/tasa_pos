<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ProductStockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'balance_after',
        'reference_type',
        'reference_id',
        'movement_date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'balance_after' => 'integer',
            'movement_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Product stock movements are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Product stock movements are append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
