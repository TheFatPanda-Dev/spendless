<?php

namespace App\Services\Assets;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\AssetValuation;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cash ISAs and pensions: accounts whose balance comes from terms or values the user enters.
 */
class AssetAccounts
{
    public const KIND_CASH_ISA = 'cash_isa';

    public const KIND_PENSION = 'pension';

    public function __construct(private readonly ResolveBankTransactionCategory $resolveCategory) {}

    /**
     * @param  array{kind: string, name: string, institution: string|null, currency: string, amount?: float, rate?: float, start_date?: string, years?: float, value?: float, date?: string}  $attributes
     */
    public function create(User $user, array $attributes): BankAccount
    {
        return DB::transaction(function () use ($user, $attributes): BankAccount {
            $isIsa = $attributes['kind'] === self::KIND_CASH_ISA;

            $wallet = Wallet::query()->create([
                'user_id' => $user->id,
                'name' => $attributes['name'],
                'type' => $isIsa ? 'savings' : 'pension',
                'currency' => $attributes['currency'],
            ]);

            $connection = BankConnection::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'provider' => BankConnection::PROVIDER_ASSET,
                'institution_name' => $attributes['institution'] ?: $attributes['name'],
                'aspsp_name' => $attributes['institution'] ?: $attributes['name'],
                'aspsp_country' => 'ZZ',
                'state' => (string) Str::uuid(),
                'status' => BankConnection::STATUS_CONNECTED,
                'authorized_at' => now(),
                'metadata' => $isIsa ? ['terms' => $this->terms($attributes)] : [],
            ]);

            $account = BankAccount::query()->create([
                'bank_connection_id' => $connection->id,
                'external_uid' => 'asset-'.Str::uuid(),
                'name' => $attributes['name'],
                'display_name' => $attributes['name'],
                'type' => $isIsa ? 'savings' : 'pension',
                'subtype' => $attributes['kind'],
                'currency' => $attributes['currency'],
                'balances_encrypted' => ['current' => 0, 'available' => 0],
                'is_active' => true,
            ]);

            $account->setRelation('connection', $connection);

            if ($isIsa) {
                $this->refreshIsa($account);
            } else {
                $this->recordValuation($account, (float) $attributes['value'], (string) $attributes['date']);
            }

            return $account;
        });
    }

    /**
     * @param  array{amount: float, rate: float, start_date: string, years: float}  $attributes
     */
    public function updateIsaTerms(BankAccount $account, array $attributes): void
    {
        $connection = $account->connection;
        $connection->forceFill(['metadata' => [...($connection->metadata ?? []), 'terms' => $this->terms($attributes)]])->save();

        $this->refreshIsa($account);
    }

    /**
     * Today's value = amount + profit earned so far. When the term ends, the profit is
     * recorded once as income on the maturity date.
     */
    public function refreshIsa(BankAccount $account): void
    {
        $savings = FixedTermSavings::fromTerms($account->connection?->metadata['terms'] ?? []);

        if ($savings === null) {
            return;
        }

        $value = $savings->valueOn();

        $account->forceFill([
            'balances_encrypted' => ['current' => $value, 'available' => $value, 'as_of' => now()->toDateString()],
            'last_synced_at' => now(),
        ])->save();
        $this->touchUpdated($account);

        $profitUid = 'isa-profit:'.$savings->maturityDate()->toDateString();

        // Terms edited after maturity: drop a profit entry that no longer matches.
        $account->transactions()
            ->where('external_uid', 'like', 'isa-profit:%')
            ->where('external_uid', '!=', $profitUid)
            ->delete();

        if (! $savings->hasMatured() || $savings->totalProfit() <= 0) {
            $account->transactions()->where('external_uid', $profitUid)->delete();

            return;
        }

        $existing = $account->transactions()->where('external_uid', $profitUid)->first();
        $attributes = [
            'bank_connection_id' => $account->bank_connection_id,
            'name' => 'Cash ISA profit (interest)',
            'merchant_name' => $account->connection?->institution_name,
            'amount' => $savings->totalProfit(),
            'currency' => $account->currency,
            'date' => $savings->maturityDate()->toDateString(),
            'pending' => false,
            'description' => sprintf('%s%% for %s year(s) on %s', rtrim(rtrim(number_format($savings->ratePercent, 3, '.', ''), '0'), '.'), rtrim(rtrim(number_format($savings->years, 2, '.', ''), '0'), '.'), number_format($savings->amount, 2, '.', '')),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return;
        }

        $transaction = new BankTransaction([...$attributes, 'external_uid' => $profitUid]);
        $transaction->bank_account_id = $account->id;

        if ($account->connection?->user !== null) {
            $transaction->category_id = ($this->resolveCategory)($transaction, $account->connection->user)?->id;
        }

        $transaction->save();
    }

    /**
     * Records the value on a date; the most recent value becomes the balance.
     */
    public function recordValuation(BankAccount $account, float $value, string $date): AssetValuation
    {
        $valuation = AssetValuation::query()->updateOrCreate(
            ['bank_account_id' => $account->id, 'date' => CarbonImmutable::parse($date)->toDateString()],
            ['value' => round($value, 2)],
        );

        $this->syncBalanceFromValuations($account);

        return $valuation;
    }

    public function syncBalanceFromValuations(BankAccount $account): void
    {
        $latest = $account->valuations()->orderByDesc('date')->first();
        $value = $latest?->value ?? 0.0;

        $account->forceFill([
            'balances_encrypted' => ['current' => $value, 'available' => $value, 'as_of' => $latest?->date?->toDateString()],
            'last_synced_at' => now(),
        ])->save();
        $this->touchUpdated($account);
    }

    private function touchUpdated(BankAccount $account): void
    {
        $account->connection?->forceFill(['last_synced_at' => now()])->save();
        $account->connection?->wallet?->forceFill(['last_synced_at' => now()])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{amount: float, rate: float, start_date: string, years: float}
     */
    private function terms(array $attributes): array
    {
        return [
            'amount' => round((float) $attributes['amount'], 2),
            'rate' => round((float) $attributes['rate'], 4),
            'start_date' => CarbonImmutable::parse((string) $attributes['start_date'])->toDateString(),
            'years' => round((float) $attributes['years'], 2),
        ];
    }
}
