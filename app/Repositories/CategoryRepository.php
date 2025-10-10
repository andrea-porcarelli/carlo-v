<?php

namespace App\Repositories;

use App\Interfaces\CategoryInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Category;

class CategoryRepository extends CrudRepository implements CategoryInterface
{

    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
