<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\PrinterInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrinterController extends BaseController
{
    use DatatableTrait;

    protected PrinterInterface $interface;
    protected string $name;
    public function __construct(
        PrinterInterface $interface,
    )
    {
        $this->interface = $interface;
        $this->name = 'printers';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'status'], null, 'restaurant.printers')
                ->addColumn('categories', function ($item) {
                   return $item->categories->count();
                })
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
                'ip' => 'required',
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
            $user = $this->interface->find($id);
            if ($user->id) {
                return view('backoffice.' . $this->name . '.edit', [
                    'object' => $user,
                ]);
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
                'ip' => 'required',
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

    public function status(int $id): JsonResponse
    {
        try {
            $model = $this->interface->find($id);
            $this->interface->edit($model, ["is_active" => !$model->is_active]);
            return response()->json(['response' => 'success']);
        } catch (\Exception $e) {
            return $this->exception($e, null);
        }
    }
}
