<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    public const PL_GROUPS = [
        'marketing_facebook' => 'Facebook Ads',
        'marketing_other' => 'Other Marketing',
        'courier' => 'Courier Charges',
        'packaging' => 'Packaging',
        'rent_utilities' => 'Rent & Utilities',
        'other' => 'Other Expenses',
    ];

    protected $fillable = [
        'name',
        'pl_group',
        'is_system',
        'is_active',
    ];

    protected $appends = [
        'pl_group_label',
    ];

    public function getPlGroupLabelAttribute(): string
    {
        return self::PL_GROUPS[$this->pl_group] ?? ucfirst(str_replace('_', ' ', $this->pl_group));
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }
}
