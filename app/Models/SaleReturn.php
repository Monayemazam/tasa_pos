<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'returned_on',
        'reason',
        'return_value',
        'refunded_amount',
        'restock',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'returned_on' => 'date',
            'return_value' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
