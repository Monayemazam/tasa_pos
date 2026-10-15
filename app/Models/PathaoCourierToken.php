<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PathaoCourierToken extends Model
{
    use HasFactory;

    protected $table = 'pathao_courier_tokens';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_in',
        'token_type',
    ];

    protected function casts(): array
    {
        return [
            'expires_in' => 'integer',
        ];
    }

    /**
     * Check if token has expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_in || ! $this->created_at) {
            return false;
        }

        return $this->created_at->addSeconds($this->expires_in)->isPast();
    }

    /**
     * Calculate how many days remain before the token expires.
     */
    public function getDaysLeftAttribute(): int
    {
        if (! $this->expires_in || ! $this->created_at) {
            return 30;
        }

        $expiry = $this->created_at->addSeconds($this->expires_in);

        return max(0, (int) now()->diffInDays($expiry, false));
    }
}
