<?php

namespace App\Events;

use App\File;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a new drive entry (file/folder) became persistent.
 * The FE listens on the owner's private drive channel to live-refresh
 * the opened folder view.
 */
class FileEntryCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public File $entry)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if (! $this->entry->uploaded_by) {
            return [];
        }

        return [
            new PrivateChannel("drive.{$this->entry->uploaded_by}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'entry.created';
    }

    public function broadcastWith(): array
    {
        return [
            'entry' => [
                'hash' => $this->entry->hash,
                'id' => $this->entry->id,
                'name' => $this->entry->name,
                'type' => $this->entry->type,
                'mime' => $this->entry->mime,
                'extension' => $this->entry->extension,
                'file_size' => (int) $this->entry->file_size,
                'parent_id' => $this->entry->parent_id,
            ],
        ];
    }
}
