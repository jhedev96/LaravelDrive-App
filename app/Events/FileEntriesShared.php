<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when entries get shared with users so the recipients'
 * shared-with-me views stay live.
 */
class FileEntriesShared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>  $hashes  hash ids of the shared entries
     * @param  array<int, int>  $userIds  recipients
     * @param  int  $permissions  permissions bitmask
     */
    public function __construct(
        public array $hashes,
        public array $userIds,
        public int $permissions,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId) => new PrivateChannel("drive.{$userId}"),
            $this->userIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'entries.shared';
    }

    public function broadcastWith(): array
    {
        return [
            'entries' => $this->hashes,
            'permissions' => $this->permissions,
        ];
    }
}
