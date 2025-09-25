<?php

namespace App\Traits;

use App\Facades\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

trait Filterable
{
    public function filter(Builder $builder, array $filters = []) : Builder
    {
        return $builder
            ->when(isset($filters['label']), function($q) use($filters) {
                $q->whereHas('languages', function($q)  use($filters) {
                    $q->where('label', 'like', '%' . $filters['label']. '%')
                        ->whereHas('language', function($q) {
                            $q->where('iso_code', Utils::default_language());
                        });
                });
            })
            ->when(isset($filters['company_id']), fn($q) => $q->where('company_id', $filters['company_id']))
            ->when(in_array(Auth::user()->role, ['god', 'admin', 'operator']), fn($q) => $q->where('company_id', Session::get('company_id')));
    }

}
