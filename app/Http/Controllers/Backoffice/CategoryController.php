<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\CategoryInterface;
use App\Interfaces\PrinterInterface;
use App\Models\Printer;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends BaseController
{
    use DatatableTrait;

    protected CategoryInterface $interface;
    protected string $name;
    public function __construct(
        CategoryInterface $interface,
    )
    {
        $this->interface = $interface;
        $this->name = 'categories';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'status'], null, 'restaurant.categories')
                ->addColumn('dishes', function ($item) {
                   return $item->dishes->count();
                })
                ->addColumn('printer', function ($item) {
                   return $item->printer->label . '<br /><small>IP: ' . $item->printer->ip . '</small>';
                })
                ->rawColumns(['dishes', 'printer'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request) : View {
        try {
            $printers = Utils::map_collection(Printer::where('is_active', 1));
            return view('backoffice.' . $this->name . '.create', compact('printers'));
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $request->validate([
                'label' => 'required',
                'printer_id' => 'required',
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
                $printers = Utils::map_collection(Printer::where('is_active', 1));
                return view('backoffice.' . $this->name . '.edit', compact('object' , 'printers'));
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
                'printer_id' => 'required',
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
