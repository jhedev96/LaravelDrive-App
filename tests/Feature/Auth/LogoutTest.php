<?php

namespace Tests\Feature\Auth;

use App\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    public function test_logout_revokes_all_issued_api_tokens(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();

        // a REAL token row (requires the seeded personal access client);
        // Passport::actingAs would only build a mocked in-memory token
        $this->ensurePersonalAccessClient();

        $token = $user->createToken('drive session')->accessToken;
        $this->assertEquals(1, $user->tokens()->count());

        $this->actingAs($user, 'web')->postJson('/logout')
            ->assertStatus(204);

        // every previously issued API token must be gone
        $this->assertEquals(0, $user->tokens()->count());

        // ...and the issued token no longer authenticates the API
        $this->withToken($token)
            ->getJson('/api/file')
            ->assertStatus(401);
    }
}
