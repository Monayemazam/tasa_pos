<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => (bool) $user->is_active,
                    'role' => $user->roles->first()?->name ?? 'Staff',
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'courier_warning' => $user ? app(\App\Services\Courier\PathaoService::class)->getCourierWarning() : null,
            'shop' => [
                'name' => \App\Models\Setting::get('general.shop_name', 'Tasa Leather Goods'),
                'logo' => \App\Models\Setting::get('general.logo', '/storage/branding/tasa-logo.png'),
                'phone' => \App\Models\Setting::get('general.phone', ''),
                'address' => \App\Models\Setting::get('general.address', ''),
                'currency_symbol' => \App\Models\Setting::get('general.currency_symbol', '৳'),
            ],
        ];
    }
}
