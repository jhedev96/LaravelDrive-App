<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a delete/trash/restore action on one or more entries.
 * The FE refreshes the affected folder view.
 */
class FileEntriesChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>  $hashes  hash ids of the touched entries
     * @param  string  $action  trash|restore|deleteforever
     */
    public function __construct(
        public array $hashes,
        public string $action,
        public ?int $userId,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if (! $this->userId) {
            return [];
        }

        return [
            new PrivateChannel("drive.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'entries.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'entries' => $this->hashes,
        ];
    }
}
