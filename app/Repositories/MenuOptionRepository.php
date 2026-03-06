<?php

namespace App\Repositories;

use App\Interfaces\MenuOptionInterface;
use App\Models\MenuOption;
use Illuminate\Database\Eloquent\Builder;

class MenuOptionRepository extends CrudRepository implements MenuOptionInterface
{
    public function __construct(MenuOption $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();

        if (!empty($filters['type'])) {
            $builder->where('type', $filters['type']);
        }

        if (!empty($filters['mixed'])) {
            $builder->where('label', 'like', '%' . $filters['mixed'] . '%');
        }

        return $builder;
    }
}
