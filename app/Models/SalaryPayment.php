<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period',
        'paid_on',
        'amount',
        'type',
        'method',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
