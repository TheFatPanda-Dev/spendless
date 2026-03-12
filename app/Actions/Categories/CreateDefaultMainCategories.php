<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class CreateDefaultMainCategories
{
    /**
     * @var array<string, list<array{name: string, icon: string, color: string}>>
     */
    private const DEFAULT_CATEGORIES = [
        'expense' => [
            ['name' => 'Housing', 'icon' => 'house', 'color' => 'emerald'],
            ['name' => 'Transportation', 'icon' => 'car', 'color' => 'sky'],
            ['name' => 'Groceries', 'icon' => 'shopping-basket', 'color' => 'green'],
            ['name' => 'Food & Drink', 'icon' => 'utensils-crossed', 'color' => 'amber'],
            ['name' => 'Health & Wellness', 'icon' => 'heart-pulse', 'color' => 'rose'],
            ['name' => 'Personal Care', 'icon' => 'sparkles', 'color' => 'pink'],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'color' => 'violet'],
            ['name' => 'Entertainment', 'icon' => 'clapperboard', 'color' => 'fuchsia'],
            ['name' => 'Subscriptions', 'icon' => 'repeat', 'color' => 'indigo'],
            ['name' => 'Travel & Vacation', 'icon' => 'plane', 'color' => 'cyan'],
            ['name' => 'Debt Repayment', 'icon' => 'receipt-text', 'color' => 'red'],
            ['name' => 'Savings & Goals', 'icon' => 'piggy-bank', 'color' => 'lime'],
            ['name' => 'Investments', 'icon' => 'chart-candlestick', 'color' => 'teal'],
            ['name' => 'Education', 'icon' => 'graduation-cap', 'color' => 'blue'],
            ['name' => 'Family & Dependents', 'icon' => 'users', 'color' => 'coral'],
            ['name' => 'Gifts & Occasions', 'icon' => 'gift', 'color' => 'plum'],
            ['name' => 'Giving & Donations', 'icon' => 'hand-heart', 'color' => 'stone'],
            ['name' => 'Other', 'icon' => 'package', 'color' => 'slate'],
        ],
        'income' => [
            ['name' => 'Wages & Salary', 'icon' => 'briefcase', 'color' => 'emerald'],
            ['name' => 'Self-Employment & Freelance', 'icon' => 'hammer', 'color' => 'sky'],
            ['name' => 'Investment Dividends', 'icon' => 'badge-percent', 'color' => 'green'],
            ['name' => 'Stock Capital Gains', 'icon' => 'chart-candlestick', 'color' => 'teal'],
            ['name' => 'Interest Income', 'icon' => 'piggy-bank', 'color' => 'lime'],
            ['name' => 'Rental & Real Estate Income', 'icon' => 'building', 'color' => 'blue'],
            ['name' => 'Tips & Gratuities', 'icon' => 'hand-coins', 'color' => 'amber'],
            ['name' => 'Gifts & Inheritance', 'icon' => 'gift', 'color' => 'plum'],
            ['name' => 'Government Benefits & Subsidies', 'icon' => 'landmark', 'color' => 'indigo'],
            ['name' => 'Tax Refunds', 'icon' => 'receipt', 'color' => 'cyan'],
            ['name' => 'Pensions & Retirement Distributions', 'icon' => 'wallet', 'color' => 'violet'],
            ['name' => 'Alimony & Child Support', 'icon' => 'heart-handshake', 'color' => 'rose'],
            ['name' => 'Grants & Scholarships', 'icon' => 'graduation-cap', 'color' => 'blue'],
            ['name' => 'Royalties', 'icon' => 'copyright', 'color' => 'fuchsia'],
            ['name' => 'Side Hustles', 'icon' => 'rocket', 'color' => 'coral'],
            ['name' => 'Refunds & Reimbursements', 'icon' => 'refresh-ccw', 'color' => 'stone'],
            ['name' => 'Sales (Selling Personal Items)', 'icon' => 'store', 'color' => 'red'],
            ['name' => 'Other Income', 'icon' => 'banknote', 'color' => 'pink'],
        ],
    ];

    public function handle(User $user): void
    {
        foreach (self::DEFAULT_CATEGORIES as $type => $categories) {
            foreach ($categories as $category) {
                Category::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'parent_id' => null,
                        'name' => $category['name'],
                        'type' => $type,
                    ],
                    [
                        'icon' => $category['icon'],
                        'color' => $category['color'],
                    ],
                );
            }
        }
    }
}
