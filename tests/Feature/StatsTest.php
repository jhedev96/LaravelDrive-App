<?php

namespace Tests\Feature;

use App\File;
use App\Role;
use App\User;
use Tests\TestCase;

/**
 * W5.2 backend half: GET /api/stats feeds the SPA dashboard with live
 * numbers — the legacy dashboard only ever showed hard-coded digits. Own
 * counts (files/folders/storage) for everyone; the user count is admin
 * only (legacy "administrator" permission semantics).
 */
class StatsTest extends TestCase
{
    public function test_own_counts_and_storage_are_live_for_the_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $file = $this->makeEntry($user, 'a.txt', 'file', 100);
        $this->makeEntry($user, 'b.txt', 'file', 300);
        $this->makeEntry($user, 'Documents', 'folder', 0);
        $otherEntry = $this->makeEntry($other, 'not-mine.txt', 'file', 9999);
        $this->makeEntry($user, 'gone.txt', 'file', 700)->delete();

        $this->actingAs($user, 'api')->getJson('/api/stats')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.files', 2)
            ->assertJsonPath('data.folders', 1)
            ->assertJsonPath('data.storage_bytes', 400)
            ->assertJsonPath('data.users', null);

        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertDatabaseHas('files', ['id' => $otherEntry->id]);
    }

    public function test_administrators_also_get_the_user_count(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'administrator')->firstOrFail());
        User::factory()->create();

        $total = User::query()->count();

        $this->actingAs($admin, 'api')->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.users', $total);
    }

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();
    }

    protected function makeEntry(User $uploader, string $name, string $type, int $size): File
    {
        return File::factory()->create([
            'uploaded_by' => $uploader->id,
            'name' => $name,
            'type' => $type,
            'extension' => $type === 'file' ? pathinfo($name, PATHINFO_EXTENSION) : '',
            'mime' => $type === 'file' ? 'text/plain' : '',
            'file_size' => $size,
        ]);
    }
}
