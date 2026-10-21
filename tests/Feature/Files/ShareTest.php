<?php

namespace Tests\Feature\Files;

use App\File;
use App\ShareableLink;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ShareTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    public function test_shareable_link_can_be_created_and_read_back(): void
    {
        $entry = File::factory()->create(['uploaded_by' => $this->user->id, 'name' => 'sharable.jpg']);

        $created = $this->postJson("/api/shareable-links/file/{$entry->id}")
            // freshly minted JsonResource → 201 (same parity as uploads)
            ->assertStatus(201)
            ->json('data');

        // FE contract: a consumable `link` URL is exposed; hashed internals stay hidden
        $this->assertNotEmpty($created['link']);
        $this->assertStringStartsWith((string) url('/'), $created['link']);
        $this->assertArrayNotHasKey('hash', $created);

        // reading an existing link must not mint a new one
        $again = $this->getJson("/api/shareable-links/file/{$entry->id}")
            ->assertOk()->json('data');

        $this->assertSame($created['link'], $again['link']);
        $this->assertSame(1, ShareableLink::query()->where('file_id', $entry->id)->count());
    }

    public function test_shareable_link_password_is_hashed_and_masked(): void
    {
        $entry = File::factory()->create(['uploaded_by' => $this->user->id]);

        $created = $this->postJson("/api/shareable-links/file/{$entry->id}", ['password' => 's3cret'])
            ->assertStatus(201)->json('data');

        $this->assertArrayNotHasKey('password', $created);

        $row = ShareableLink::query()->where('file_id', $entry->id)->firstOrFail();

        $this->assertNotSame('s3cret', $row->password);
        $this->assertTrue(Hash::check('s3cret', $row->password));
    }

    public function test_shareable_link_can_be_disabled_and_deleted(): void
    {
        $entry = File::factory()->create(['uploaded_by' => $this->user->id]);

        $link = $this->postJson("/api/shareable-links/file/{$entry->id}", ['allow_download' => true])
            ->assertStatus(201)->json('data');

        $this->putJson("/api/shareable-links/{$link['id']}", ['allow_download' => false])
            ->assertOk();

        $row = ShareableLink::query()->where('file_id', $entry->id)->firstOrFail();
        $this->assertFalse((bool) $row->allow_download);

        $this->deleteJson("/api/shareable-links/{$link['id']}")->assertOk();
        $this->assertSame(0, ShareableLink::query()->where('file_id', $entry->id)->count());
    }

    public function test_public_shareable_page_renders_globally(): void
    {
        $this->get('/file/s/whatever-hash')->assertOk();
    }

    public function test_owner_can_share_entries_with_users(): void
    {
        $entry = File::factory()->create(['uploaded_by' => $this->user->id]);
        $other = User::factory()->create();

        $this->postJson('/api/shares/add-users', [
            'userIds' => [$other->id],
            'permissions' => 1,
            'fileids' => [$entry->id],
        ])->assertOk();

        $this->assertDatabaseHas('file_user', [
            'file_id' => $entry->id,
            'user_id' => $other->id,
        ]);

        // the recipient sees the entry on the shared-with-me listing
        Passport::actingAs($other);

        $this->getJson('/api/shared/files')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $entry->name);
    }

    public function test_share_with_users_of_a_foreign_entry_is_stopped(): void
    {
        $foreign = File::factory()->create(); // uploaded_by = null (unowned row)
        $other = User::factory()->create();

        Passport::actingAs($other);

        $this->postJson('/api/shares/add-users', [
            'userIds' => [$this->user->id],
            'permissions' => 1,
            'fileids' => [$foreign->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('file_user', [
            'file_id' => $foreign->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_administrator_serves_as_super_user_for_shares(): void
    {
        $foreign = File::factory()->create();
        // raw array (not a pre-serialized string): the model mutator serializes it
        $admin = User::factory()->create(['permissions' => ['administrator']]);

        Passport::actingAs($admin);

        $this->postJson('/api/shares/add-users', [
            'userIds' => [$this->user->id],
            'permissions' => 1,
            'fileids' => [$foreign->id],
        ])->assertOk();
    }
}
