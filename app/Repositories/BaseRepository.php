<?php

namespace App\Repositories;

trait BaseRepository
{
    /**
     * Get number of records.
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->model->count();
    }

    /**
     * Update columns in the record by id.
     *
     * @param  mixed  $id
     * @param  array  $input
     * @return bool
     */
    public function updateColumn($id, $input)
    {
        $this->model = $this->getById($id);

        foreach ($input as $key => $value) {
            $this->model->{$key} = $value;
        }

        return $this->model->save();
    }

    /**
     * Destroy a model.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function destroy($id)
    {
        return $this->getById($id)->delete();
    }

    /**
     * Get model by id.
     */
    public function getById($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Get all the records.
     */
    public function all()
    {
        return $this->model->get();
    }

    /**
     * Get paginated records.
     */
    public function page($number = 10, $sort = 'desc', $sortColumn = 'created_at')
    {
        return $this->model->orderBy($sortColumn, $sort)->paginate($number);
    }

    /**
     * Store a new record.
     *
     * @param  array  $input
     * @return mixed
     */
    public function store($input)
    {
        return $this->save($this->model, $input);
    }

    /**
     * Update a record by id.
     *
     * @param  mixed  $id
     * @param  array  $input
     * @return mixed
     */
    public function update($id, $input)
    {
        $this->model = $this->getById($id);

        return $this->save($this->model, $input);
    }

    public function setAttributes($model, $input)
    {
        $fillable = $model->getFillable();

        foreach ($input as $key => $value) {
            if (in_array($key, $fillable)) {
                $model->$key = $value;
            }
        }

        return $model;
    }

    /**
     * Save the input's data.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $input
     * @return mixed
     */
    public function save($model, $input)
    {
        $model->fill($input);

        if ($model->save()) {
            return $model;
        }

        return false;
    }
}
