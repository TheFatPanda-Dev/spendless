<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, list<string>>
     */
    private function expectedDefaultMainCategories(): array
    {
        return [
            'expense' => [
                'Housing',
                'Transportation',
                'Groceries',
                'Food & Drink',
                'Health & Wellness',
                'Personal Care',
                'Shopping',
                'Entertainment',
                'Subscriptions',
                'Travel & Vacation',
                'Debt Repayment',
                'Savings & Goals',
                'Investments',
                'Education',
                'Family & Dependents',
                'Gifts & Occasions',
                'Giving & Donations',
                'Other',
            ],
            'income' => [
                'Wages & Salary',
                'Self-Employment & Freelance',
                'Investment Dividends',
                'Stock Capital Gains',
                'Interest Income',
                'Rental & Real Estate Income',
                'Tips & Gratuities',
                'Gifts & Inheritance',
                'Government Benefits & Subsidies',
                'Tax Refunds',
                'Pensions & Retirement Distributions',
                'Alimony & Child Support',
                'Grants & Scholarships',
                'Royalties',
                'Side Hustles',
                'Refunds & Reimbursements',
                'Sales (Selling Personal Items)',
                'Other Income',
            ],
        ];
    }

    private function assertDefaultMainCategoriesCreatedFor(User $user): void
    {
        foreach ($this->expectedDefaultMainCategories() as $type => $expectedCategories) {
            $this->assertSame(
                $expectedCategories,
                Category::query()
                    ->where('user_id', $user->id)
                    ->whereNull('parent_id')
                    ->where('type', $type)
                    ->orderBy('id')
                    ->pluck('name')
                    ->all(),
            );
        }
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertDefaultMainCategoriesCreatedFor(
            User::query()->where('email', 'test@example.com')->firstOrFail(),
        );
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
