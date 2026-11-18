<?php

namespace Tests\Feature\Assets;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_isa_shows_profit_earned_so_far(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->startOfDay());
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('assets.store'), [
            'kind' => 'cash_isa',
            'name' => 'Cash ISA',
            'institution' => 'Al Rayan Bank',
            'currency' => 'GBP',
            'amount' => '20000',
            'rate' => '4.25',
            'start_date' => '2026-05-18',
            'years' => '2',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $account = BankAccount::query()->sole();
        $this->assertSame(BankConnection::PROVIDER_ASSET, $account->connection->provider);
        $this->assertSame('cash_isa', $account->subtype);
        $this->assertSame('savings', $account->connection->wallet->type);
        $this->assertSame('GBP', $account->currency);
        // Half a year into a 2-year term: about a quarter of the £1,700 profit.
        $this->assertEqualsWithDelta(20425.0, (float) $account->balances_encrypted['current'], 3.0);
        $this->assertSame(0, BankTransaction::query()->count(), 'No profit paid before the end');

        $this->actingAs($user)->get(route('wallets.show', $account->connection->wallet_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('wallet.connections.0.asset.kind', 'cash_isa')
                ->where('wallet.connections.0.asset.isa.total_profit', 1700)
                ->where('wallet.connections.0.asset.isa.maturity_date', '2028-05-18')
                ->where('wallet.connections.0.asset.isa.matured', false));
    }

    public function test_profit_becomes_income_when_the_term_ends(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->startOfDay());
        $user = User::factory()->create();
        $interest = Category::query()->create(['user_id' => $user->id, 'name' => 'Interest Income', 'type' => 'income', 'icon' => 'piggy-bank', 'color' => 'lime']);
        $account = $this->isa($user, start: '2026-01-01', years: '1');

        $this->assertSame(0, BankTransaction::query()->count());

        $this->travelTo(now()->setDate(2027, 1, 2));
        $this->artisan('assets:refresh')->assertSuccessful();
        $this->artisan('assets:refresh')->assertSuccessful();

        $profit = BankTransaction::query()->sole();
        $this->assertSame('425.00', $profit->amount);
        $this->assertSame('2027-01-01', $profit->date->toDateString());
        $this->assertSame($interest->id, $profit->category_id);
        $this->assertSame(10425.0, (float) $account->fresh()->balances_encrypted['current']);
    }

    public function test_editing_terms_recalculates(): void
    {
        $this->travelTo(now()->setDate(2027, 6, 1)->startOfDay());
        $user = User::factory()->create();
        $account = $this->isa($user, start: '2026-01-01', years: '1');
        $this->assertSame('425.00', BankTransaction::query()->sole()->amount);

        $this->actingAs($user)->patch(route('assets.update', $account->connection), [
            'amount' => '10000', 'rate' => '5', 'start_date' => '2026-01-01', 'years' => '2',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, BankTransaction::query()->count(), 'The term now ends in 2028, so nothing is paid yet');
        $this->assertGreaterThan(10000.0, (float) $account->fresh()->balances_encrypted['current']);
    }

    public function test_pension_value_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('assets.store'), [
            'kind' => 'pension', 'name' => 'Pension', 'institution' => 'PensionBee', 'currency' => 'GBP',
            'value' => '18450.32', 'date' => '2026-09-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $account = BankAccount::query()->sole();
        $connection = $account->connection;
        $this->assertSame(18450.32, (float) $account->balances_encrypted['current']);

        $this->actingAs($user)->post(route('assets.valuations.store', $connection), ['value' => '19000', 'date' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('assets.valuations.store', $connection), ['value' => '17000', 'date' => '2026-06-01'])->assertSessionHasNoErrors();

        $this->assertSame(19000.0, (float) $account->fresh()->balances_encrypted['current'], 'An older value does not replace the latest');

        $this->actingAs($user)->get(route('wallets.show', $connection->wallet_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('wallet.connections.0.asset.kind', 'pension')
                ->has('wallet.connections.0.asset.valuations', 3)
                ->where('wallet.connections.0.asset.valuations.0.value', 19000)
                ->where('wallet.connections.0.asset.valuations.0.change', 549.68));

        $latest = $account->valuations()->orderByDesc('date')->first();
        $this->actingAs($user)->delete(route('assets.valuations.destroy', [$connection, $latest]))->assertRedirect();
        $this->assertSame(18450.32, (float) $account->fresh()->balances_encrypted['current']);

        $this->actingAs($user)->post(route('assets.valuations.store', $connection), ['value' => '1', 'date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('date');
    }

    public function test_assets_count_in_the_dashboard_and_are_private(): void
    {
        $user = User::factory()->create();
        $account = $this->isa($user, start: now()->toDateString(), years: '1');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('accounts', fn ($accounts): bool => collect($accounts)->contains('id', $account->id)));

        $this->actingAs(User::factory()->create())
            ->patch(route('assets.update', $account->connection), ['amount' => '1', 'rate' => '1', 'start_date' => '2026-01-01', 'years' => '1'])
            ->assertForbidden();
    }

    private function isa(User $user, string $start, string $years): BankAccount
    {
        $this->actingAs($user)->post(route('assets.store'), [
            'kind' => 'cash_isa', 'name' => 'Cash ISA', 'institution' => 'Al Rayan Bank', 'currency' => 'GBP',
            'amount' => '10000', 'rate' => '4.25', 'start_date' => $start, 'years' => $years,
        ])->assertSessionHasNoErrors();

        return BankAccount::query()->latest('id')->firstOrFail();
    }
}
