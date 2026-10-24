<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use App\Http\Requests\FileRequest;
use App\Events\FileEntryCreated;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class FileController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perpage = $request->get('per_page') ?: 30;
        $type = $request->get('type');
        $search = $request->get('search');
        $parent_id = $request->get('parent_id');

        if ($parent_id) {
            $parent_id = File::decodeHash($parent_id);
        }

        $files = File::with('uploader')
            ->where('parent_id', $parent_id ?: 0)
            ->where('uploaded_by', $user->id)
            ->orderByRaw('type = ? desc', ['folder']);

        if ($type) {
            $files = $files->where('type', $type);
        }

        if ($search) {
            $files = $files->where('name', 'like', "%$search%");
        }

        $files = $files->paginate($perpage);

        return JsonResource::collection($files);
    }

    public function show(Request $request, File $file)
    {
        $file->load('uploader');

        return new JsonResource($file);
    }

    public function store(Request $request)
    {
        $inputs = $request->all();
        $inputs['meta'] = [
            'sizes' => $this->getDefaultSizes(),
            'permissions' => $this->getFilePermissions($request),
        ];
        $inputs['user_id'] = $request->user()->id;

        // W3.4: optional per-upload storage driver (legacy contract kept — the
        // parameter was never validated/served before). Unknown disk names are
        // rejected instead of being silently coerced to the default disk.
        $request->validate([
            'driver' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('filesystems.disks'))),
            ],
            'driver_data' => 'sometimes|array',
        ]);

        // create the file receiver
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

        // check if the upload is successful, throw exception otherwise
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        // receive the file
        $save = $receiver->receive();

        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            $incoming = $save->getFile();

            // storage quota gate (D18): reject before persisting anything
            app(StorageQuota::class)->enforce($request->user(), (int) $incoming->getSize());

            $filedata = $this->getFileData($incoming, $inputs);
            // the legacy app never persisted file_size at upload (NULL rows),
            // which broke every downstream size-based feature (quota) — fixed
            $filedata['file_size'] = (int) $incoming->getSize();
            $fileEntry = File::create($filedata);

            $this->storeLocalUpload($fileEntry, $incoming);
            $fileEntry->generatePath();

            // W3.2: live-refresh for the owner's drive channel (Reverb)
            FileEntryCreated::dispatch($fileEntry);

            // UploadToCloud::dispatch($fileEntry)->delay(now()->addMinutes(10));

            return new JsonResource($fileEntry);
        }

        // we are in chunk mode, send back the current progress
        $handler = $save->handler();

        return response()->json([
            'done' => $handler->getPercentageDone(),
            'status' => true,
        ]);
    }

    public function update(FileRequest $request, File $file)
    {
        $data = $request->only(['name', 'description']);

        $file->fill($data);
        $file->save();
        $file->refresh();

        return new JsonResource($file);
    }

    public function destroy(File $file)
    {
        $file->delete();

        return response()->json(['success' => true, 'message' => 'File deleted successfully.']);
    }

    public function destroyAll(Request $request)
    {
        $this->validate($request, [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        File::whereIn('id', $request->input('ids'))->delete();

        return response()->json(['success' => true, 'message' => 'Files deleted successfully.']);
    }

    protected function getDefaultSizes()
    {
        return [
            '120x80' => false,
        ];
    }

    protected function getFilePermissions(Request $request)
    {
        return [
            'public' => true,
        ];
    }

    public function getFileData(UploadedFile $file, $extra)
    {
        $entries = collect();

        if (Arr::get($extra, 'path')) {
            $entries = $entries->merge($this->createPath($extra['path'], $extra['parent_id'], $extra['user_id']));
            $parent = $entries->last();
            if ($parent) {
                $extra['parent_id'] = $parent->id;
            }
        }

        $originalMime = $file->getMimeType();

        if ($originalMime === 'application/octet-stream') {
            $originalMime = $file->getClientMimeType();
        }

        $file_name = Arr::get($extra, 'file_name') ?: Str::random(36);

        return [
            'name' => Arr::get($extra, 'name', $file->getClientOriginalName()),
            'file_name' => $file_name,
            'mime' => $originalMime,
            'type' => $this->getTypeFromMime($originalMime),
            'extension' => $this->getExtension($file, $originalMime),
            'path' => Arr::get($extra, 'path'),
            'parent_id' => Arr::get($extra, 'parent_id') ?: 0,
            'public_path' => Arr::get($extra, 'public_path'),
            'driver' => Arr::get($extra, 'driver', config('filesystems.default')),
            'driver_data' => Arr::get($extra, 'driver_data'),
            'meta' => Arr::get($extra, 'meta'),
        ];
    }

    /**
     * Create all folders for the passed path (directories separated by "/"),
     * skipping anything that looks like a file name (contains a dot).
     */
    private function createPath($path, $parentId, $userId)
    {
        $path = collect(explode('/', $path));
        $path = $path->filter(fn ($name) => $name && ! Str::contains($name, '.'));

        if ($path->isEmpty()) {
            return $path;
        }

        return $path->reduce(function ($parents, $name) use ($parentId, $userId) {
            if (! $parents) {
                $parents = collect();
            }
            $parent = $parents->last();
            $values = [
                'type' => 'folder',
                'name' => $name,
                'file_name' => $name,
                'parent_id' => $parent ? $parent->id : $parentId,
                'uploaded_by' => $userId,
            ];

            // check if user already has a folder with that name and parent
            $folder = File::where('type', 'folder')
                ->where('name', $values['name'])
                ->where('parent_id', $values['parent_id'])
                ->first();

            if (! $folder) {
                $folder = File::create($values);
                $folder->generatePath();
            }

            return $parents->push($folder);
        });
    }

    private function getExtension(UploadedFile $file, $mime)
    {
        if ($extension = $file->getClientOriginalExtension()) {
            return $extension;
        }

        $pathinfo = pathinfo($file->getClientOriginalName());

        if (isset($pathinfo['extension'])) {
            return $pathinfo['extension'];
        }

        return explode('/', $mime)[1];
    }

    protected function getTypeFromMime($mime)
    {
        $default = explode('/', $mime)[0];

        switch ($mime) {
            case 'application/x-zip-compressed':
            case 'application/zip':
                return 'archive';
            case 'application/pdf':
                return 'pdf';
            case 'vnd.android.package-archive':
                return 'android package';
            case Str::contains($mime, 'xml'):
                return 'spreadsheet';
            default:
                return $default === 'application' ? 'file' : $default;
        }
    }

    public function createThumbanile(File $entry, UploadedFile $file, $size = '120x80')
    {
        if ($entry->type !== 'image') {
            return null;
        }

        $rs = explode('x', $size);
        if (count($rs) !== 2 || ! ctype_digit($rs[0]) || ! ctype_digit($rs[1])) {
            return null;
        }

        // create the resized version via Intervention Image 4
        $image = Image::read($file)->resize((int) $rs[0], (int) $rs[1]);
        $path = storage_path("app/uploads/{$entry->file_name}");

        $localFile = "{$path}/{$size}-{$entry->name}";
        $image->save($localFile);

        return $localFile;
    }

    /**
     * @param  File  $entry
     * @param  UploadedFile  $file
     */
    public function storePublicUpload(File $entry, UploadedFile $file, $file_name = null)
    {
        $this->moveFile($entry, $file, 'public', 'public', $file_name);
    }

    /**
     * @param  File  $entry
     * @param  UploadedFile  $file
     */
    public function storeLocalUpload(File $entry, UploadedFile $file, $file_name = null)
    {
        // W3.4: the row's `driver` decides the physical disk. Legacy always
        // forced `local`, silently clobbering the driver the request picked.
        $driver = $entry->driver;

        if (! in_array($driver, array_keys(config('filesystems.disks')), true)) {
            $driver = 'local';
        }

        $this->moveFile($entry, $file, 'public', $driver, $file_name);
    }

    /**
     * Save the uploaded file on the specified disk.
     *
     * @param  File  $entry
     * @param  UploadedFile  $file
     */
    public function moveFile(File $entry, UploadedFile $file, $public = 'public', $disk = null, $file_name = null)
    {
        $disk = $disk ?: config('filesystems.default');

        if (! $file_name) {
            $file_name = $entry->name;
        }

        Storage::disk($disk)->putFileAs($entry->file_name, $file, $file_name, ['visibility' => $public]);

        $entry->updatePublicPaths($disk);
    }
}
