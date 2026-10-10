<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourierOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'merchant_order_id',
        'consignment_id',
        'store_id',
        'delivery_type',
        'item_type',
        'item_quantity',
        'item_weight',
        'item_description',
        'special_instruction',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_city',
        'recipient_zone',
        'recipient_area',
        'cod_amount',
        'courier_charge',
        'status',
        'raw_status',
        'attempts',
        'last_error',
        'last_synced_at',
        'delivered_at',
        'cod_settled_at',
        'cod_settled_amount',
        'cod_settled_by',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'delivery_type' => 'integer',
            'item_type' => 'integer',
            'item_quantity' => 'integer',
            'item_weight' => 'decimal:2',
            'recipient_city' => 'integer',
            'recipient_zone' => 'integer',
            'recipient_area' => 'integer',
            'cod_amount' => 'decimal:2',
            'courier_charge' => 'decimal:2',
            'attempts' => 'integer',
            'last_synced_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cod_settled_at' => 'datetime',
            'cod_settled_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CourierStatusLog::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cod_settled_by');
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    public function scopeCodReceivable(Builder $query): Builder
    {
        return $query->where('cod_amount', '>', 0)
            ->where('status', 'delivered')
            ->whereNull('cod_settled_at');
    }
}
