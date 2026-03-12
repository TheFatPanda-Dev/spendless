<?php

namespace Tests\Unit\Actions\Transactions;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveBankTransactionCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_specific_vehicle_subcategories_for_fuel_transactions(): void
    {
        $user = User::factory()->create();

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

        $transaction = new BankTransaction([
            'merchant_name' => 'Shell Petrol Station',
            'name' => 'Fuel purchase',
            'amount' => 55.90,
            'category' => ['Transportation', 'Fuel'],
            'personal_finance_category' => [
                'primary' => 'TRANSPORTATION',
                'detailed' => 'TRANSPORTATION_GAS',
            ],
        ]);

        $resolvedCategory = app(ResolveBankTransactionCategory::class)($transaction, $user);

        $this->assertInstanceOf(Category::class, $resolvedCategory);
        $this->assertSame($car->id, $resolvedCategory->id);
    }

    public function test_it_falls_back_to_other_for_unknown_expense_transactions(): void
    {
        $user = User::factory()->create();

        $other = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Other',
            'type' => 'expense',
            'icon' => 'package',
            'color' => 'slate',
        ]);

        $transaction = new BankTransaction([
            'merchant_name' => 'Unknown Merchant',
            'name' => 'Card payment',
            'amount' => 12.30,
            'category' => [],
            'personal_finance_category' => [],
        ]);

        $resolvedCategory = app(ResolveBankTransactionCategory::class)($transaction, $user);

        $this->assertInstanceOf(Category::class, $resolvedCategory);
        $this->assertSame($other->id, $resolvedCategory->id);
    }
}
