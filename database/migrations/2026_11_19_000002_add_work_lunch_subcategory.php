<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Food & Drink > Work Lunch, for food bought at or near the office. Only this one
 * subcategory is added, so defaults someone deleted on purpose stay deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()->each(function (User $user): void {
            $parent = Category::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_id')
                ->where('type', 'expense')
                ->whereRaw('lower(name) = ?', ['food & drink'])
                ->first();

            if (! $parent instanceof Category) {
                return;
            }

            $exists = Category::query()
                ->where('user_id', $user->id)
                ->where('parent_id', $parent->id)
                ->whereRaw('lower(name) in (?, ?)', ['work lunch', 'work lunches'])
                ->exists();

            if (! $exists) {
                Category::query()->create([
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'name' => 'Work Lunch',
                    'type' => 'expense',
                    'icon' => 'sandwich',
                    'color' => $parent->color,
                ]);
            }
        });
    }

    public function down(): void
    {
        // It may already hold transactions; leave it.
    }
};
