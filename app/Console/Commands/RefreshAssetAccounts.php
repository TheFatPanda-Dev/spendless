<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Services\Assets\AssetAccounts;
use Illuminate\Console\Command;

class RefreshAssetAccounts extends Command
{
    protected $signature = 'assets:refresh';

    protected $description = 'Update Cash ISA values (profit earned so far, profit paid at maturity)';

    public function handle(AssetAccounts $assets): int
    {
        $count = 0;

        BankAccount::query()
            ->where('subtype', AssetAccounts::KIND_CASH_ISA)
            ->whereHas('connection', fn ($query) => $query->where('provider', BankConnection::PROVIDER_ASSET))
            ->with('connection.user')
            ->each(function (BankAccount $account) use ($assets, &$count): void {
                $assets->refreshIsa($account);
                $count++;
            });

        $this->info(sprintf('Refreshed %d Cash ISA account(s).', $count));

        return self::SUCCESS;
    }
}
