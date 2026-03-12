<?php

namespace Tests\Feature\Banking;

use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankConnectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_own_bank_connection(): void
    {
        $user = User::factory()->create();

        $connection = BankConnection::create([
            'user_id' => $user->id,
            'aspsp_name' => 'Mock ASPSP',
            'aspsp_country' => 'SI',
            'state' => 'delete-own-state',
            'status' => 'active',
            'session_id' => 'session-delete-own',
        ]);

        $response = $this->actingAs($user)->delete(route('banking.destroy', $connection));

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('success', 'Bank connection deleted.');
        $this->assertDatabaseMissing('bank_connections', ['id' => $connection->id]);
    }

    public function test_user_cannot_delete_other_users_bank_connection(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $connection = BankConnection::create([
            'user_id' => $owner->id,
            'aspsp_name' => 'Mock ASPSP',
            'aspsp_country' => 'SI',
            'state' => 'delete-other-state',
            'status' => 'active',
            'session_id' => 'session-delete-other',
        ]);

        $response = $this->actingAs($intruder)->delete(route('banking.destroy', $connection));

        $response->assertForbidden();
        $this->assertDatabaseHas('bank_connections', ['id' => $connection->id]);
    }
}
