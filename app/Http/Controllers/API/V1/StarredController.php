<?php

namespace App\Http\Controllers\API\V1;

use App\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StarredController extends ApiController
{
    const TAG_NAME = 'starred';

    public function index(Request $request)
    {
        $perPage = 20;

        $tag = $this->getStarTag();

        $files = $tag->files()->wherePivot('user_id', $request->user()->id)->paginate($perPage);

        return JsonResource::collection($files);
    }

    /**
     * Attach "starred" tag to specified entries.
     */
    public function add(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|exists:files,id',
        ]);

        $ids = $request->input('ids');
        $tag = $this->getStarTag();
        $tag->attachFile($ids, $request->user()->id);

        return $this->respondWithMessage('Successfully added to stared.');
    }

    /**
     * Detach "starred" tag from specified entries.
     */
    public function remove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|exists:files,id',
        ]);

        $ids = $request->input('ids');
        $tag = $this->getStarTag();

        $tag->detachFile($ids, $request->user()->id);

        return $this->respondWithMessage('Successfully removed from stared.');
    }

    protected function getStarTag()
    {
        $tag = Tag::where('name', self::TAG_NAME)->first();

        return $tag ?: $this->createStarTag();
    }

    protected function createStarTag()
    {
        return Tag::firstOrCreate([
            'name' => self::TAG_NAME,
            'description' => 'Stared files for quick access',
            'type' => 'custom',
        ]);
    }
}
