<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrudRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(string $id): Model
    {
        $element = $this->builder()->find($id);
        if (isset($element->id)) {
            return $element;
        }
        abort(404);
    }

    public function builder(): Builder
    {
        return $this->model->query();
    }

    public function store(array $store): Model
    {
        return $this->model->create($store);
    }

    public function edit(Model $object, array $update): bool
    {
        return $object->update($update);
    }

    public function remove(int $id): bool
    {
        $model = $this->find($id);
        if ($model->id) {
            $model->delete();
            return true;
        }
        return false;
    }

    public function for_select() : array {
        return $this->model->get()->map(function ($item) {
            return ['id' => $item->id, 'label' => $item->label, 'is_active' => $item->is_active ?? 1];
        })->toArray();
    }

    public function updateOrCreate(array $update, array $create) : Model {
        return $this->model->updateOrCreate($update, $create);
    }
}
