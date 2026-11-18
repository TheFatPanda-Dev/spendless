<?php

namespace Tests\Feature\Tags;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transaction_can_be_tagged_and_untagged_from_its_account_page(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $transaction = $this->transaction($account, 'OMV Ljubljana', -45);

        $this->actingAs($user)->post(route('tags.store'), ['name' => 'Golf', 'color' => 'sky'])
            ->assertSessionHasNoErrors();
        $golf = Tag::query()->sole();

        $this->patchTransaction($user, $account, $transaction, ['tag_ids' => [$golf->id]])->assertSessionHasNoErrors();
        $this->assertSame([$golf->id], $transaction->tags()->pluck('tags.id')->all());

        $this->actingAs($user)->get(route('accounts.show', $account))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tags.0.name', 'Golf')
                ->where('transactions.0.tag_ids', [$golf->id]));

        $this->patchTransaction($user, $account, $transaction, ['tag_ids' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $transaction->tags()->count());
    }

    public function test_another_users_tag_cannot_be_used(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $transaction = $this->transaction($account, 'OMV', -45);
        $foreignTag = Tag::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'color' => 'red']);

        $this->patchTransaction($user, $account, $transaction, ['tag_ids' => [$foreignTag->id]])
            ->assertSessionHasErrors('tag_ids.0');
        $this->assertSame(0, $transaction->tags()->count());
    }

    public function test_rules_tag_new_transactions_once_and_a_removed_tag_stays_removed(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Housing']);
        $flat = Tag::query()->create(['user_id' => $user->id, 'name' => 'Flat Ljubljana', 'color' => 'emerald']);

        $this->actingAs($user)->post(route('tags.rules.store', $flat), ['contains' => 'J. Novak', 'apply_existing' => false])
            ->assertSessionHasNoErrors();

        $this->addManualTransaction($user, $account, $category, 'J NOVAK najemnina');
        $rent = BankTransaction::query()->where('merchant_name', 'J NOVAK najemnina')->sole();
        $this->assertSame([$flat->id], $rent->tags()->pluck('tags.id')->all(), 'Punctuation is ignored: "J. Novak" matches "J NOVAK".');

        $this->patchTransaction($user, $account, $rent, ['tag_ids' => []]);
        $this->addManualTransaction($user, $account, $category, 'Hofer');

        $this->assertSame(0, $rent->tags()->count(), 'Rules never re-add a tag removed by hand.');
        $this->assertSame(0, BankTransaction::query()->where('merchant_name', 'Hofer')->sole()->tags()->count());
    }

    public function test_a_rule_can_tag_existing_transactions_of_one_account(): void
    {
        $user = User::factory()->create();
        $motorbikeCard = $this->account($user, 'Motorbike card');
        $current = $this->account($user, 'Current');
        $onCard = $this->transaction($motorbikeCard, 'OMV Kranj', -18);
        $alsoOnCard = $this->transaction($motorbikeCard, 'Yamaha service', -120);
        $elsewhere = $this->transaction($current, 'OMV Kranj', -60);
        $motorbike = Tag::query()->create(['user_id' => $user->id, 'name' => 'Motorbike', 'color' => 'red']);

        $this->actingAs($user)->post(route('tags.rules.store', $motorbike), [
            'bank_account_id' => $motorbikeCard->id,
            'apply_existing' => true,
        ])->assertSessionHas('success', 'Rule added and 2 existing transactions tagged.');

        $this->assertSame(1, $onCard->tags()->count());
        $this->assertSame(1, $alsoOnCard->tags()->count());
        $this->assertSame(0, $elsewhere->tags()->count());
    }

    public function test_a_rule_needs_an_account_or_text_and_only_the_users_own_accounts(): void
    {
        $user = User::factory()->create();
        $tag = Tag::query()->create(['user_id' => $user->id, 'name' => 'Golf', 'color' => 'sky']);
        $foreignAccount = $this->account(User::factory()->create());

        $this->actingAs($user)->post(route('tags.rules.store', $tag), ['contains' => ' .. '])
            ->assertSessionHasErrors('contains');
        $this->actingAs($user)->post(route('tags.rules.store', $tag), ['bank_account_id' => $foreignAccount->id])
            ->assertSessionHasErrors('bank_account_id');
        $this->assertSame(0, $tag->rules()->count());
    }

    public function test_tags_page_shows_what_each_tag_cost_by_currency_and_category(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $transport = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Transportation']);
        $fuel = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Fuel', 'parent_id' => $transport->id]);
        $insurance = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Car Insurance', 'parent_id' => $transport->id]);
        $golf = Tag::query()->create(['user_id' => $user->id, 'name' => 'Golf', 'color' => 'sky']);
        Tag::query()->create(['user_id' => $user->id, 'name' => 'Unused', 'color' => 'red']);

        foreach ([
            $this->transaction($account, 'OMV', -45, $fuel),
            $this->transaction($account, 'Petrol', -30, $fuel),
            $this->transaction($account, 'Triglav', -400, $insurance),
            $this->transaction($account, 'Sold old tyres', 50),
            $this->transaction($account, 'Shell UK', -20, $fuel, 'GBP'),
        ] as $transaction) {
            $transaction->tags()->attach($golf);
        }
        $this->transaction($account, 'OMV untagged', -99, $fuel);

        $this->actingAs($user)->get(route('tags.index', ['period' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tags/index')
                ->where('period', 'all')
                ->has('tags', 2)
                ->where('tags.0.name', 'Golf')
                ->where('tags.0.count', 5)
                ->where('tags.0.totals.0', ['currency' => 'EUR', 'spent' => 475, 'received' => 50, 'count' => 4])
                ->where('tags.0.totals.1', ['currency' => 'GBP', 'spent' => 20, 'received' => 0, 'count' => 1])
                ->where('tags.0.by_category.0.category', 'Transportation / Car Insurance')
                ->where('tags.0.by_category.0.spent', 400)
                ->where('tags.0.by_category.1.category', 'Transportation / Fuel')
                ->where('tags.0.by_category.1.spent', 75)
                ->where('tags.1.count', 0));
    }

    public function test_tag_names_are_unique_per_user_and_tags_are_private(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tags.store'), ['name' => 'Golf', 'color' => 'sky']);

        $this->actingAs($user)->post(route('tags.store'), ['name' => ' golf ', 'color' => 'red'])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('tags.store'), ['name' => 'Yamaha', 'color' => 'not-a-color'])
            ->assertSessionHasErrors('color');

        $tag = Tag::query()->sole();
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->patch(route('tags.update', $tag), ['name' => 'Mine', 'color' => 'red'])->assertNotFound();
        $this->actingAs($intruder)->delete(route('tags.destroy', $tag))->assertNotFound();

        $this->actingAs($user)->patch(route('tags.update', $tag), ['name' => 'VW Golf', 'color' => 'blue'])->assertSessionHasNoErrors();
        $this->assertSame('VW Golf', $tag->fresh()->name);

        $this->actingAs($user)->delete(route('tags.destroy', $tag))->assertSessionHasNoErrors();
        $this->assertModelMissing($tag);
    }

    private function account(User $user, string $name = 'Current'): BankAccount
    {
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

        return BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'manual-'.uniqid(),
            'name' => $name,
            'display_name' => $name,
            'currency' => 'EUR',
            'balances_encrypted' => ['current' => 0, 'available' => 0],
            'is_active' => true,
        ]);
    }

    private function transaction(BankAccount $account, string $merchant, float $amount, ?Category $category = null, string $currency = 'EUR'): BankTransaction
    {
        return BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'category_id' => $category?->id,
            'category_manually_set' => $category !== null,
            'external_uid' => 'tx-'.uniqid(),
            'merchant_name' => $merchant,
            'name' => $merchant,
            'amount' => $amount,
            'currency' => $currency,
            'date' => now()->toDateString(),
            'pending' => false,
        ]);
    }

    private function patchTransaction(User $user, BankAccount $account, BankTransaction $transaction, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->patch(route('accounts.transactions.update', [$account, $transaction]), $data);
    }

    private function addManualTransaction(User $user, BankAccount $account, Category $category, string $merchant): void
    {
        $this->actingAs($user)->post(route('accounts.transactions.manual.store', $account), [
            'category_id' => $category->id,
            'amount' => '650',
            'date' => now()->toDateString(),
            'merchant_name' => $merchant,
            'transaction_name' => $merchant,
        ])->assertSessionHasNoErrors();
    }
}
