<?php

namespace App\Http\Controllers\API\V1;

use App\Folder;
use App\Http\Requests\FolderRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderController extends ApiController
{
    public function index()
    {
        $folders = Folder::all();

        return JsonResource::collection($folders);
    }

    public function show(Folder $folder)
    {
        return new JsonResource($folder);
    }

    public function store(FolderRequest $request)
    {
        $data = $request->only(['name', 'description', 'parent_id']);
        $data['parent_id'] = isset($data['parent_id']) ? intval($data['parent_id']) : 0;
        $data['file_name'] = $data['name'];

        $folder = Folder::create($data);
        $folder->generatePath();

        return new JsonResource($folder);
    }

    public function update(FolderRequest $request, Folder $folder)
    {
        $data = $request->only(['name', 'description']);
        $data['file_name'] = $data['name'];

        $folder->fill($data);
        $folder->save();

        return new JsonResource($folder);
    }

    public function destroy(Folder $folder)
    {
        $folder->delete();

        return response()->json(['success' => true, 'message' => 'Folder deleted successfully.']);
    }
}
