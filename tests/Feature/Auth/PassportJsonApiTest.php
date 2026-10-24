<?php

namespace Tests\Feature\Auth;

use App\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * W3.1: the first-party Passport JSON API routes (Passport 8 registered them
 * by default; Passport 13 gates them behind Passport::$registersJsonApiRoutes)
 * restored for the admin passport pages (0-usage feature). Every surface is
 * owner-scoped to the session user.
 */
class PassportJsonApiTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        $this->actingAs($this->user, 'web');
    }

    public function test_scopes_endpoint_returns_the_registry(): void
    {
        // the legacy app never registered tokensCan() scopes, so the list is
        // naturally empty — assert the endpoint contract (auth'd 200 array)
        $response = $this->getJson('/oauth/scopes')->assertOk();

        $this->assertIsArray($response->json());
    }

    public function test_clients_crud_is_owner_scoped(): void
    {
        // create
        $created = $this->postJson('/oauth/clients', [
            'name' => 'Drive CLI',
            'redirect' => 'https://drive.test/redirect',
        ])->assertStatus(201);

        $clientId = $created->json('id');
        $this->assertNotEmpty($clientId);

        // list shows the owner's client
        $this->getJson('/oauth/clients')
            ->assertOk()
            ->assertJsonPath('0.id', $clientId)
            ->assertJsonPath('0.name', 'Drive CLI')
            ->assertJsonPath('0.redirect_uris.0', 'https://drive.test/redirect');

        // update
        $this->putJson("/oauth/clients/{$clientId}", [
            'name' => 'Drive CLI v2',
            'redirect' => 'https://drive.test/redirect-2',
        ])->assertOk()->assertJsonPath('name', 'Drive CLI v2');

        // a foreign session cannot manage someone else's client
        $foreign = User::factory()->create();

        $this->actingAs($foreign, 'web')
            ->getJson('/oauth/clients')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($foreign, 'web')
            ->putJson("/oauth/clients/{$clientId}", [
                'name' => 'hijacked',
                'redirect' => 'https://evil.test/x',
            ])->assertNotFound();

        // back to the owner session before destroying
        $this->actingAs($this->user, 'web');

        // destroy
        $this->deleteJson("/oauth/clients/{$clientId}")->assertNoContent();

        $this->getJson('/oauth/clients')->assertOk()->assertJsonCount(0);
    }

    public function test_personal_access_tokens_flow(): void
    {
        $this->ensurePersonalAccessClient();

        $issued = $this->user->createToken('test-drive-pat');
        $issuedToken = $issued->token;
        $issuedToken->refresh();
        $tokenRow = $issuedToken;

        $list = $this->getJson('/oauth/personal-access-tokens')->assertOk();

        $this->assertEquals($tokenRow->id, $list->json('0.id'));
        $this->assertSame('test-drive-pat', $list->json('0.name'));

        // foreign session sees an empty list (owner scope)
        $foreign = User::factory()->create();

        $this->actingAs($foreign, 'web')
            ->getJson('/oauth/personal-access-tokens')
            ->assertOk()
            ->assertJsonCount(0);

        // back to the owner session before revoking
        $this->actingAs($this->user, 'web');

        // destroy revokes the token
        $this->deleteJson("/oauth/personal-access-tokens/{$tokenRow->id}")->assertNoContent();

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenRow->id,
            'revoked' => true,
        ]);
    }

    public function test_tokens_endpoint_lists_users_oauth_access_tokens(): void
    {
        $this->ensurePersonalAccessClient();

        $this->user->createToken('app-access');

        $response = $this->getJson('/oauth/tokens')->assertOk();

        $this->assertIsArray($response->json());
    }

    public function test_guests_are_kicked_from_the_json_api(): void
    {
        $this->app->forgetInstance('auth');

        $this->getJson('/oauth/clients')->assertUnauthorized();
        $this->getJson('/oauth/scopes')->assertUnauthorized();
    }
}
