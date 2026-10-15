<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Policies\EmployeePolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\RolePolicy;
use App\Policies\SalaryMonthPolicy;
use App\Policies\SalaryPaymentPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkerEarningPolicy;
use App\Policies\WorkerPaymentPolicy;
use App\Policies\WorkerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(SalaryMonth::class, SalaryMonthPolicy::class);
        Gate::policy(SalaryPayment::class, SalaryPaymentPolicy::class);
        Gate::policy(Worker::class, WorkerPolicy::class);
        Gate::policy(WorkerEarning::class, WorkerEarningPolicy::class);
        Gate::policy(WorkerPayment::class, WorkerPaymentPolicy::class);

        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($user instanceof User && $user->hasRole('Owner')) {
                // If it is a mutation on a protected Role, User, Expense, ExpenseCategory, SalaryMonth, SalaryPayment, WorkerEarning or WorkerPayment, defer to explicit policy
                $target = $arguments[0] ?? null;
                if (($target instanceof Role || $target instanceof User || $target instanceof Expense || $target instanceof ExpenseCategory || $target instanceof SalaryMonth || $target instanceof SalaryPayment || $target instanceof WorkerEarning || $target instanceof WorkerPayment)
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
