<?php

namespace Tests\Feature\Banking;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankAccountTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_displays_inferred_and_fallback_categories(): void
    {
        $user = User::factory()->create();
        $account = $this->createPlaidAccount($user);

        $vehicles = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Vehicles',
            'type' => 'expense',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        $car = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => $vehicles->id,
            'name' => 'Car',
            'type' => 'expense',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Other',
            'type' => 'expense',
            'icon' => 'package',
            'color' => 'slate',
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'fuel-transaction',
            'plaid_transaction_id' => 'fuel-transaction',
            'merchant_name' => 'Shell Petrol Station',
            'name' => 'Fuel purchase',
            'amount' => 72.85,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
            'category' => ['Transportation', 'Fuel'],
            'personal_finance_category' => [
                'primary' => 'TRANSPORTATION',
                'detailed' => 'TRANSPORTATION_GAS',
            ],
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'unknown-transaction',
            'plaid_transaction_id' => 'unknown-transaction',
            'merchant_name' => 'Mystery Merchant',
            'name' => 'Card payment',
            'amount' => 19.45,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
            'category' => [],
            'personal_finance_category' => [],
        ]);

        $this->actingAs($user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/show')
                ->has('transactions', 2)
                ->where('transactions', function ($transactions) use ($car): bool {
                    $transactionsByName = $transactions->keyBy('name');

                    return $transactionsByName->has('Fuel purchase')
                        && $transactionsByName->has('Card payment')
                        && $transactionsByName->get('Fuel purchase')['category'] === 'Vehicles / Car'
                        && $transactionsByName->get('Fuel purchase')['category_id'] === $car->id
                        && $transactionsByName->get('Fuel purchase')['category_source'] === 'automatic'
                        && $transactionsByName->get('Fuel purchase')['amount'] === -72.85
                        && $transactionsByName->get('Card payment')['category'] === 'Other'
                        && $transactionsByName->get('Card payment')['category_source'] === 'automatic'
                        && $transactionsByName->get('Card payment')['amount'] === -19.45;
                })
            );
    }

    public function test_user_can_update_transaction_category(): void
    {
        $user = User::factory()->create();
        $account = $this->createPlaidAccount($user);

        $groceries = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Groceries',
            'type' => 'expense',
            'icon' => 'shopping-basket',
            'color' => 'green',
        ]);

        $transaction = BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'txn-1',
            'plaid_transaction_id' => 'txn-1',
            'name' => 'Weekly shop',
            'amount' => 40.25,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->patch(route('accounts.transactions.update', [$account, $transaction]), [
                'category_id' => $groceries->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $transaction->id,
            'category_id' => $groceries->id,
            'category_manually_set' => true,
        ]);
    }

    public function test_user_can_assign_income_category_to_transaction_and_page_reflects_it(): void
    {
        $user = User::factory()->create();
        $account = $this->createPlaidAccount($user);

        $salary = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Salary',
            'type' => 'income',
            'icon' => 'briefcase',
            'color' => 'emerald',
        ]);

        $transaction = BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'txn-income-1',
            'plaid_transaction_id' => 'txn-income-1',
            'name' => 'Transfer adjustment',
            'amount' => 40.25,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->patch(route('accounts.transactions.update', [$account, $transaction]), [
                'category_id' => $salary->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $transaction->id,
            'category_id' => $salary->id,
            'category_manually_set' => true,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/show')
                ->where('transactions', function ($transactions) use ($transaction): bool {
                    $savedTransaction = collect($transactions)->firstWhere('id', $transaction->id);

                    return is_array($savedTransaction)
                        && $savedTransaction['category'] === 'Salary'
                        && $savedTransaction['category_type'] === 'income'
                        && $savedTransaction['category_source'] === 'manual';
                })
            );
    }

    public function test_user_cannot_assign_transaction_to_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = $this->createPlaidAccount($user);

        $otherUsersCategory = Category::query()->create([
            'user_id' => $otherUser->id,
            'parent_id' => null,
            'name' => 'Groceries',
            'type' => 'expense',
            'icon' => 'shopping-basket',
            'color' => 'green',
        ]);

        $transaction = BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'txn-2',
            'plaid_transaction_id' => 'txn-2',
            'name' => 'Weekly shop',
            'amount' => 40.25,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->from(route('accounts.show', $account))
            ->patch(route('accounts.transactions.update', [$account, $transaction]), [
                'category_id' => $otherUsersCategory->id,
            ])
            ->assertRedirect(route('accounts.show', $account));

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $transaction->id,
            'category_id' => null,
            'category_manually_set' => false,
        ]);
    }

    public function test_user_can_update_transaction_text_without_changing_category_assignment(): void
    {
        $user = User::factory()->create();
        $account = $this->createPlaidAccount($user);

        $transaction = BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'external_uid' => 'txn-note-1',
            'plaid_transaction_id' => 'txn-note-1',
            'merchant_name' => 'Local Cafe',
            'name' => 'Old transaction text',
            'amount' => 12.40,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-10',
            'date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->patch(route('accounts.transactions.update', [$account, $transaction]), [
                'name' => 'Morning coffee',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $transaction->id,
            'name' => 'Morning coffee',
            'category_id' => null,
            'category_manually_set' => false,
        ]);
    }

    private function createPlaidAccount(User $user): BankAccount
    {
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => 'plaid',
            'plaid_item_id_hash' => hash('sha256', 'item-'.$user->id),
            'plaid_item_id_encrypted' => 'item-'.$user->id,
            'plaid_access_token_encrypted' => 'access-'.$user->id,
            'institution_name' => 'Plaid Test Bank',
            'aspsp_name' => 'Plaid Test Bank',
            'aspsp_country' => 'US',
            'state' => 'state-'.$user->id,
            'status' => 'connected',
            'authorized_at' => now(),
        ]);

        return BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'account-'.$user->id,
            'plaid_account_id' => 'account-'.$user->id,
            'name' => 'Checking',
            'display_name' => 'Everyday Checking',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 1000, 'available' => 950],
            'is_active' => true,
        ]);
    }
}
