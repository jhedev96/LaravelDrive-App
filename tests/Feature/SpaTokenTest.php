<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W4.21: SPA bearer bootstrap. The legacy FE armed /api/* only with a
 * token stored by a login THROUGH the SPA — any other session path left
 * the drive page 401-ing the whole API surface. POST /spa-token mints a
 * personal access token for the session user.
 */
class SpaTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // createToken() needs a personal access client in the test DB
        $this->ensurePersonalAccessClient();
    }

    public function test_the_session_user_can_mint_a_bearer_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/spa-token');

        $response->assertOk();
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        // Passport 13 mints JWTs (3 url-safe segments)
        $this->assertStringContainsString('.', $token);
        // JWT = 3 segments separated by 2 dots
        $this->assertSame(2, substr_count($token, '.'));

        // the minted token actually authenticates an api-guarded surface
        $this->withToken($token)->getJson('/api/stats')->assertOk();
    }

    public function test_guests_cannot_mint_spa_tokens(): void
    {
        $this->postJson('/spa-token')->assertUnauthorized();
    }
}
