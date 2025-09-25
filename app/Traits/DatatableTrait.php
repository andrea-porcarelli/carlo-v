<?php

namespace App\Traits;

trait DatatableTrait
{
    public function editColumns($datatableModel, $model, array $options = [], $modal = null) {
        return $datatableModel->addColumn('action', function ($item) use($model, $options, $modal) {
            return view('backoffice.components.datatable', [
                'item' => $item,
                'model' => $model,
                'options' => $options,
                'modal' => $modal ?? '',
            ]);
        });
    }

}
