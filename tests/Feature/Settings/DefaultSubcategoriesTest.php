<?php

namespace Tests\Feature\Settings;

use App\Actions\Categories\CreateDefaultMainCategories;
use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DefaultSubcategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_get_main_categories_with_subcategories(): void
    {
        $user = User::factory()->create();

        app(CreateDefaultMainCategories::class)->handle($user);

        $housing = Category::query()->where('user_id', $user->id)->whereNull('parent_id')->where('name', 'Housing')->sole();
        $this->assertSame(36, Category::query()->where('user_id', $user->id)->whereNull('parent_id')->count());
        $this->assertSame(
            ['Council Tax', 'Furniture & Household', 'Home Insurance', 'Internet & Phone', 'Mortgage', 'Rent', 'Repairs & Maintenance', 'Utilities'],
            $housing->children()->get()->pluck('name')->sort()->values()->all(),
        );
        $this->assertTrue($housing->children->every(fn (Category $child): bool => $child->color === $housing->color && $child->type === 'expense'));
        $this->assertSame('Holiday Pay (Regres)', Category::query()->where('user_id', $user->id)->where('icon', 'sun')->sole()->name);
    }

    public function test_adding_subcategories_later_keeps_what_the_user_changed(): void
    {
        $user = User::factory()->create();
        $action = app(CreateDefaultMainCategories::class);
        $action->handle($user);

        Category::query()->where('user_id', $user->id)->where('name', 'Transportation')->delete();
        $rent = Category::query()->where('user_id', $user->id)->where('name', 'Rent')->sole();
        $rent->update(['color' => 'red']);
        $total = Category::query()->where('user_id', $user->id)->count();

        $this->assertSame(0, $action->addSubcategories($user), 'Nothing missing: no duplicates, deleted parents stay deleted.');
        $this->assertSame($total, Category::query()->where('user_id', $user->id)->count());
        $this->assertSame('red', $rent->fresh()->color);
    }

    /**
     * @return array<string, array{string, float, string}>
     */
    public static function bankTexts(): array
    {
        return [
            'Slovenian rent' => ['NAJEMNINA STANOVANJE 11/2026', -650, 'Rent'],
            'fuel station' => ['OMV 1234 LJUBLJANA', -45, 'Fuel'],
            'food delivery before taxi' => ['UBER *EATS', -21.5, 'Takeaway & Delivery'],
            'taxi' => ['UBER *TRIP', -12, 'Taxi & Rideshare'],
            'streaming' => ['Netflix.com', -12.99, 'Streaming'],
            'online shopping' => ['AMZN Mktp UK', -30, 'Online Shopping'],
            'HMRC is not H&M' => ['HM REVENUE & CUSTOMS', -300, 'Taxes'],
            'clothes' => ['H&M 123 LONDON', -40, 'Clothing & Shoes'],
            'generic card payment is not a credit card' => ['CARD PAYMENT TO XYZ LTD', -10, 'Other'],
            'generic bank transfer is not a bank fee' => ['BANK TRANSFER REF 123', -10, 'Other'],
            'salary' => ['PLACA 11/2026 ACME D.O.O.', 2500, 'Salary'],
            'holiday pay' => ['REGRES 2026', 1300, 'Holiday Pay (Regres)'],
            'rent received' => ['NAJEMNINA ZA NOVEMBER', 650, 'Rental & Real Estate Income'],
        ];
    }

    #[DataProvider('bankTexts')]
    public function test_bank_transactions_land_in_the_right_subcategory(string $text, float $amount, string $expected): void
    {
        $user = User::factory()->create();
        app(CreateDefaultMainCategories::class)->handle($user);

        $category = app(ResolveBankTransactionCategory::class)(new BankTransaction(['name' => $text, 'amount' => $amount]), $user);

        $this->assertSame($expected, $category?->name);
    }
}
