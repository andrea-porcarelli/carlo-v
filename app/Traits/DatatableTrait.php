<?php

namespace App\Traits;

trait DatatableTrait
{
    public function editColumns($datatableModel, $model, array $options = [], $modal = null, $route = null) {
        return $datatableModel->addColumn('action', function ($item) use($model, $options, $modal, $route) {
            return view('backoffice.components.datatable', [
                'item' => $item,
                'model' => $model,
                'options' => $options,
                'modal' => $modal ?? '',
                'route' => $route ?? null,
            ]);
        });
    }

}
