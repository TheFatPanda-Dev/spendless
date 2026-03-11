<?php

namespace App\Support\Categories;

class CategoryOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function types(): array
    {
        return [
            ['value' => 'expense', 'label' => 'Expense'],
            ['value' => 'income', 'label' => 'Income'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function icons(): array
    {
        return [
            ['value' => 'wallet', 'label' => 'Wallet'],
            ['value' => 'landmark', 'label' => 'Bank'],
            ['value' => 'building-2', 'label' => 'Building'],
            ['value' => 'banknote', 'label' => 'Cash'],
            ['value' => 'coins', 'label' => 'Coins'],
            ['value' => 'hand-coins', 'label' => 'Hand Coins'],
            ['value' => 'piggy-bank', 'label' => 'Piggy Bank'],
            ['value' => 'badge-dollar-sign', 'label' => 'Salary'],
            ['value' => 'briefcase', 'label' => 'Work'],
            ['value' => 'laptop', 'label' => 'Laptop'],
            ['value' => 'monitor', 'label' => 'Monitor'],
            ['value' => 'phone', 'label' => 'Phone'],
            ['value' => 'wifi', 'label' => 'Internet'],
            ['value' => 'home', 'label' => 'Home'],
            ['value' => 'hammer', 'label' => 'Tools'],
            ['value' => 'shopping-bag', 'label' => 'Shopping'],
            ['value' => 'shopping-cart', 'label' => 'Groceries'],
            ['value' => 'shirt', 'label' => 'Clothing'],
            ['value' => 'utensils-crossed', 'label' => 'Food'],
            ['value' => 'car', 'label' => 'Car'],
            ['value' => 'bike', 'label' => 'Bike'],
            ['value' => 'bus-front', 'label' => 'Bus'],
            ['value' => 'train-front', 'label' => 'Train'],
            ['value' => 'fuel', 'label' => 'Fuel'],
            ['value' => 'wrench', 'label' => 'Maintenance'],
            ['value' => 'heart-pulse', 'label' => 'Health'],
            ['value' => 'dumbbell', 'label' => 'Fitness'],
            ['value' => 'paw-print', 'label' => 'Pets'],
            ['value' => 'baby', 'label' => 'Kids'],
            ['value' => 'plane', 'label' => 'Travel'],
            ['value' => 'graduation-cap', 'label' => 'Education'],
            ['value' => 'book-open', 'label' => 'Books'],
            ['value' => 'film', 'label' => 'Movies'],
            ['value' => 'music-4', 'label' => 'Music'],
            ['value' => 'gamepad-2', 'label' => 'Games'],
            ['value' => 'party-popper', 'label' => 'Celebration'],
            ['value' => 'receipt', 'label' => 'Bills'],
            ['value' => 'package', 'label' => 'Packages'],
            ['value' => 'gift', 'label' => 'Gifts'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, background: string, foreground: string}>
     */
    public static function colors(): array
    {
        return [
            ['value' => 'emerald', 'label' => 'Emerald', 'background' => 'rgb(16 185 129)', 'foreground' => 'rgb(236 253 245)'],
            ['value' => 'teal', 'label' => 'Teal', 'background' => 'rgb(13 148 136)', 'foreground' => 'rgb(240 253 250)'],
            ['value' => 'sky', 'label' => 'Sky', 'background' => 'rgb(14 165 233)', 'foreground' => 'rgb(240 249 255)'],
            ['value' => 'indigo', 'label' => 'Indigo', 'background' => 'rgb(99 102 241)', 'foreground' => 'rgb(238 242 255)'],
            ['value' => 'amber', 'label' => 'Amber', 'background' => 'rgb(245 158 11)', 'foreground' => 'rgb(255 251 235)'],
            ['value' => 'coral', 'label' => 'Coral', 'background' => 'rgb(249 115 22)', 'foreground' => 'rgb(255 247 237)'],
            ['value' => 'rose', 'label' => 'Rose', 'background' => 'rgb(244 63 94)', 'foreground' => 'rgb(255 241 242)'],
            ['value' => 'plum', 'label' => 'Plum', 'background' => 'rgb(168 85 247)', 'foreground' => 'rgb(250 245 255)'],
            ['value' => 'slate', 'label' => 'Slate', 'background' => 'rgb(71 85 105)', 'foreground' => 'rgb(248 250 252)'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function typeValues(): array
    {
        return array_column(self::types(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function iconValues(): array
    {
        return array_column(self::icons(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function colorValues(): array
    {
        return array_column(self::colors(), 'value');
    }
}
