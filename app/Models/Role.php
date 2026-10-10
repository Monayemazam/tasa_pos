<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'is_locked',
        'description',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_locked' => 'boolean',
        ]);
    }
}
