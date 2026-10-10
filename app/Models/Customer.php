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
}
