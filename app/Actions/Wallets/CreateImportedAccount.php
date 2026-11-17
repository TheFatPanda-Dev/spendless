<?php

namespace App\Actions\Wallets;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A wallet + account kept up to date by importing statement files.
 */
class CreateImportedAccount
{
    public const KINDS = ['current', 'savings', 'credit_card'];

    /**
     * @param  array{name: string, currency: string, kind: string, institution: string|null}  $attributes
     */
    public function __invoke(User $user, array $attributes): BankAccount
    {
        return DB::transaction(function () use ($user, $attributes): BankAccount {
            $institution = $attributes['institution'] ?: $attributes['name'];

            $wallet = Wallet::query()->create([
                'user_id' => $user->id,
                'name' => $attributes['name'],
                'type' => 'bank',
                'currency' => $attributes['currency'],
            ]);

            $connection = BankConnection::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'provider' => BankConnection::PROVIDER_IMPORT,
                'institution_name' => $institution,
                'aspsp_name' => $institution,
                'aspsp_country' => 'ZZ',
                'state' => (string) Str::uuid(),
                'status' => BankConnection::STATUS_CONNECTED,
                'authorized_at' => now(),
            ]);

            return BankAccount::query()->create([
                'bank_connection_id' => $connection->id,
                'external_uid' => 'import-account-'.Str::uuid(),
                'name' => $attributes['name'],
                'display_name' => $attributes['name'],
                'type' => $attributes['kind'] === 'credit_card' ? 'credit' : 'bank',
                'subtype' => $attributes['kind'],
                'currency' => $attributes['currency'],
                'balances_encrypted' => ['current' => 0, 'available' => 0],
                'is_active' => true,
            ]);
        });
    }
}
