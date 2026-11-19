<?php

namespace App\Console\Commands;

use App\Models\BankConnection;
use App\Support\Banking\InstitutionLogos;
use Illuminate\Console\Command;

class FetchInstitutionLogos extends Command
{
    protected $signature = 'banking:fetch-logos {--force : Fetch again even when a logo is stored or recently failed}';

    protected $description = 'Store logos for wallets whose provider sends none (imports, IBKR, ISA, pension)';

    public function handle(): int
    {
        $domains = BankConnection::query()
            ->whereNull('institution_logo')
            ->where('status', '!=', BankConnection::STATUS_PENDING)
            ->get(['institution_name', 'provider'])
            ->map(fn (BankConnection $connection): ?string => InstitutionLogos::domainFor($connection->institution_name, $connection->provider))
            ->filter()
            ->unique()
            ->values();

        foreach ($domains as $domain) {
            if (! $this->option('force') && (InstitutionLogos::has($domain) || InstitutionLogos::recentlyMissed($domain))) {
                continue;
            }

            $this->line(sprintf('%s: %s', $domain, InstitutionLogos::fetch($domain) ? 'stored' : 'no usable icon'));
        }

        return self::SUCCESS;
    }
}
