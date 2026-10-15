<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSensitiveFinancials
{
    /**
     * Forbidden financial keys if user lacks inventory.view_costs.
     */
    protected array $costKeys = [
        'cost_price',
        'unit_cost',
        'rate',
        'purchase_rate',
        'rate_per_sqft',
        'total_cost',
        'avg_cost',
        'weighted_average_cost',
        'stock_value',
        'period_purchased_cost',
        'total_stock_value',
    ];

    /**
     * Forbidden profit keys if user lacks reports.sales_profit.
     */
    protected array $profitKeys = [
        'profit',
        'gross_profit',
        'net_profit',
        'profit_margin',
        'margin',
        'cogs',
        'total_cogs',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        // Owner always has access to all financial fields
        if ($user->hasRole('Owner')) {
            return $response;
        }

        $canViewCosts = $user->can('inventory.view_costs') || $user->can('inventory.cost.view');
        $canViewProfit = $user->can('reports.sales_profit');

        if ($canViewCosts && $canViewProfit) {
            return $response;
        }

        $keysToRemove = [];
        if (! $canViewCosts) {
            $keysToRemove = array_merge($keysToRemove, $this->costKeys);
        }
        if (! $canViewProfit) {
            $keysToRemove = array_merge($keysToRemove, $this->profitKeys);
        }

        if (empty($keysToRemove)) {
            return $response;
        }

        // Handle JsonResponse (API endpoints, JSON exports)
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $sanitized = $this->stripKeysRecursively($data, $keysToRemove);
                $response->setData($sanitized);
            }

            return $response;
        }

        // Handle Inertia responses
        if ($request->header('X-Inertia') && $response->isOk()) {
            $content = $response->getContent();
            $data = json_decode($content, true);
            if (is_array($data)) {
                $sanitized = $this->stripKeysRecursively($data, $keysToRemove);
                $response->setContent(json_encode($sanitized));
            }

            return $response;
        }

        return $response;
    }

    /**
     * Recursively strip blacklisted keys from data array.
     */
    protected function stripKeysRecursively(array $data, array $keysToRemove): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $keysToRemove, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->stripKeysRecursively($value, $keysToRemove);
            }
        }

        return $data;
    }
}
