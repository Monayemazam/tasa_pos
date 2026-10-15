<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'alt_phone',
        'address',
        'pathao_city_id',
        'pathao_zone_id',
        'pathao_area_id',
        'city_name',
        'zone_name',
        'area_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pathao_city_id' => 'integer',
            'pathao_zone_id' => 'integer',
            'pathao_area_id' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getTotalOrdersAttribute(): int
    {
        if (array_key_exists('sales_count', $this->attributes)) {
            return (int) $this->attributes['sales_count'];
        }

        return (int) $this->sales()->where('status', '!=', 'cancelled')->count();
    }

    public function getTotalSpentAttribute(): float
    {
        if (array_key_exists('total_spent_amount', $this->attributes)) {
            return (float) $this->attributes['total_spent_amount'];
        }

        return (float) $this->sales()->where('status', '!=', 'cancelled')->sum('grand_total');
    }

    public function getOutstandingDueAttribute(): float
    {
        if (array_key_exists('outstanding_due_amount', $this->attributes)) {
            return (float) $this->attributes['outstanding_due_amount'];
        }

        $activeSales = $this->sales()
            ->where('status', '!=', 'cancelled')
            ->with('returns')
            ->get();

        return round($activeSales->sum(fn ($sale) => (float) $sale->net_due_amount), 2);
    }
}
