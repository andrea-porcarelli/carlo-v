<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\MappingProduct;
use App\Models\Material;
use App\Models\Supplier;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MappingProductController extends BaseController
{
    use DatatableTrait;

    protected string $name;

    public function __construct()
    {
        $this->name = 'mapping-products';
    }

    /**
     * Display a listing of mapping products
     */
    public function index(): View
    {
        $suppliers = Supplier::orderBy('company_name')->pluck('company_name', 'id');
        return view('backoffice.' . $this->name . '.index', compact('suppliers'));
    }

    /**
     * Get datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = MappingProduct::with(['material', 'supplier'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['supplier_id'])) {
                $query->where('supplier_id', $filters['supplier_id']);
            }

            if (!empty($filters['search'])) {
                $query->where('product_name', 'like', '%' . $filters['search'] . '%');
            }

            $elements = $query->get();

            return $this->editColumns(
                datatables()->of($elements),
                $this->name,
                ['edit', 'remove'],
                null,
                'mapping-products'
            )
                ->addColumn('supplier_name', function ($item) {
                    return $item->supplier ? $item->supplier->company_name : '<span class="text-muted">(nessuno)</span>';
                })
                ->addColumn('material_label', function ($item) {
                    return $item->material ? $item->material->label : '<span class="text-danger">non trovato</span>';
                })
                ->addColumn('quantity_multiplier', function ($item) {
                    return $item->quantity_multiplier ?? '-';
                })
                ->rawColumns(['supplier_name', 'material_label', 'action'])
                ->make(true);

        } catch (Exception $e) {
            Log::error('Error in MappingProductController datatable: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified mapping product
     */
    public function show($id): View
    {
        $mappingProduct = MappingProduct::findOrFail($id);
        $materials = Material::orderBy('label')->get();
        return view('backoffice.' . $this->name . '.edit', compact('mappingProduct', 'materials'));
    }

    /**
     * Update the specified mapping product
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $mappingProduct = MappingProduct::findOrFail($id);

            $validated = $request->validate([
                'product_name' => 'required|string|max:255',
                'material_id' => 'required|integer|exists:materials,id',
                'quantity_multiplier' => 'nullable|numeric|min:0',
            ], [
                'product_name.required' => 'Il nome del prodotto è obbligatorio',
                'product_name.max' => 'Il nome del prodotto non può superare 255 caratteri',
                'material_id.required' => 'Il materiale è obbligatorio',
                'material_id.exists' => 'Il materiale selezionato non esiste',
                'quantity_multiplier.numeric' => 'Il moltiplicatore deve essere un numero',
                'quantity_multiplier.min' => 'Il moltiplicatore non può essere negativo',
            ]);

            DB::beginTransaction();

            $mappingProduct->update([
                'product_name' => $validated['product_name'],
                'material_id' => $validated['material_id'],
                'quantity_multiplier' => $validated['quantity_multiplier'],
            ]);

            DB::commit();

            return $this->success([
                'message' => 'Mappatura aggiornata con successo',
                'mapping_product' => $mappingProduct,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating mapping product: ' . $e->getMessage());
            return $this->error(['message' => 'Errore nell\'aggiornamento della mappatura']);
        }
    }

    /**
     * Remove the specified mapping product
     */
    public function destroy($id): JsonResponse
    {
        try {
            $mappingProduct = MappingProduct::findOrFail($id);

            DB::beginTransaction();

            $mappingProduct->delete();

            DB::commit();

            return $this->success(['message' => 'Mappatura eliminata con successo']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting mapping product: ' . $e->getMessage());
            return $this->error(['message' => 'Errore nell\'eliminazione della mappatura']);
        }
    }
}
