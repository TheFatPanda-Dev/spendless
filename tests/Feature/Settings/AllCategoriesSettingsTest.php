<?php

namespace Tests\Feature\Settings;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AllCategoriesSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_categories_page_displays_only_the_authenticated_users_category_tree(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicles = Category::factory()->expense()->create([
            'user_id' => $owner->id,
            'name' => 'Vehicles',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        Category::factory()->childOf($vehicles)->create([
            'name' => 'Motorbike',
            'icon' => 'bike',
            'color' => 'teal',
        ]);

        Category::factory()->income()->create([
            'user_id' => $otherUser->id,
            'name' => 'Salary',
            'icon' => 'badge-dollar-sign',
            'color' => 'emerald',
        ]);

        $this->actingAs($owner)
            ->get('/settings/all-categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/all-categories')
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.name', 'Vehicles')
                ->where('expenseCategories.0.children.0.name', 'Motorbike')
                ->has('incomeCategories', 0)
                ->has('parentOptions.expense', 2)
                ->has('categoryIcons')
                ->has('categoryColors'));
    }

    public function test_user_can_create_a_top_level_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'Salary',
                'type' => 'income',
                'icon' => 'badge-dollar-sign',
                'color' => 'emerald',
                'parent_id' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Salary',
            'type' => 'income',
            'parent_id' => null,
        ]);
    }

    public function test_user_can_create_a_subcategory_for_a_matching_parent_type(): void
    {
        $user = User::factory()->create();

        $parent = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Vehicles',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'Car',
                'type' => 'expense',
                'icon' => 'car',
                'color' => 'coral',
                'parent_id' => $parent->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Car',
            'type' => 'expense',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_user_cannot_create_a_category_under_a_parent_with_a_different_type(): void
    {
        $user = User::factory()->create();

        $incomeParent = Category::factory()->income()->create([
            'user_id' => $user->id,
            'name' => 'Income',
            'icon' => 'wallet',
            'color' => 'emerald',
        ]);

        $this->actingAs($user)
            ->from('/settings/all-categories')
            ->post('/settings/all-categories', [
                'name' => 'Fuel',
                'type' => 'expense',
                'icon' => 'fuel',
                'color' => 'amber',
                'parent_id' => $incomeParent->id,
            ])
            ->assertSessionHasErrors(['parent_id'])
            ->assertRedirect('/settings/all-categories');
    }

    public function test_user_can_create_a_category_with_a_custom_hex_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'Subscriptions',
                'type' => 'expense',
                'icon' => 'monitor',
                'color' => '#2dd4bf',
                'parent_id' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Subscriptions',
            'icon' => 'monitor',
            'color' => '#2dd4bf',
        ]);
    }

    public function test_category_names_are_saved_in_title_case(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'dart veder',
                'type' => 'expense',
                'icon' => 'car',
                'color' => 'sky',
                'parent_id' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Dart Veder',
            'type' => 'expense',
            'parent_id' => null,
        ]);
    }

    public function test_category_names_are_normalized_from_mixed_case_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'viHaClE',
                'type' => 'expense',
                'icon' => 'car',
                'color' => 'sky',
                'parent_id' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Vihacle',
            'type' => 'expense',
            'parent_id' => null,
        ]);
    }

    public function test_user_cannot_create_a_duplicate_top_level_category_name(): void
    {
        $user = User::factory()->create();

        Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Car',
            'icon' => 'car',
            'color' => 'sky',
            'parent_id' => null,
        ]);

        $this->actingAs($user)
            ->from('/settings/all-categories')
            ->post('/settings/all-categories', [
                'name' => 'car',
                'type' => 'expense',
                'icon' => 'fuel',
                'color' => 'amber',
                'parent_id' => null,
            ])
            ->assertSessionHasErrors([
                'name' => 'A main category with this name already exists. This name can only be added as a subcategory.',
            ])
            ->assertRedirect('/settings/all-categories');
    }

    public function test_user_cannot_create_duplicate_subcategory_names_under_the_same_parent(): void
    {
        $user = User::factory()->create();

        $parent = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Car',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        Category::factory()->expense()->childOf($parent)->create([
            'user_id' => $user->id,
            'name' => 'Petrol',
            'icon' => 'fuel',
            'color' => 'amber',
        ]);

        $this->actingAs($user)
            ->from('/settings/all-categories')
            ->post('/settings/all-categories', [
                'name' => 'petrol',
                'type' => 'expense',
                'icon' => 'fuel',
                'color' => 'amber',
                'parent_id' => $parent->id,
            ])
            ->assertSessionHasErrors([
                'name' => 'A subcategory with this name already exists under the selected parent.',
            ])
            ->assertRedirect('/settings/all-categories');
    }

    public function test_user_can_reuse_a_subcategory_name_under_a_different_parent(): void
    {
        $user = User::factory()->create();

        $car = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Car',
            'icon' => 'car',
            'color' => 'sky',
        ]);

        $home = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Home',
            'icon' => 'house',
            'color' => 'emerald',
        ]);

        Category::factory()->expense()->childOf($car)->create([
            'user_id' => $user->id,
            'name' => 'Petrol',
            'icon' => 'fuel',
            'color' => 'amber',
        ]);

        $this->actingAs($user)
            ->post('/settings/all-categories', [
                'name' => 'Petrol',
                'type' => 'expense',
                'icon' => 'droplets',
                'color' => 'teal',
                'parent_id' => $home->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Petrol',
            'parent_id' => $home->id,
        ]);
    }
}
