<?php

namespace App\Http\Controllers\Backoffice;

use App\Interfaces\AllergenInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllergenController extends BaseController
{
    use DatatableTrait;

    protected AllergenInterface $interface;
    protected string $name;
    public function __construct(
        AllergenInterface $interface,
    )
    {
        $this->interface = $interface;
        $this->name = 'allergens';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.allergens')
                ->rawColumns(['dishes', 'printer'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request) : View {
        try {
            return view('backoffice.' . $this->name . '.create');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $request->validate([
                'label' => 'required',
            ]);
            $store = $request->all();
            $item = $this->interface->store($store);
            return $this->success(['item' => $item->toArray()]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }
    public function show(int $id, Request $request) : View {
        try {
            $object = $this->interface->find($id);
            if ($object->id) {
                return view('backoffice.' . $this->name . '.edit', compact('object'));
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function edit(int $id, Request $request) : JsonResponse {
        try {
            $request->validate([
                'label' => 'required',
            ]);
            $item = $this->interface->find($id);
            if ($item->id) {
                $store = $request->all();
                if ($this->interface->edit($item, $store)) {
                    return $this->success(['user' => $item->toArray()]);
                }
                throw new Exception('Element not updated');
            }
            throw new Exception('Element not found');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
