<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'is_custom',
        'name',
        'variant_name',
        'variant_sku',
        'qty',
        'unit_price',
        'unit_cost',
        'line_total',
        'returned_qty',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'returned_qty' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
