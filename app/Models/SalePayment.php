<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SalePayment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'sale_id',
        'paid_on',
        'amount',
        'method',
        'reference',
        'note',
        'courier_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Sale payments are append-only and cannot be updated.');
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function courierOrder(): BelongsTo
    {
        return $this->belongsTo(CourierOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
