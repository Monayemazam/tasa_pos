<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierStatusLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'courier_order_id',
        'status',
        'raw_status',
        'payload',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function courierOrder(): BelongsTo
    {
        return $this->belongsTo(CourierOrder::class);
    }
}
