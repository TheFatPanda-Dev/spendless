<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): Category
    {
        return Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'icon' => $data['icon'],
            'color' => $data['color'],
        ]);
    }
}
