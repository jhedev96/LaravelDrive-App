<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shell-page wiring for the frontend rebuild (W5 slice 1):
 * the blades feed window.LD and carry the Vite entry points, so the
 * controllers must hand them the data the SPA expects.
 */
class ShellPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_the_drive_shell(): void
    {
        $this->get('/drive')->assertRedirect(route('login'));
    }

    public function test_the_drive_shell_feeds_window_ld_with_the_authed_user(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/drive');

        $response->assertOk();
        $response->assertViewIs('drive.index');
        $response->assertViewHas('user', fn ($passed) => $passed instanceof User && $passed->is($user));
        $response->assertSee('window.LD');
    }

    public function test_the_public_share_shell_mounts_with_the_link_hash(): void
    {
        $this->withoutVite();

        $response = $this->get('/file/s/a-share-hash');

        $response->assertOk();
        $response->assertViewIs('sharedfiles');
        $response->assertViewHas('hash', 'a-share-hash');
        $response->assertSee('window.LD');
        $response->assertSee('share-root');
    }
}
