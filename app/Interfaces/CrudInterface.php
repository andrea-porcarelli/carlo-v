<?php
namespace App\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CrudInterface
{
    public function find(string $id) : Model;

    public function builder() : Builder;

    public function filters(array $filters) : Builder;

    public function store(array $store) : Model;

    public function edit(Model $object, array $update) : bool;

    public function remove(int $id) : bool;

    public function for_select() : array;

    public function updateOrCreate(array $update, array $create) : Model;
}
