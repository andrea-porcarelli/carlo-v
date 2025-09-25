<?php

namespace App\Traits;


use Illuminate\View\View;

trait ControllerData
{
    protected function view($view, $data = []) : View
    {
        return view($view, array_merge($data, [
            'title' => $this->title ?? null,
            'route' => $this->route ?? null,
        ]));
    }
}
