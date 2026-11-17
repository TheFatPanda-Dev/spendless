<?php

namespace App\Actions\Wallets;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateManualWallet
{
    /**
     * @param  array{name: string, currency: string, starting_balance: float|null}  $attributes
     */
    public function __invoke(User $user, array $attributes): BankAccount
    {
        return DB::transaction(function () use ($user, $attributes): BankAccount {
            $wallet = Wallet::query()->create([
                'user_id' => $user->id,
                'name' => $attributes['name'],
                'type' => 'cash',
                'currency' => $attributes['currency'],
                'last_synced_at' => now(),
            ]);

            $connection = BankConnection::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'provider' => BankConnection::PROVIDER_MANUAL,
                'institution_name' => 'Manual wallet',
                'aspsp_name' => 'Manual wallet',
                'aspsp_country' => 'ZZ',
                'state' => (string) Str::uuid(),
                'status' => BankConnection::STATUS_CONNECTED,
                'authorized_at' => now(),
                'last_synced_at' => now(),
            ]);

            $startingBalance = round((float) ($attributes['starting_balance'] ?? 0), 2);

            $account = BankAccount::query()->create([
                'bank_connection_id' => $connection->id,
                'external_uid' => 'manual-account-'.Str::uuid(),
                'name' => $attributes['name'],
                'display_name' => $attributes['name'],
                'type' => 'cash',
                'subtype' => 'manual',
                'currency' => $attributes['currency'],
                'balances_encrypted' => [
                    'current' => $startingBalance,
                    'available' => $startingBalance,
                ],
                'is_active' => true,
                'last_synced_at' => now(),
            ]);

            if ($startingBalance !== 0.0) {
                $openedOn = now()->toDateString();

                BankTransaction::query()->create([
                    'bank_connection_id' => $connection->id,
                    'bank_account_id' => $account->id,
                    'external_uid' => 'manual-opening-balance-'.Str::uuid(),
                    'name' => 'Opening balance',
                    'amount' => $startingBalance,
                    'currency' => $attributes['currency'],
                    'date' => $openedOn,
                    'pending' => false,
                    'description' => 'Opening balance',
                ]);
            }

            return $account;
        });
    }
}
