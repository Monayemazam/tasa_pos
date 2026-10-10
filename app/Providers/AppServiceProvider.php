<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure authorization gates and locked role protection.
     */
    protected function configureAuthorization(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Role::class, \App\Policies\RolePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Setting::class, \App\Policies\SettingPolicy::class);

        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($user instanceof \App\Models\User && $user->hasRole('Owner')) {
                // If it is a mutation on a protected Role or User model, defer to explicit policy
                $target = $arguments[0] ?? null;
                if (($target instanceof \App\Models\Role || $target instanceof \App\Models\User)
                    && in_array($ability, ['update', 'delete', 'deactivate'])) {
                    return null;
                }
                return true;
            }
            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
