<?php

namespace App\Repositories;

use App\Interfaces\SupplierInterface;
use App\Models\Supplier;
use App\Repositories\CrudRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierRepository extends CrudRepository implements SupplierInterface
{

    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters = []): Builder
    {
        $builder = $this->builder();

        return $builder;
    }

    /**
     * Al salvataggio, se `ignore_mapping` passa da false a true, marca come
     * ignorate anche tutte le fatture esistenti del fornitore non ancora
     * ignorate. Al contrario false → true non riattiva le vecchie fatture
     * (evita cambi retroattivi indesiderati sui totali storici).
     */
    public function edit(Model $object, array $update): bool
    {
        $wasIgnored = (bool) $object->ignore_mapping;
        $willBeIgnored = array_key_exists('ignore_mapping', $update)
            ? (bool) $update['ignore_mapping']
            : $wasIgnored;

        $result = parent::edit($object, $update);

        if ($result && !$wasIgnored && $willBeIgnored) {
            $object->invoices()
                ->whereNull('ignored_at')
                ->update(['ignored_at' => now()]);
        }

        return $result;
    }
}
