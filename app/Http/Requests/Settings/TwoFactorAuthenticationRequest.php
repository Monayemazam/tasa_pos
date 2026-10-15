<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * Determine if two-factor authentication can be managed.
     */
    public function canManageTwoFactor(): bool
    {
        return Features::canManageTwoFactorAuthentication();
    }

    /**
     * Determine if the user has two-factor authentication enabled.
     */
    public function hasEnabledTwoFactor(): bool
    {
        return ! is_null($this->user()?->two_factor_secret) &&
            (! Fortify::confirmsTwoFactorAuthentication() || ! is_null($this->user()?->two_factor_confirmed_at));
    }

    /**
     * Determine if two-factor authentication requires confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return Fortify::confirmsTwoFactorAuthentication() &&
            is_null($this->user()?->two_factor_confirmed_at);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
