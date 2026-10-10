<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeatherItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'colour_grade',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(LeatherPurchase::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(LeatherConsumption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
