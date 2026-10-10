<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    /**
     * Determine whether the user can view settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    /**
     * Determine whether the user can update settings.
     */
    public function update(User $user): bool
    {
        return $user->can('settings.manage');
    }
}
