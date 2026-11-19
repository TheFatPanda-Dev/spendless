<?php

namespace Tests\Feature;

use App\Actions\Transactions\RecognizeTransfers;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetsAndActivityTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH = ['start_date' => '2026-11-01', 'end_date' => '2026-11-30'];

    public function test_the_overview_leads_with_what_is_left_of_the_monthly_budget(): void
    {
        [$user, $account] = $this->owner();
        $groceries = $this->category($user, 'Groceries');
        $dining = $this->category($user, 'Food & Drink');
        $this->transaction($account, 'Hofer', -300, $groceries);
        $this->transaction($account, 'Gostilna', -165.40, $dining);
        $this->transaction($account, 'Salary', 2500, $this->category($user, 'Wages & Salary', 'income'));
        $this->transaction($account, 'Opening balance', 900, null, 'manual-opening-balance-1');

        $this->actingAs($user)->post(route('budgets.store'), ['amount' => '2000'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('budgets.store'), ['category_id' => $dining->id, 'amount' => '150'])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('dashboard', self::MONTH))
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.amount', 2000)
                ->where('budget.spent', 465.4)
                ->where('budget.left', 1534.6)
                ->where('budget.percent', 23)
                ->where('income', 2500, 'Opening balances are not income.')
                ->where('categories.0.name', 'Groceries')
                ->where('categories.0.amount', 300)
                ->where('overBudget.0.name', 'Food & Drink')
                ->where('overBudget.0.over', 15.4)
                ->has('recent', 3));
    }

    public function test_friends_paying_back_their_share_lowers_spending_and_is_not_income(): void
    {
        [$user, $cash] = $this->owner(name: 'Cash');
        [, $bank] = $this->owner($user, 'Revolut');
        $food = $this->category($user, 'Food & Drink');
        $workLunch = Category::query()->create(['user_id' => $user->id, 'parent_id' => $food->id, 'name' => 'Work Lunch', 'type' => 'expense', 'icon' => 'sandwich', 'color' => 'amber']);

        $this->transaction($bank, 'Maharaja', -51.70, $workLunch);
        $this->transaction($bank, 'Transfer from ADAM STRADOVNIK', 13.90, $workLunch);
        $this->transaction($bank, 'Transfer from BOSTJAN OBLAK', 13.90, $workLunch);
        $this->transaction($bank, 'Payment from employer', 2000, $this->category($user, 'Wages & Salary', 'income'));

        // The third share came in cash: money in, recorded against the same category.
        $this->actingAs($user)
            ->post("/accounts/{$cash->id}/manual-transactions?from=activity", [
                'category_id' => $workLunch->id,
                'amount' => '13.90',
                'date' => '2026-11-10',
                'merchant_name' => 'Luka',
                'direction' => 'in',
            ])
            ->assertSessionHas('success', 'Money back saved.');

        $this->assertSame('13.90', BankTransaction::query()->where('merchant_name', 'Luka')->sole()->amount);
        $this->assertSame(13.9, (float) $cash->fresh()->balances_encrypted['current']);

        $this->actingAs($user)->get(route('dashboard', self::MONTH))
            ->assertInertia(fn (Assert $page) => $page
                ->where('spent', 10)
                ->where('income', 2000)
                ->where('categories.0.name', 'Food & Drink')
                ->where('categories.0.amount', 10));

        $activity = fn (array $query = []) => $this->actingAs($user)->get(route('activity.index', [...self::MONTH, ...$query]));

        $activity()->assertInertia(fn (Assert $page) => $page
            ->where('totals.0.out', -10)
            ->where('totals.0.in', 2000));
        $activity(['type' => 'expense'])->assertInertia(fn (Assert $page) => $page->has('transactions', 4));
        $activity(['type' => 'income'])->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.name', 'Payment from employer'));
    }

    public function test_moving_money_between_my_accounts_is_neither_income_nor_spending(): void
    {
        [$user, $barclays] = $this->owner(name: 'Barclays');
        [, $nationwide] = $this->owner($user, 'Nationwide');
        $transfers = $this->category($user, 'Transfers', 'transfer');
        $between = Category::query()->create(['user_id' => $user->id, 'parent_id' => $transfers->id, 'name' => 'Between My Accounts', 'type' => 'transfer', 'icon' => 'arrow-left-right', 'color' => 'stone']);
        $investments = Category::query()->create(['user_id' => $user->id, 'parent_id' => $transfers->id, 'name' => 'Investments & Savings', 'type' => 'transfer', 'icon' => 'piggy-bank', 'color' => 'stone']);
        $groceries = $this->category($user, 'Groceries');
        $otherIncome = $this->category($user, 'Other Income', 'income');

        $this->transaction($barclays, 'To Nationwide', -1200, $between);
        $this->transaction($nationwide, 'From Barclays', 1200, $between);
        $this->transaction($barclays, 'Tesco', -40, $groceries);
        $this->transaction($nationwide, 'Gift from Mum', 100, $otherIncome);
        $this->transaction($nationwide, 'Uncategorised refund', 25);
        // Imported before transfers existed: automatically filed as income.
        $ibkr = $this->transaction($nationwide, 'Payment from INTERACTIVE BROKERS', 500, $otherIncome);
        $ibkr->forceFill(['category_manually_set' => false])->save();
        $handPicked = $this->transaction($nationwide, 'Interactive Brokers dividend I filed myself', 7, $otherIncome);

        $this->assertSame(1, app(RecognizeTransfers::class)->handle($user));
        $this->assertSame($investments->id, $ibkr->fresh()->category_id);
        $this->assertSame($otherIncome->id, $handPicked->fresh()->category_id, 'Hand-picked categories are kept');

        $this->actingAs($user)->get(route('dashboard', self::MONTH))
            ->assertInertia(fn (Assert $page) => $page
                ->where('spent', 40)
                ->where('income', 132)
                ->has('categories', 1));

        $activity = fn (array $query = []) => $this->actingAs($user)->get(route('activity.index', [...self::MONTH, ...$query]));

        $activity()->assertInertia(fn (Assert $page) => $page
            ->where('totals.0.out', -40)
            ->where('totals.0.in', 132));
        $activity(['type' => 'transfer'])->assertInertia(fn (Assert $page) => $page->has('transactions', 3));
        $activity(['type' => 'expense'])->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('transactions.0.name', 'Tesco'));
        $activity(['type' => 'income'])->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 3)
            ->where('transactions', fn ($transactions): bool => collect($transactions)->pluck('name')->contains('Uncategorised refund')));
    }

    public function test_saving_the_monthly_budget_again_changes_it_instead_of_adding_another(): void
    {
        [$user] = $this->owner();

        $this->actingAs($user)->post(route('budgets.store'), ['amount' => '2000']);
        $this->actingAs($user)->post(route('budgets.store'), ['amount' => '1800.50']);

        $this->assertSame(1, Budget::query()->count());
        $this->assertSame('1800.50', Budget::query()->sole()->amount);
    }

    public function test_only_your_own_main_expense_categories_can_have_a_budget(): void
    {
        [$user] = $this->owner();
        $transport = $this->category($user, 'Transportation');
        $fuel = Category::query()->create(['user_id' => $user->id, 'parent_id' => $transport->id, 'name' => 'Fuel', 'type' => 'expense', 'icon' => 'fuel', 'color' => 'sky']);
        $salary = $this->category($user, 'Wages & Salary', 'income');
        $foreign = $this->category(User::factory()->create(), 'Groceries');

        foreach ([$fuel, $salary, $foreign] as $category) {
            $this->actingAs($user)->post(route('budgets.store'), ['category_id' => $category->id, 'amount' => '100'])
                ->assertSessionHasErrors('category_id');
        }

        $this->actingAs($user)->post(route('budgets.store'), ['category_id' => $transport->id, 'amount' => '0'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, Budget::query()->count());

        $budget = Budget::query()->create(['user_id' => User::factory()->create()->id, 'amount' => 50]);
        $this->actingAs($user)->delete(route('budgets.destroy', $budget))->assertNotFound();
    }

    public function test_the_budgets_page_lists_category_budgets_and_unbudgeted_spending(): void
    {
        [$user, $account] = $this->owner();
        $groceries = $this->category($user, 'Groceries');
        $housing = $this->category($user, 'Housing');
        $this->transaction($account, 'Hofer', -80, $groceries);
        $this->transaction($account, 'Rent', -650, $housing);
        Budget::query()->create(['user_id' => $user->id, 'category_id' => $groceries->id, 'amount' => 300]);

        $this->actingAs($user)->get(route('budgets.index', self::MONTH))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('budgets/index')
                ->where('total', null)
                ->where('spent', 730)
                ->where('categories.0.name', 'Groceries')
                ->where('categories.0.left', 220)
                ->where('unbudgeted.0.name', 'Housing')
                ->where('options', fn ($options): bool => collect($options)->pluck('name')->all() === ['Housing']));
    }

    public function test_activity_lists_every_wallet_and_filters_by_type_category_tag_and_text(): void
    {
        [$user, $current] = $this->owner();
        [, $cash] = $this->owner($user, 'Pocket cash');
        $transport = $this->category($user, 'Transportation');
        $fuel = Category::query()->create(['user_id' => $user->id, 'parent_id' => $transport->id, 'name' => 'Fuel', 'type' => 'expense', 'icon' => 'fuel', 'color' => 'sky']);
        $golf = Tag::query()->create(['user_id' => $user->id, 'name' => 'Golf', 'color' => 'sky']);
        $omv = $this->transaction($current, 'OMV Ljubljana', -45.2, $fuel);
        $omv->tags()->attach($golf);
        $this->transaction($cash, 'Market', -12, $this->category($user, 'Groceries'));
        $this->transaction($current, 'Salary', 2500, $this->category($user, 'Wages & Salary', 'income'));
        $this->transaction($current, 'October fuel', -30, $fuel, date: '2026-10-28');
        [, $foreign] = $this->owner(User::factory()->create());
        $this->transaction($foreign, 'Not mine', -5);

        $page = fn (array $query) => $this->actingAs($user)->get(route('activity.index', [...self::MONTH, ...$query]));

        $page([])->assertInertia(fn (Assert $page) => $page
            ->component('activity/index')
            ->has('transactions', 3)
            ->where('totals.0.in', 2500)
            ->where('totals.0.out', -57.2));
        $page(['type' => 'income'])->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('transactions.0.name', 'Salary'));
        $page(['category' => $transport->id])->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('transactions.0.name', 'OMV Ljubljana')->where('transactions.0.category_color', 'rgb(14 165 233)'));
        $page(['tag' => $golf->id])->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('transactions.0.tag_ids', [$golf->id]));
        $page(['q' => 'market'])->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('transactions.0.account', 'Pocket cash'));
    }

    public function test_editing_from_activity_returns_to_activity(): void
    {
        [$user, $account] = $this->owner();
        $transaction = $this->transaction($account, 'OMV', -45);
        $fuel = $this->category($user, 'Fuel');

        $this->actingAs($user)
            ->from(route('activity.index', self::MONTH))
            ->patch(route('accounts.transactions.update', [$account, $transaction]).'?from=activity', ['category_id' => $fuel->id])
            ->assertRedirect(route('activity.index', self::MONTH));

        $this->assertSame($fuel->id, $transaction->fresh()->category_id);
    }

    /**
     * @return array{User, BankAccount}
     */
    private function owner(?User $user = null, string $name = 'Current'): array
    {
        $user ??= User::factory()->create();
        $wallet = Wallet::query()->create(['user_id' => $user->id, 'name' => $name, 'type' => 'cash', 'currency' => 'EUR']);
        $connection = BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => BankConnection::PROVIDER_MANUAL,
            'institution_name' => $name,
            'aspsp_name' => $name,
            'aspsp_country' => 'ZZ',
            'state' => 'state-'.uniqid(),
            'status' => BankConnection::STATUS_CONNECTED,
        ]);

        return [$user, BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'manual-'.uniqid(),
            'name' => $name,
            'display_name' => $name,
            'currency' => 'EUR',
            'balances_encrypted' => ['current' => 0, 'available' => 0],
            'is_active' => true,
        ])];
    }

    private function category(User $user, string $name, string $type = 'expense'): Category
    {
        return Category::query()->create(['user_id' => $user->id, 'name' => $name, 'type' => $type, 'icon' => 'package', 'color' => 'slate']);
    }

    private function transaction(BankAccount $account, string $name, float $amount, ?Category $category = null, ?string $uid = null, string $date = '2026-11-10'): BankTransaction
    {
        return BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'category_id' => $category?->id,
            'category_manually_set' => $category !== null,
            'external_uid' => $uid ?? 'tx-'.uniqid(),
            'merchant_name' => $name,
            'name' => $name,
            'amount' => $amount,
            'currency' => 'EUR',
            'date' => $date,
            'pending' => false,
        ]);
    }
}
