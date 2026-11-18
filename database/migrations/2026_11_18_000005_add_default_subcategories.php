<?php

use App\Actions\Categories\CreateDefaultMainCategories;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Existing accounts get the default subcategories (Housing > Rent, Transportation > Fuel…)
 * that new accounts now start with. Nothing the user made is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = app(CreateDefaultMainCategories::class);

        User::query()->each(fn (User $user) => $action->addSubcategories($user));
    }

    public function down(): void
    {
        // Subcategories may already be in use; leave them.
    }
};
