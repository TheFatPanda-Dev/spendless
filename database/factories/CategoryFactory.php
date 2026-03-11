<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['expense', 'income']),
            'icon' => fake()->randomElement([
                'wallet',
                'landmark',
                'briefcase',
                'home',
                'shopping-bag',
                'car',
                'bike',
                'fuel',
            ]),
            'color' => fake()->randomElement([
                'emerald',
                'teal',
                'sky',
                'indigo',
                'amber',
                'coral',
                'rose',
            ]),
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => 'expense',
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => 'income',
        ]);
    }

    public function childOf(Category $category): static
    {
        return $this->state(fn (): array => [
            'user_id' => $category->user_id,
            'parent_id' => $category->id,
            'type' => $category->type,
        ]);
    }
}
