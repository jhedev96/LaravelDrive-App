<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\UserRequest;
use App\Repositories\UserRepository;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserController extends ApiController
{
    protected UserRepository $user;

    public function __construct(UserRepository $user)
    {
        $this->user = $user;
    }

    public function index()
    {
        $this->authorize('view', User::class);

        return JsonResource::collection($this->user->page());
    }

    public function show($id)
    {
        $this->authorize('view', User::class);

        return new JsonResource($this->user->getById($id));
    }

    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->only([
            'firstname',
            'lastname',
            'name',
            'email',
            'password',
            'role',
            'avatar',
            'permissions',
        ]);

        $data['ip'] = $request->ip();

        return new JsonResource($this->user->store($data));
    }

    public function update(UserRequest $request, $id)
    {
        $this->authorize('update', User::class);

        $data = $request->only([
            'firstname',
            'lastname',
            'name',
            'email',
            'password',
            'role',
            'avatar',
            'permissions',
            'status',
        ]);

        return new JsonResource($this->user->update($id, $data));
    }

    /**
     * Update user status by user id.
     */
    public function status($id, Request $request)
    {
        $this->authorize('update', User::class);

        if (auth()->user()->id == $id) {
            return $this->errorUnauthorized("You can't change status for yourself and other Administrators!");
        }

        $this->user->update($id, $request->all());

        return $this->noContent();
    }

    public function destroy($id)
    {
        $this->authorize('delete', User::class);

        if (auth()->user()->id == $id) {
            return $this->errorUnauthorized("You can't delete for yourself and other Administrators!");
        }

        $this->user->destroy($id);

        return $this->respondWithMessage('Successfully deleted');
    }

    /**
     * Delete multiple users.
     */
    public function deleteMultiple(Request $request)
    {
        $this->authorize('delete', User::class);

        $this->validate($request, [
            'ids' => 'required|array|min:1',
        ]);

        $this->user->deleteMultiple($request->input('ids'));

        return $this->respondWithMessage('Successfully deleted');
    }

    public function search(Request $request)
    {
        $this->authorize('view', User::class);

        $this->validate($request, [
            's' => 'required|string|min:1',
        ]);

        $query = $request->input('s');

        return JsonResource::collection($this->user->searchUser($query));
    }
}
