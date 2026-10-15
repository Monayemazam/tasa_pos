<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Enforce exactly one role per user in application logic.
     */
    public function assignSingleRole(string|Role $role): static
    {
        $this->syncRoles([$role]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke permission from the user and their assigned roles, and forget permission cache.
     */
    public function revokePermissionTo($permission): static
    {
        $permObj = $this->getStoredPermission($permission);

        foreach ($this->roles as $role) {
            if ($role->hasPermissionTo($permObj)) {
                $role->revokePermissionTo($permObj);
            }
        }

        $this->permissions()->detach($permObj);
        $this->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->load('roles', 'permissions');

        return $this;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
