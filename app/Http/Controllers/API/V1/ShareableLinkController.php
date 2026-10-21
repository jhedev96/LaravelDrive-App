<?php

namespace App\Http\Controllers\API\V1;

use App\ShareableLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareableLinkController extends ApiController
{
    public function getFileShareableLink(Request $request, $fileId)
    {
        $link = ShareableLink::where('file_id', $fileId)->first();

        if (! $link) {
            return $this->errorNotFound('Link not found!');
        }

        return $this->shareableLink($link);
    }

    public function storeFileShareableLink(Request $request, $fileId)
    {
        $link = ShareableLink::firstOrNew(['file_id' => $fileId]);

        $params = $request->all();
        $params['user_id'] = $request->user()->id;
        $params['file_id'] = $fileId;
        $params['hash'] = $link->hash ?: Str::random(30);

        if ($request->has('password')) {
            $params['password'] = $request->password ? Hash::make($request->password) : null;
        }

        $link->fill($params);
        $link->save();

        return $this->shareableLink($link);
    }

    protected function shareableLink(ShareableLink $link, $withFile = false)
    {
        if ($withFile) {
            $link->load('file', 'file.children', 'file.uploader');
        }

        return new JsonResource($link);
    }

    public function show(Request $request, $fileId)
    {
        $link = ShareableLink::where('file_id', $fileId)->firstOrFail();

        if (! $link || ! $link->file || $link->file->trashed()) {
            abort(404);
        }

        if ($request->input('withFile')) {
            $link->load('file.children', 'file.uploader');
        }

        return new JsonResource($link);
    }

    public function update(Request $request, ShareableLink $link)
    {
        $params = $request->all();
        $params['user_id'] = $request->user()->id;

        if ($request->has('password')) {
            $params['password'] = $request->password ? Hash::make($request->password) : null;
        }

        $link->fill($params);
        $link->save();

        return new JsonResource($link);
    }

    public function destroy(Request $request, ShareableLink $link)
    {
        $link->delete();

        return response()->json(['success' => true, 'message' => 'link deleted successfully.']);
    }

    public function check(Request $request, ShareableLink $link)
    {
        $this->validate($request, [
            'password' => 'required|min:1',
        ]);

        if (strlen((string) $link->password) === 40 && Hash::check($request->password, $link->password) === false) {
            return $this->errorUnauthorized('You do not have permission!!');
        }

        return $this->shareableLink($link, true);
    }
}
