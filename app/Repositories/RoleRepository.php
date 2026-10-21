<?php

namespace App\Repositories;

use App\Role;

class RoleRepository
{
    use BaseRepository;

    protected Role $model;

    public function __construct(Role $role)
    {
        $this->model = $role;
    }

    public function getList()
    {
        return $this->model->orderBy('id', 'desc')->get();
    }

    public function getByName($name)
    {
        return $this->model->where('name', $name)->first();
    }

    public function getPermissions($id)
    {
        return $this->getById($id)->permissions;
    }

    public function attachUser($role_id, $user_id)
    {
        return $this->getById($role_id)->users()->syncWithoutDetaching([$user_id]);
    }
}
