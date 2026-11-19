<?php

use App\Actions\Categories\CreateDefaultMainCategories;
use App\Actions\Transactions\RecognizeTransfers;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Transfers: a third kind of category for money moved between your own accounts,
 * to investments or savings, or between currencies. It counts as neither income
 * nor spending. Accounts that already have a transfer category keep theirs. Then
 * automatically categorised transactions that are clearly transfers are moved over.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()->each(function (User $user): void {
            $this->addCategories($user);
            app(RecognizeTransfers::class)->handle($user);
        });
    }

    private function addCategories(User $user): void
    {
        if (Category::query()->where('user_id', $user->id)->where('type', 'transfer')->exists()) {
            return;
        }

        $parent = Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'name' => 'Transfers',
            'type' => 'transfer',
            'icon' => 'arrow-left-right',
            'color' => 'stone',
        ]);

        foreach (CreateDefaultMainCategories::TRANSFER_SUBCATEGORIES as [$name, $icon]) {
            Category::query()->create([
                'user_id' => $user->id,
                'parent_id' => $parent->id,
                'name' => $name,
                'type' => 'transfer',
                'icon' => $icon,
                'color' => $parent->color,
            ]);
        }
    }

    public function down(): void
    {
        // They may already hold transactions; leave them.
    }
};
