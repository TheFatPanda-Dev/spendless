<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAvatarUrlNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_protocol_relative_google_avatar_is_normalized_to_https(): void
    {
        $user = User::factory()->create([
            'avatar_path' => null,
            'google_avatar' => '//lh3.googleusercontent.com/a/example-avatar=s96-c',
            'github_avatar' => null,
        ]);

        $this->assertSame('https://lh3.googleusercontent.com/a/example-avatar=s96-c', $user->avatar);
    }

    public function test_protocol_relative_github_avatar_is_normalized_to_https(): void
    {
        $user = User::factory()->create([
            'avatar_path' => null,
            'google_avatar' => null,
            'github_avatar' => '//avatars.githubusercontent.com/u/12345?v=4',
        ]);

        $this->assertSame('https://avatars.githubusercontent.com/u/12345?v=4', $user->avatar);
    }
}
