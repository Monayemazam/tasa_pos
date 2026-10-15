<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'color_name',
        'attribute_name',
        'attribute_value',
        'cost_price',
        'selling_price',
        'stock_qty',
        'min_stock',
        'barcode',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class, 'product_variant_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'min_stock');
    }

    /**
     * Get the formatted display name (e.g. Eyewear Case Series 1 — Brown).
     */
    public function getDisplayNameAttribute(): string
    {
        $color = $this->color_name ?: $this->attribute_value;
        $parentName = $this->product?->name ?? 'Product';

        return $color ? "{$parentName} — {$color}" : $parentName;
    }
}
