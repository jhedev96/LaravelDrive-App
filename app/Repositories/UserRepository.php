<?php

namespace App\Repositories;

use App\User;

class UserRepository
{
    use BaseRepository;

    protected User $model;

    public function __construct(User $user)
    {
        $this->model = $user;
    }

    /**
     * Get the list of all the users without myself.
     */
    public function getList()
    {
        return $this->model->orderBy('id', 'desc')->get();
    }

    public function getByName($name)
    {
        return $this->model->where('name', $name)->first();
    }

    public function page($number = 10, $sort = 'desc', $sortColumn = 'created_at')
    {
        return $this->model->orderBy($sortColumn, $sort)->paginate($number);
    }

    public function getById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function store($input)
    {
        return $this->save($this->model, $input);
    }

    public function update($id, $input)
    {
        $this->model = $this->getById($id);

        return $this->save($this->model, $input);
    }

    public function changePassword($user, $password)
    {
        return $user->update(['password' => $password]);
    }

    public function saveAvatar($id, $photo)
    {
        $user = $this->getById($id);

        $user->avatar = $photo;

        return $user->save();
    }

    public function destroy($id)
    {
        return $this->getById($id)->delete();
    }

    /**
     * Delete multiple users.
     *
     * @param  array  $ids
     * @return array
     */
    public function deleteMultiple($ids)
    {
        foreach ($ids as $id) {
            $user = $this->model->find($id);
            if (is_null($user)) {
                continue;
            }

            $user->roles()->detach();
            $user->delete();
        }

        return $ids;
    }

    public function searchUser($query)
    {
        return $this->model->where('name', 'like', "%$query%")
            ->orWhere('email', 'like', "%$query%")
            ->orWhere('firstname', 'like', "%$query%")
            ->orWhere('lastname', 'like', "%$query%")
            ->get();
    }
}
