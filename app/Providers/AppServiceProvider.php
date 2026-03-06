<?php

namespace App\Providers;

use App\Models\BankConnection;
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

        RateLimiter::for('banking-sensitive', function (Request $request): array {
            return [
                Limit::perMinute(30)->by((string) optional($request->user())->id),
            ];
        });

        RateLimiter::for('plaid-webhooks', function (Request $request): array {
            return [
                Limit::perMinute(120)->by((string) $request->ip()),
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
