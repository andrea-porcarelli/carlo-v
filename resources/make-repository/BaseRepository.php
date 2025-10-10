<?php

namespace App\Repositories;

use App\Interfaces\BaseInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Model;

class BaseRepository extends CrudRepository implements BaseInterface
{

    public function __construct(BaseModel $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
