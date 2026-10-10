<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeatherPurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'leather_item_id',
        'purchase_date',
        'supplier',
        'qty_sqft',
        'rate_per_sqft',
        'total_cost',
        'paid_amount',
        'payment_method',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'qty_sqft' => 'decimal:3',
            'rate_per_sqft' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LeatherItem::class, 'leather_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
