<?php

namespace App\Support\Admin;

use App\Models\AppSetting;
use App\Models\BankConnection;
use App\Services\EnableBanking\EnableBankingClient;
use App\Services\EnableBanking\EnableBankingException;
use App\Services\Plaid\PlaidUsage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The "did I set it up right?" list on the admin page. Each check is green (ok),
 * amber (warn: works, but worth fixing) or red (fail: something is broken).
 */
class SetupChecks
{
    public function __construct(private readonly EnableBankingClient $client) {}

    /**
     * Checks that only read local configuration (fast).
     *
     * @return list<array{key: string, label: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    public function local(Request $request): array
    {
        $appUrl = (string) config('app.url');
        $appUrlHost = (string) parse_url($appUrl, PHP_URL_HOST);
        $isProduction = app()->isProduction();

        return [
            $this->check('environment', 'Environment',
                $isProduction ? 'ok' : 'warn',
                $isProduction ? 'APP_ENV=production' : sprintf('APP_ENV=%s. Use "production" on the live server.', app()->environment())),

            $this->check('debug', 'Debug mode',
                config('app.debug') ? ($isProduction ? 'fail' : 'warn') : 'ok',
                config('app.debug') ? 'APP_DEBUG=true shows error details to visitors. Set APP_DEBUG=false.' : 'Off'),

            $this->check('https', 'HTTPS address',
                str_starts_with($appUrl, 'https://') ? 'ok' : 'warn',
                str_starts_with($appUrl, 'https://') ? $appUrl : sprintf('APP_URL is %s. Banks require an https:// address for the return link.', $appUrl ?: 'empty')),

            $this->check('app-url', 'APP_URL matches this site',
                $appUrlHost === '' || $appUrlHost === $request->getHost() ? 'ok' : 'warn',
                $appUrlHost === '' || $appUrlHost === $request->getHost()
                    ? 'Return link: '.route('banking.callback')
                    : sprintf('APP_URL points to %s but you are on %s. The bank return link is built from APP_URL.', $appUrlHost, $request->getHost())),

            $this->schedulerCheck(),
            $this->queueCheck(),

            $this->check('mail', 'Email sending',
                in_array(config('mail.default'), ['log', 'array'], true) ? 'warn' : 'ok',
                in_array(config('mail.default'), ['log', 'array'], true)
                    ? sprintf('MAIL_MAILER=%s, so emails (password resets, bank reminders) are not sent. Configure SMTP.', config('mail.default'))
                    : sprintf('%s, from %s', config('mail.default'), config('mail.from.address'))),

            $this->check('registration', 'New sign-ups',
                AppSetting::registrationOpen() ? 'warn' : 'ok',
                AppSetting::registrationOpen()
                    ? 'Anyone can create an account. Free Enable Banking only works for accounts you linked yourself, so consider closing sign-ups below.'
                    : 'Closed. Only existing users can sign in.'),

            $this->connectionsCheck(),
            ...$this->plaidChecks(),

            $this->check('php', 'PHP version',
                version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail',
                PHP_VERSION),
        ];
    }

    /**
     * Checks that call Enable Banking (slower; loaded after the page).
     *
     * @return list<array{key: string, label: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    public function enableBanking(): array
    {
        if (! $this->client->isConfigured()) {
            return [$this->check('eb-credentials', 'Enable Banking credentials', 'fail',
                'ENABLE_BANKING_APP_ID and ENABLE_BANKING_PRIVATE_KEY_PATH are not set in .env.')];
        }

        try {
            $application = $this->client->getApplication();
        } catch (EnableBankingException $exception) {
            return [$this->check('eb-credentials', 'Enable Banking credentials', 'fail',
                'Enable Banking rejected the request: '.$exception->getMessage().'. Check the App ID and that the key file is the one downloaded for this application.')];
        }

        $environment = strtoupper((string) ($application['environment'] ?? 'UNKNOWN'));
        $redirectUrls = array_map(fn (mixed $url): string => rtrim((string) $url, '/'), (array) ($application['redirect_urls'] ?? []));
        $callbackUrl = rtrim(route('banking.callback'), '/');
        $countries = array_map('strtoupper', (array) ($application['countries'] ?? []));
        $missingCountries = $countries === []
            ? []
            : array_values(array_diff((array) config('services.enable_banking.countries', []), $countries));

        return [
            $this->check('eb-credentials', 'Enable Banking credentials', 'ok',
                sprintf('Application "%s" (%s)', $application['name'] ?? 'unnamed', config('services.enable_banking.app_id'))),

            $this->check('eb-environment', 'Enable Banking environment',
                $environment === 'PRODUCTION' ? 'ok' : 'warn',
                $environment === 'PRODUCTION'
                    ? 'Production: real banks.'
                    : sprintf('%s: only Enable Banking\'s test banks work. Create a production application to connect your real accounts.', $environment)),

            $this->check('eb-active', 'Enable Banking application active',
                ($application['active'] ?? false) ? 'ok' : 'fail',
                ($application['active'] ?? false)
                    ? 'Active'
                    : 'Not active yet. In the Enable Banking Control Panel, link your bank accounts to activate the application (free restricted mode).'),

            $this->check('eb-redirect', 'Bank return link registered',
                in_array($callbackUrl, $redirectUrls, true) ? 'ok' : 'fail',
                in_array($callbackUrl, $redirectUrls, true)
                    ? $callbackUrl
                    : sprintf('Add %s to the application\'s redirect URLs in the Enable Banking Control Panel. Registered now: %s', $callbackUrl, $redirectUrls === [] ? 'none' : implode(', ', $redirectUrls))),

            $this->check('eb-countries', 'Countries enabled',
                $missingCountries === [] ? 'ok' : 'warn',
                $missingCountries === []
                    ? implode(', ', (array) config('services.enable_banking.countries', []))
                    : sprintf('The application is not enabled for %s.', implode(', ', $missingCountries))),
        ];
    }

    private function schedulerCheck(): array
    {
        $lastRun = Cache::get('scheduler:last_run_at');

        try {
            $lastRunAt = is_string($lastRun) ? CarbonImmutable::parse($lastRun) : null;
        } catch (Throwable) {
            $lastRunAt = null;
        }

        // Under the web server PHP_BINARY is php-fpm / lsphp, which cron can't use.
        $cliBinary = PHP_SAPI === 'cli' && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cronLine = sprintf('* * * * * cd %s && %s artisan schedule:run >> /dev/null 2>&1', base_path(), $cliBinary)
            .($cliBinary === 'php' ? ' (if "php" is too old, use the full path shown by "which php" over SSH)' : '');

        if ($lastRunAt === null) {
            return $this->check('cron', 'Cron job (background syncing)', 'fail',
                'The scheduler has never run, so banks never sync in the background. Add this cron job in cPanel: '.$cronLine);
        }

        $minutesAgo = (int) $lastRunAt->diffInMinutes(now());

        return $this->check('cron', 'Cron job (background syncing)',
            $minutesAgo <= 3 ? 'ok' : 'fail',
            $minutesAgo <= 3
                ? sprintf('Last run %s', $lastRunAt->diffForHumans())
                : sprintf('Last run %s. The cron job has stopped. Expected: %s', $lastRunAt->diffForHumans(), $cronLine));
    }

    private function queueCheck(): array
    {
        try {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(5)->getTimestamp())->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return $this->check('queue', 'Background jobs', 'warn', 'Could not read the jobs tables.');
        }

        return $this->check('queue', 'Background jobs',
            $stuck > 0 ? 'fail' : ($failed > 0 ? 'warn' : 'ok'),
            match (true) {
                $stuck > 0 => sprintf('%d job(s) waiting longer than 5 minutes. The cron job is probably not running.', $stuck),
                $failed > 0 => sprintf('%d failed job(s), see below.', $failed),
                default => 'Nothing waiting, nothing failed.',
            });
    }

    private function connectionsCheck(): array
    {
        $counts = BankConnection::query()
            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $needsReauth = (int) ($counts[BankConnection::STATUS_NEEDS_REAUTH] ?? 0);
        $failing = (int) ($counts[BankConnection::STATUS_SYNC_FAILED] ?? 0);
        $total = (int) $counts->except(BankConnection::STATUS_PENDING)->sum();

        return $this->check('connections', 'Bank connections',
            $needsReauth + $failing > 0 ? 'warn' : 'ok',
            $total === 0
                ? 'No banks connected yet.'
                : sprintf('%d connected, %d need reconnecting, %d failing.', $total - $needsReauth - $failing, $needsReauth, $failing));
    }

    /**
     * UK banks through Plaid: only shown once Plaid credentials are set. No API call here,
     * every call counts against the free allowance.
     *
     * @return list<array{key: string, label: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    private function plaidChecks(): array
    {
        if (blank(config('services.plaid.client_id')) && blank(config('services.plaid.secret'))) {
            return [];
        }

        $configured = filled(config('services.plaid.client_id')) && filled(config('services.plaid.secret'));
        $environment = config('services.plaid.environment') === 'sandbox' ? 'sandbox' : 'production';
        $redirect = (string) config('services.plaid.redirect_uri');
        $used = PlaidUsage::used();
        $limit = PlaidUsage::limit();

        return [
            $this->check('plaid', 'Plaid (UK banks)',
                ! $configured ? 'fail' : ($environment === 'sandbox' && app()->isProduction() ? 'warn' : 'ok'),
                ! $configured
                    ? 'Set both PLAID_CLIENT_ID and PLAID_SECRET.'
                    : sprintf('%s. Add this to Allowed redirect URIs in the Plaid Dashboard: %s', ucfirst($environment), $redirect)),

            $this->check('plaid-usage', 'Plaid calls',
                $limit === 0 ? 'ok' : (PlaidUsage::exhausted() ? 'fail' : ($used >= $limit * 0.8 ? 'warn' : 'ok')),
                $limit === 0
                    ? sprintf('%d transaction sync calls so far (no limit set).', $used)
                    : sprintf('%d of %d free calls used, as counted by SpendLess (the Plaid Dashboard has the official number). UK banks sync once a day.', $used, $limit)),
        ];
    }

    /**
     * @param  'ok'|'warn'|'fail'  $status
     * @return array{key: string, label: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }
}
