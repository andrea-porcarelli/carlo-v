<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\SupplierInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends BaseController
{
    use DatatableTrait;

    protected SupplierInterface $interface;

    public function __construct(
        SupplierInterface $interface,
    )
    {
        $this->interface = $interface;
    }

    public function index() : View {
        return view('backoffice.suppliers.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), 'suppliers', ['edit'])
                ->addColumn('invoices', function ($item) {
                   return $item->invoices()->count();
                })
                ->rawColumns(['referer'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function show(int $id, Request $request) : View {
        try {
            $user = $this->interface->find($id);
            if ($user->id) {
                return view('backoffice.suppliers.edit', [
                    'object' => $user,
                ]);
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function update(int $id, Request $request) : JsonResponse {
        try {
            $request->validate([
                'company_name' => 'required',
                'fiscal_code' => 'required',
                'vat_number' => 'required',
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

    public function create(Request $request) : View {
        try {
            return view('backoffice.suppliers.create');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $request->validate([
                'company_name' => 'required',
                'fiscal_code' => 'required',
                'vat_number' => 'required',
            ]);
            $store = $request->all();
            $item = $this->interface->store($store);
            return $this->success(['item' => $item->toArray()]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
