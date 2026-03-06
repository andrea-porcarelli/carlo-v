<?php

namespace App\Http\Controllers\Backoffice;

use App\Interfaces\MenuOptionInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuOptionController extends BaseController
{
    use DatatableTrait;

    protected MenuOptionInterface $interface;
    protected string $name;

    public function __construct(MenuOptionInterface $interface)
    {
        $this->interface = $interface;
        $this->name = 'menu-options';
    }

    public function index(Request $request): View
    {
        $type = $request->get('type', 'extra');
        return view('backoffice.' . $this->name . '.index', compact('type'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'status'], null, 'restaurant.menu-options')
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request): View
    {
        try {
            $type = $request->get('type', 'extra');
            return view('backoffice.' . $this->name . '.create', compact('type'));
        } catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'label' => 'required',
                'type' => 'required|in:extra,removal',
                'price' => 'required_if:type,extra|nullable|numeric|min:0',
            ]);
            $store = $request->all();
            $item = $this->interface->store($store);
            return $this->success(['item' => $item->toArray()]);
        } catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function show(int $id, Request $request): View
    {
        try {
            $object = $this->interface->find($id);
            if ($object->id) {
                return view('backoffice.' . $this->name . '.edit', compact('object'));
            }
            throw new Exception('Element not found');
        } catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function edit(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'label' => 'required',
                'type' => 'required|in:extra,removal',
                'price' => 'required_if:type,extra|nullable|numeric|min:0',
            ]);
            $item = $this->interface->find($id);
            if ($item->id) {
                $store = $request->all();
                if ($this->interface->edit($item, $store)) {
                    return $this->success(['item' => $item->toArray()]);
                }
                throw new Exception('Element not updated');
            }
            throw new Exception('Element not found');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function status(int $id): JsonResponse
    {
        try {
            $model = $this->interface->find($id);
            $this->interface->edit($model, ['is_active' => !$model->is_active]);
            return response()->json(['response' => 'success']);
        } catch (\Exception $e) {
            return $this->exception($e, null);
        }
    }
}
