<?php

namespace App\Http\Controllers\API\V1;

use App\Response\DownloadResponse;
use App\ShareableLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareableLinkController extends ApiController
{
    public function __construct(private readonly DownloadResponse $downloadResponse)
    {
    }

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

    /**
     * W3.9 public surface (stateless — owner-approved): guest info,
     * password check and download behind /file/s/{hash}. The
     * route-model `check()` variant below was never registered (0-usage
     * legacy artifact keyed by id only) and is replaced by the hash-based
     * public methods.
     */
    public function publicLink(string $hash)
    {
        $link = $this->resolvePublicLink($hash);

        // password-gated links reveal nothing until the check passes
        if ($link->password) {
            return response()->json(['data' => [
                'requires_password' => true,
                'allows_download' => (bool) $link->allow_download,
            ]]);
        }

        return $this->shareableLink($link, true);
    }

    public function publicCheck(Request $request, string $hash)
    {
        $link = $this->resolvePublicLink($hash);

        $this->validate($request, [
            'password' => 'required|min:1',
        ]);

        if ($link->password && Hash::check((string) $request->input('password'), (string) $link->password) === false) {
            return $this->errorUnauthorized('You do not have permission!!');
        }

        return $this->shareableLink($link, true);
    }

    public function publicDownload(Request $request, string $hash)
    {
        $link = $this->resolvePublicLink($hash);

        if (! $link->allow_download) {
            return $this->errorForbidden('Downloading is not allowed for this link.');
        }

        if ($link->password) {
            $this->validate($request, ['password' => 'required|min:1']);

            if (Hash::check((string) $request->input('password'), (string) $link->password) === false) {
                return $this->errorUnauthorized('You do not have permission!!');
            }
        }

        return $link->file->type === 'folder'
            ? $this->downloadResponse->folderDownload($link->file)
            : $this->downloadResponse->singleDownload($link->file->hash);
    }

    /**
     * Shareable links live by hash: expired or entry-trashed links die with
     * a plain 404 (ModelNotFound already renders JSON under api/*).
     */
    private function resolvePublicLink(string $hash): ShareableLink
    {
        $link = ShareableLink::query()
            ->where('hash', $hash)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->firstOrFail();

        if (! $link->file || $link->file->trashed()) {
            abort(404);
        }

        return $link;
    }
}
