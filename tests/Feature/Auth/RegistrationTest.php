<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'nickname' => 'Panda',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'nickname' => 'Panda',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_can_not_register_with_duplicate_nickname()
    {
        User::factory()->create([
            'nickname' => 'Panda',
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Another User',
            'nickname' => 'Panda',
            'email' => 'another@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('nickname');
        $this->assertGuest();
    }
}
