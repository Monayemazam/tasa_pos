<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'designation',
        'monthly_salary',
        'joined_at',
        'left_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'joined_at' => 'date',
            'left_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function salaryMonths(): HasMany
    {
        return $this->hasMany(SalaryMonth::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
