<?php

namespace Tests\Feature\Settings;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WalletSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_settings_page_is_displayed_with_users_accounts_only(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerWallet = Wallet::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $otherWallet = Wallet::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $ownerConnection = $this->createPlaidConnection($owner, $ownerWallet, 'owner-item');
        $otherConnection = $this->createPlaidConnection($otherUser, $otherWallet, 'other-item');

        $ownerAccount = BankAccount::query()->create([
            'bank_connection_id' => $ownerConnection->id,
            'external_uid' => 'owner-acc-1',
            'plaid_account_id' => 'owner-acc-1',
            'name' => 'Owner Checking',
            'display_name' => 'Daily spending',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 123.45],
            'is_active' => true,
        ]);

        BankAccount::query()->create([
            'bank_connection_id' => $otherConnection->id,
            'external_uid' => 'other-acc-1',
            'plaid_account_id' => 'other-acc-1',
            'name' => 'Other Checking',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 55.00],
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get('/settings/wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/wallets')
                ->has('accounts', 1)
                ->where('accounts.0.id', $ownerAccount->id)
                ->where('accounts.0.display_name', 'Daily spending')
                ->where('accounts.0.wallet_name', 'Owner Wallet')
                ->where('base_currency.selected', 'EUR')
                ->where('base_currency.options.0.code', 'EUR')
                ->where('base_currency.options.0.label', 'EUR - Euro')
                ->where('base_currency.options.1.code', 'USD')
                ->where('base_currency.options.1.label', 'USD - US Dollar')
                ->where('base_currency.options.2.code', 'GBP')
                ->where('base_currency.options.2.label', 'GBP - British Pound')
                ->where('number_locale.selected', 'en-GB')
                ->where('number_locale.options.0.code', 'en-GB')
                ->where('number_locale.options.0.example', '1,000.00')
                ->where('number_locale.options.2.code', 'de-DE')
                ->where('number_locale.options.2.example', '1.000,00'));
    }

    public function test_user_can_update_wallet_base_currency_preference(): void
    {
        $user = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'GBP',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet, 'base-currency-item');

        BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-base-1',
            'plaid_account_id' => 'acc-base-1',
            'name' => 'Checking',
            'currency' => 'GBP',
            'currency_code' => 'GBP',
            'balances_encrypted' => ['current' => 100],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch('/settings/wallets/base-currency', [
                'base_currency' => 'GBP',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_base_currency' => 'GBP',
        ]);
    }

    public function test_user_can_set_top_world_base_currency_without_matching_account_currency(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/wallets/base-currency', [
                'base_currency' => 'CAD',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_base_currency' => 'CAD',
        ]);
    }

    public function test_user_can_update_number_localization_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/wallets/number-locale', [
                'number_locale' => 'de-DE',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_number_locale' => 'de-DE',
        ]);
    }

    public function test_user_can_update_bank_account_display_name(): void
    {
        $user = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet, 'update-item');

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-update-1',
            'plaid_account_id' => 'acc-update-1',
            'name' => 'Checking',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 100],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch("/settings/wallets/accounts/{$account->id}", [
                'display_name' => 'Main spending account',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id,
            'display_name' => 'Main spending account',
        ]);
    }

    public function test_user_can_delete_bank_account_and_empty_connection_is_removed(): void
    {
        $user = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet, 'delete-item');

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-delete-1',
            'plaid_account_id' => 'acc-delete-1',
            'name' => 'Checking',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 100],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete("/settings/wallets/accounts/{$account->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('bank_accounts', [
            'id' => $account->id,
        ]);

        $this->assertDatabaseMissing('bank_connections', [
            'id' => $connection->id,
        ]);

        $this->assertDatabaseHas('bank_activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'account_deleted',
        ]);
    }

    public function test_user_cannot_update_or_delete_account_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($owner, $wallet, 'forbidden-item');

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-forbidden-1',
            'plaid_account_id' => 'acc-forbidden-1',
            'name' => 'Owner Account',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 100],
            'is_active' => true,
        ]);

        $this->actingAs($intruder)
            ->patch("/settings/wallets/accounts/{$account->id}", [
                'display_name' => 'Intruder edit',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete("/settings/wallets/accounts/{$account->id}")
            ->assertForbidden();
    }

    private function createPlaidConnection(User $user, Wallet $wallet, string $itemId): BankConnection
    {
        return BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => 'plaid',
            'plaid_item_id_hash' => hash('sha256', $itemId),
            'plaid_item_id_encrypted' => $itemId,
            'plaid_access_token_encrypted' => 'access-token',
            'institution_id' => 'ins_001',
            'institution_name' => 'Plaid Test Bank',
            'status' => 'connected',
            'aspsp_name' => 'Plaid Test Bank',
            'aspsp_country' => 'US',
            'state' => (string) fake()->uuid(),
            'authorized_at' => now(),
        ]);
    }
}
