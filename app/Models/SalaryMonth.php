<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryMonth extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period',
        'base_salary',
        'bonus',
        'deduction',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'base_salary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deduction' => 'decimal:2',
            'payable' => 'decimal:2',
        ];
    }

    /**
     * Get computed payable attribute as float.
     */
    public function getPayableAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        $base = (float) ($this->base_salary ?? 0);
        $bonus = (float) ($this->bonus ?? 0);
        $deduction = (float) ($this->deduction ?? 0);

        return round($base + $bonus - $deduction, 2);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
