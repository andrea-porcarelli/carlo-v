<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;

class Product extends LogsModel
{

    use LogsActivity;

    public $fillable = [
        'label',
    ];
}
