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

    /**
     * Parent name => [subcategory name, icon]. Subcategories take their parent's color.
     * Names are already in the Title Case the Category model stores.
     *
     * @var array<string, array<string, list<array{string, string}>>>
     */
    private const DEFAULT_SUBCATEGORIES = [
        'expense' => [
            'Housing' => [
                ['Rent', 'key-round'],
                ['Mortgage', 'landmark'],
                ['Utilities', 'plug-zap'],
                ['Internet & Phone', 'wifi'],
                ['Home Insurance', 'shield-check'],
                ['Council Tax', 'receipt'],
                ['Repairs & Maintenance', 'wrench'],
                ['Furniture & Household', 'sofa'],
            ],
            'Transportation' => [
                ['Fuel', 'fuel'],
                ['Public Transport', 'bus-front'],
                ['Taxi & Rideshare', 'car-taxi-front'],
                ['Parking & Tolls', 'square-parking'],
                ['Car Insurance', 'shield-check'],
                ['Car Maintenance', 'wrench'],
            ],
            'Food & Drink' => [
                ['Restaurants', 'utensils'],
                ['Coffee & Cafés', 'coffee'],
                ['Takeaway & Delivery', 'bike'],
                ['Bars & Pubs', 'beer'],
            ],
            'Health & Wellness' => [
                ['Pharmacy', 'pill'],
                ['Doctor & Dentist', 'stethoscope'],
                ['Gym & Fitness', 'dumbbell'],
                ['Health Insurance', 'shield-plus'],
            ],
            'Personal Care' => [
                ['Hair & Beauty', 'scissors'],
                ['Toiletries', 'bath'],
            ],
            'Shopping' => [
                ['Clothing & Shoes', 'shirt'],
                ['Electronics', 'smartphone'],
                ['Home & Garden', 'sprout'],
                ['Online Shopping', 'package'],
            ],
            'Entertainment' => [
                ['Events & Cinema', 'ticket'],
                ['Games', 'gamepad-2'],
                ['Hobbies', 'palette'],
                ['Books & Music', 'book-open'],
            ],
            'Subscriptions' => [
                ['Streaming', 'tv'],
                ['Software & Apps', 'app-window'],
                ['Memberships', 'id-card'],
            ],
            'Travel & Vacation' => [
                ['Flights', 'plane-takeoff'],
                ['Accommodation', 'bed-double'],
                ['Activities', 'map'],
            ],
            'Debt Repayment' => [
                ['Credit Card', 'credit-card'],
                ['Loans', 'hand-coins'],
            ],
            'Family & Dependents' => [
                ['Childcare', 'baby'],
                ['Kids', 'backpack'],
                ['Pets', 'paw-print'],
            ],
            'Education' => [
                ['Courses', 'school'],
                ['Books & Supplies', 'book-open'],
            ],
            'Other' => [
                ['Bank Fees', 'percent'],
                ['Taxes', 'file-text'],
            ],
        ],
        'income' => [
            'Wages & Salary' => [
                ['Salary', 'banknote'],
                ['Bonus', 'sparkles'],
                ['Holiday Pay (Regres)', 'sun'],
            ],
            'Refunds & Reimbursements' => [
                ['Purchase Refunds', 'undo-2'],
                ['Expense Claims', 'file-check'],
            ],
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

        $this->addSubcategories($user);
    }

    /**
     * Add the default subcategories under the user's main categories. A main category the
     * user deleted or renamed is left alone, and existing subcategories are never touched.
     */
    public function addSubcategories(User $user): int
    {
        $parents = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('parent_id')
            ->get()
            ->keyBy(fn (Category $category): string => $category->type.':'.mb_strtolower((string) $category->name));
        $existing = Category::query()
            ->where('user_id', $user->id)
            ->whereNotNull('parent_id')
            ->get()
            ->map(fn (Category $category): string => $category->parent_id.':'.mb_strtolower((string) $category->name))
            ->flip();
        $added = 0;

        foreach (self::DEFAULT_SUBCATEGORIES as $type => $groups) {
            foreach ($groups as $parentName => $children) {
                $parent = $parents->get($type.':'.mb_strtolower($parentName));

                if (! $parent instanceof Category) {
                    continue;
                }

                foreach ($children as [$name, $icon]) {
                    if ($existing->has($parent->id.':'.mb_strtolower($name))) {
                        continue;
                    }

                    Category::query()->create([
                        'user_id' => $user->id,
                        'parent_id' => $parent->id,
                        'name' => $name,
                        'type' => $type,
                        'icon' => $icon,
                        'color' => $parent->color,
                    ]);
                    $added++;
                }
            }
        }

        return $added;
    }
}
