<?php

namespace Tests\Feature\Wallets;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualWalletsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_manual_wallet_from_cash_wallet_flow(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('wallets.manual.store'), [
                'name' => 'Pocket Cash',
                'currency' => 'GBP',
                'starting_balance' => '250.75',
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $account = BankAccount::query()
            ->where('display_name', 'Pocket Cash')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('manual', $account->connection?->provider);
        $this->assertSame('cash', $account->connection?->wallet?->type);
        $this->assertSame('GBP', $account->currency_code);
        $this->assertSame(250.75, (float) ($account->balances_encrypted['current'] ?? 0));
    }

    public function test_manual_wallet_is_visible_on_dashboard_and_wallet_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('wallets.manual.store'), [
                'name' => 'Travel Cash',
                'currency' => 'EUR',
            ])
            ->assertRedirect(route('dashboard'));

        $account = BankAccount::query()
            ->where('display_name', 'Travel Cash')
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('accounts.0.id', $account->id)
                ->where('accounts.0.display_name', 'Travel Cash')
                ->where('accounts.0.current_balance', 0));

        $this->actingAs($user)
            ->get('/settings/wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/wallets')
                ->where('accounts.0.id', $account->id)
                ->where('accounts.0.wallet_name', 'Travel Cash')
                ->where('accounts.0.institution_name', 'Manual wallet'));
    }

    public function test_user_can_add_manual_transaction_to_manual_wallet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('wallets.manual.store'), [
                'name' => 'Daily Cash',
                'currency' => 'EUR',
                'starting_balance' => '100.00',
            ]);

        $account = BankAccount::query()
            ->where('display_name', 'Daily Cash')
            ->firstOrFail();

        $expenseCategory = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Groceries',
            'type' => 'expense',
            'icon' => 'shopping-basket',
            'color' => 'green',
        ]);

        $this->actingAs($user)
            ->from(route('accounts.show', [
                'bankAccount' => $account,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]))
            ->post(route('accounts.transactions.manual.store', [
                'bankAccount' => $account,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]), [
                'category_id' => $expenseCategory->id,
                'date' => '2026-03-13',
                'merchant_name' => 'Market Stall',
                'transaction_name' => 'Fruit and veg',
                'amount' => '12.50',
            ])
            ->assertRedirect(route('accounts.show', [
                'bankAccount' => $account,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]));

        $transaction = $account->transactions()->latest('id')->first();

        $this->assertNotNull($transaction);
        $this->assertSame(-12.5, (float) $transaction->amount);
        $this->assertSame($expenseCategory->id, $transaction->category_id);

        $account->refresh();

        $this->assertSame(87.5, (float) ($account->balances_encrypted['current'] ?? 0));

        $this->actingAs($user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/show')
                ->where('account.is_manual', true)
                ->where('transactions.0.name', 'Fruit and veg')
                ->where('transactions.0.category', 'Groceries')
                ->where('transactions.0.amount', -12.5));
    }
}
