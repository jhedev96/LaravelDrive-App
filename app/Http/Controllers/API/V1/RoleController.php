<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\RoleRequest;
use App\Repositories\RoleRepository;
use App\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleController extends ApiController
{
    protected RoleRepository $role;

    public function __construct(RoleRepository $role)
    {
        $this->role = $role;
    }

    public function index()
    {
        $this->authorize('view', Role::class);

        return JsonResource::collection($this->role->getList());
    }

    public function show($id)
    {
        $this->authorize('view', Role::class);

        return new JsonResource($this->role->getById($id));
    }

    public function store(RoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->only(['name', 'description', 'status', 'permissions']);

        return new JsonResource($this->role->store($data));
    }

    public function update(RoleRequest $request, $id)
    {
        $this->authorize('view', Role::class);

        $data = $request->only(['name', 'description', 'status', 'permissions']);

        return new JsonResource($this->role->update($id, $data));
    }

    public function destroy($id)
    {
        if (auth()->user()->id == $id) {
            return $this->errorUnauthorized("You can't delete for yourself and other Administrators!");
        }

        $this->role->destroy($id);

        return $this->noContent();
    }

    public function attachUser(Request $request)
    {
        $role_id = $request->input('role_id');
        $user_id = $request->input('user_id');

        $this->role->attachUser($role_id, $user_id);

        return $this->respondWithMessage('Role attached with the user');
    }

    public function getAbilities()
    {
        $permissions = [];

        $permissions[] = [
            'model' => 'Super',
            'permissions' => config('admin.auth.permissions.super'),
        ];

        $policies = Gate::policies();

        if (is_array($policies)) {
            foreach ($policies as $model => $policy) {
                $permissions[] = [
                    'model' => $model,
                    'permissions' => $this->getPolicyMethods($model, $policy),
                ];
            }
        }

        $abilities = Gate::abilities();

        if (is_array($abilities)) {
            $permissions[] = [
                'model' => 'Abilities',
                'permissions' => array_keys($abilities),
            ];
        }

        return $this->respondWithCollection(collect($permissions));
    }

    protected function getPolicyMethods($model, $policy)
    {
        $name = (new \ReflectionClass($model))->getShortName();
        $methods = get_class_methods($policy);
        $data = [];

        if (is_array($methods)) {
            foreach ($methods as $method) {
                if ($method === 'before') {
                    continue;
                }
                $data[] = strtolower($name).'.'.$method;
            }
        }

        return $data;
    }
}
