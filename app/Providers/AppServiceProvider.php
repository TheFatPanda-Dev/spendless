<?php

namespace App\Providers;

use App\Models\BankConnection;
use App\Models\User;
use App\Models\Wallet;
use App\Policies\BankConnectionPolicy;
use App\Policies\WalletPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(BankConnection::class, BankConnectionPolicy::class);
        Gate::define('admin', fn (User $user): bool => $user->is_admin === true);

        RateLimiter::for('banking-sensitive', function (Request $request): array {
            return [
                Limit::perMinute(30)->by((string) optional($request->user())->id),
            ];
        });

        $this->configureDefaults();
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
