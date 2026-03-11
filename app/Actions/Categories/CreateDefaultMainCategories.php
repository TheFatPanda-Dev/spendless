<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class CreateDefaultMainCategories
{
    /**
     * @var list<array{name: string, icon: string, color: string}>
     */
    private const DEFAULT_CATEGORIES = [
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
    ];

    public function handle(User $user): void
    {
        foreach (self::DEFAULT_CATEGORIES as $category) {
            Category::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'parent_id' => null,
                    'name' => $category['name'],
                ],
                [
                    'type' => 'expense',
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                ],
            );
        }
    }
}
