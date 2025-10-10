<?php

namespace App\Repositories;

use App\Interfaces\PrinterInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Printer;

class PrinterRepository extends CrudRepository implements PrinterInterface
{

    public function __construct(Printer $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
