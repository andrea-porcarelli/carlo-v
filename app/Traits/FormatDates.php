<?php

namespace App\Traits;

use App\Facades\Utils;

trait FormatDates
{

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->formatDates) && $value) {
            return Utils::data($value);
        }

        return $value;
    }
}
