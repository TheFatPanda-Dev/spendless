<?php

namespace App\Http\Middleware;

use App\Models\BankConnection;
use App\Support\Localization\NumberLocaleOptions;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'support_email' => config('mail.from.address'),
            'auth' => [
                'user' => $request->user(),
            ],
            'number_locale' => NumberLocaleOptions::normalize((string) ($request->user()?->preferred_number_locale ?: 'en-GB')),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'oauthPrompt' => fn () => $request->session()->get('oauth_prompt'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Offer "Connect a UK bank" only when Plaid credentials are set.
            'plaid_enabled' => filled(config('services.plaid.client_id')) && filled(config('services.plaid.secret')),
            // Banks whose access expired or is about to: shown as a "Reconnect" banner.
            'bankAlerts' => fn (): array => $request->user() === null ? [] : BankConnection::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('provider', [BankConnection::PROVIDER_ENABLE_BANKING, BankConnection::PROVIDER_PLAID])
                ->where(fn ($query) => $query
                    ->where('status', BankConnection::STATUS_NEEDS_REAUTH)
                    ->orWhere(fn ($query) => $query
                        ->where('status', '!=', BankConnection::STATUS_PENDING)
                        ->where('consent_valid_until', '<', now()->addDays(BankConnection::CONSENT_WARNING_DAYS))))
                ->get()
                ->map(fn (BankConnection $connection): array => $connection->toStatusArray())
                ->all(),
        ];
    }
}
