<?php

namespace Tests\Feature\Broadcast;

use App\Events\FileEntriesChanged;
use App\Events\FileEntriesShared;
use App\Events\FileEntryCreated;
use App\File;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    public function test_upload_broadcasts_entry_created_to_the_owner_channel(): void
    {
        Event::fake([FileEntryCreated::class]);

        $response = $this->post('/api/file', [
            'file' => UploadedFile::fake()->createWithContent('fresh.jpg', 'JPEGDATA'),
            'dzuuid' => 'aa2e54b2-aaaa-bbbb-cccc-333344445556',
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 1,
            'dztotalfilesize' => 8,
            'dzchunksize' => 8,
            'dzchunkbyteoffset' => 0,
            'parent_id' => 0,
            'path' => '',
        ]);

        $response->assertStatus(201);

        $owner = (int) $response->json('data.uploaded_by');

        Event::assertDispatched(FileEntryCreated::class, function (FileEntryCreated $event) use ($owner) {
            return $event->broadcastAs() === 'entry.created'
                && collect($event->broadcastOn())
                    ->contains(fn ($channel) => $channel->name === "private-drive.{$owner}")
                && $event->broadcastWith()['entry']['name'] === 'fresh.jpg';
        });
    }

    public function test_delete_actions_broadcast_the_touched_hashes(): void
    {
        Event::fake([FileEntriesChanged::class]);

        $entry = File::factory()->create(['uploaded_by' => $this->user->id]);
        Storage::fake('local');
        Storage::disk('local')->put($entry->getStoragePath(), 'DATA');

        $this->delete('/api/file/delete', ['ids' => [$entry->id]])->assertOk();

        Event::assertDispatched(function (FileEntriesChanged $event) use ($entry) {
            return $event->broadcastAs() === 'entries.changed'
                && in_array($entry->hash, $event->broadcastWith()['entries'], true)
                && $event->broadcastWith()['action'] === 'trash';
        });

        // deleting forever broadcasts the same event with a different action
        Event::assertNotDispatched(FileEntriesChanged::class, fn (FileEntriesChanged $event) => $event->action === 'deleteforever');

        $this->delete('/api/file/delete', ['ids' => [$entry->id], 'action' => 'deleteforever'])->assertOk();

        Event::assertDispatched(FileEntriesChanged::class, fn (FileEntriesChanged $event) => $event->action === 'deleteforever');
    }

    public function test_sharing_broadcasts_to_the_recipient_channels(): void
    {
        Event::fake([FileEntriesShared::class]);

        $entry = File::factory()->create(['uploaded_by' => $this->user->id]);
        $recipient = User::factory()->create();

        $this->postJson('/api/shares/add-users', [
            'userIds' => [$recipient->id],
            'permissions' => 1,
            'fileids' => [$entry->id],
        ])->assertOk();

        Event::assertDispatched(FileEntriesShared::class, function (FileEntriesShared $event) use ($entry, $recipient) {
            $channels = collect($event->broadcastOn());

            return $event->broadcastAs() === 'entries.shared'
                && $event->broadcastWith()['entries'] === [$entry->hash]
                && $channels->contains(fn ($channel) => $channel->name === "private-drive.{$recipient->id}");
        });
    }

    public function test_drive_private_channel_authorization(): void
    {
        // tests force BROADCAST_CONNECTION=null (phpunit.xml) so the channel
        // closures live on the Null broadcaster; to exercise the real auth
        // flow, switch to a signing (pusher-emulated) connection first and
        // (re)register channels on it
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-app',
                'options' => ['cluster' => 'mt1', 'useTLS' => false],
            ],
        ]);

        require base_path('routes/channels.php');

        $user = $this->user;

        // broadcasting/auth is guarded by the web session guard
        $this->actingAs($user, 'web');

        // own channel is authorized (signed response)
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => "private-drive.{$user->id}",
        ])->assertOk()->assertJsonStructure(['auth']);

        // someone else's channel is not
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-drive.'.($user->id + 77),
        ])->assertStatus(403);
    }
}
