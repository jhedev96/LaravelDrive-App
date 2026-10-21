<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\JsonResource;

class MoveFileController extends ApiController
{
    public function __invoke(Request $request)
    {
        $this->validate($request, [
            'files' => 'required|array|min:1',
            'files.*' => 'required|integer',
            'destination' => 'nullable|integer|exists:files,id',
        ]);

        $files = collect($request->input('files'));
        $destination = $request->input('destination');

        $entries = $this->getFiles($files);
        $newParent = $this->getNewParent($destination);
        $entries = $this->removeInvalidEntries($entries, $newParent);

        // there was an issue with entries or parent, bail
        if ($entries->isEmpty()) {
            return $this->errorWrongArgs();
        }

        if ($this->updateParent($destination, $entries)) {
            $entries = $this->getFiles($entries->pluck('id'));
        }

        return JsonResource::collection($entries);
    }

    /**
     * Make sure entries can't be moved into themselves or their children.
     *
     * @param  Collection  $entries
     * @param  File|int|null  $parent
     * @return Collection
     */
    private function removeInvalidEntries(Collection $entries, $parent)
    {
        if (! $parent) {
            return $entries;
        }

        return $entries->filter(function ($entry) use ($parent) {
            return ! Str::contains((string) $parent->path, (string) $entry->id);
        });
    }

    /**
     * @param  int|null  $destination
     * @return File|null
     */
    private function getNewParent($destination)
    {
        if (! $destination) {
            return null;
        }

        return File::select('path', 'id')->find($destination);
    }

    /**
     * @param  array  $ids
     * @return Collection
     */
    private function getFiles($ids)
    {
        return File::whereIn('id', $ids)->get();
    }

    /**
     * @param  int|null  $destination
     * @param  Collection  $files
     */
    private function updateParent($destination, Collection $files)
    {
        if (! $destination) {
            $destination = 0;
        }

        return File::whereIn('id', $files->pluck('id'))
            ->update(['parent_id' => $destination]);
    }
}
