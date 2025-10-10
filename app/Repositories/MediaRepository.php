<?php

namespace App\Repositories;

use App\Interfaces\MediaInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Media;

class MediaRepository extends CrudRepository implements MediaInterface
{

    public function __construct(Media $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
