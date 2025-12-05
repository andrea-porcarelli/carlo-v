<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierInvoiceInterface;
use App\Models\MappingProduct;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceProduct;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends BaseController
{
    use DatatableTrait;

    protected SupplierInvoiceInterface $interface;
    protected SupplierInterface $supplier;
    protected string $name;
    public function __construct(
        SupplierInvoiceInterface $interface,
        SupplierInterface $supplier,
    )
    {
        $this->interface = $interface;
        $this->supplier = $supplier;
        $this->name = 'invoices';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            // Associa material_stocks ai prodotti delle fatture mappate
            $this->associateMaterialStocks();

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'mapping-product'])
                ->addColumn('supplier_name', function ($item) {
                   return $item->supplier->extended_name;
                })
                ->addColumn('amount', function ($item) {
                   return Utils::price($item->amount);
                })
                ->addColumn('invoice_number', function ($item) {
                   return $item->invoice_number . '<br /><small>' . $item->filename. '</small>';
                })
                ->addColumn('invoice_date', function ($item) {
                   return Utils::data($item->invoice_date);
                })
                ->addColumn('products', function ($item) {
                   return $item->products_count;
                })
                ->addColumn('mapping', function ($item) {
                   return $item->products()->whereDoesntHave('material')->where('ignore_mapping', 0)->count() . ' / ' . $item->products()->whereHas('material')->count() . ' / ' . $item->products()->whereHas('stock')->count();
                })
                ->rawColumns(['invoice_number', 'supplier_name'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
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

    public function import_form() : JsonResponse
    {
        $entity = SupplierInvoice::class;
        return $this->success([
            'html' => view('backoffice.invoices.upload-invoice', compact('entity'))->render()
        ]);
    }

    public function import_invoice(Request $request) : JsonResponse
    {

        try {
            $response = self::extract_invoice_data($request->get('file'));

            if ($response['error']) {
                throw new Exception($response['message']);
            }

            return $this->success([
                'html' => view('backoffice.invoices.import-completed', ['name' => $response['name']])->render()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(['message' => "Errore durante l'importazione della fattura: " . $e->getMessage()]);
        }
    }

    public function mapping_products(int $id) : View
    {
        $invoice = SupplierInvoice::findOrFail($id);

        $supplierInvoiceProducts = $invoice->products()
            ->whereDoesntHave('material')
            ->where('ignore_mapping', 0)
            ->orderBy('id')
            ->get();

        $materials = Material::orderBy('label')->get();

        return view('backoffice.invoices.map-products', compact(
            'invoice',
            'supplierInvoiceProducts',
            'materials'
        ));
    }

    public function store_mapping_products(Request $request, $invoiceId)
    {

        $request->validate([
            'mappings' => 'required|array',
            'mappings.*' => [
                'nullable',
                Rule::in([0]),
                function ($attribute, $value, $fail) {
                    if ($value != 0 && !Material::where('id', $value)->exists()) {
                        $fail("Il materiale selezionato non esiste.");
                    }
                }
            ]
        ]);

        $invoice = $this->interface->find($invoiceId);
        $mappings = $request->input('mappings', []);

        DB::beginTransaction();

        try {
            foreach ($mappings as $productId => $materialId) {
                $product = $invoice->products()->find($productId);
                if ($materialId === '0') {
                    $product->update(['ignore_mapping' => 1]);
                } else {
                    if (!$product) {
                        continue;
                    }
                    if ($materialId) {
                        MappingProduct::create([
                            'material_id' => $materialId,
                            'product_name' => $product->product_name, // Variazione (può essere positiva o negativa)
                        ]);
                    }
                }
            }

            DB::commit();

            return $this->success();

        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    private function extract_invoice_data($file) : array
    {
        try {
            DB::beginTransaction();
            $original_name = $file['name'];
            $filename = $file['basename'];
            $fileContent = file_get_contents(storage_path() . '/app/private/invoices/' . $filename);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($fileContent);
            if ($xml === false) {
                throw new \Exception('File XML non valido');
            }

            // Estrai dati fornitore
            $supplier = $this->extractSupplier($xml);
            $invoice = $this->extractInvoice($xml, $supplier, $filename);
            $productsCount = $this->extractProducts($xml, $invoice);

            DB::commit();
            return ['error' => false, 'name' => $original_name];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['error' => true, 'message' => $e->getMessage()];
        }

    }

    private function extractSupplier($xml): Supplier
    {
        $cedente = $xml->FatturaElettronicaHeader->CedentePrestatore;
        $datiAnagrafici = $cedente->DatiAnagrafici;
        $anagrafica = $datiAnagrafici->Anagrafica;
        $sede = $cedente->Sede;

        // Gestisci denominazione o nome+cognome
        $companyName = (string)($anagrafica->Denominazione ?? '');
        if (!$companyName) {
            $nome = (string)($anagrafica->Nome ?? '');
            $cognome = (string)($anagrafica->Cognome ?? '');
            $companyName = trim($nome . ' ' . $cognome);
        }

        $vatNumber = str_replace('IT', '', (string)$datiAnagrafici->IdFiscaleIVA->IdCodice);

        $supplierData = [
            'vat_number' => $vatNumber,
            'fiscal_code' => (string)($datiAnagrafici->CodiceFiscale ?? $vatNumber),
            'company_name' => $companyName,
            'address' => (string)($sede->Indirizzo ?? ''),
            'number' => (string)($sede->NumeroCivico ?? ''),
            'zip_code' => (string)($sede->CAP ?? ''),
            'city' => (string)($sede->Comune ?? ''),
            'province' => (string)($sede->Provincia ?? ''),
            'nation' => (string)($sede->Nazione ?? 'IT'),
        ];

        return Supplier::firstOrCreate(
            ['vat_number' => $supplierData['vat_number']],
            $supplierData
        );
    }

    private function extractInvoice($xml, Supplier $supplier, string $filename): SupplierInvoice
    {
        $body = $xml->FatturaElettronicaBody;
        $datiDocumento = $body->DatiGenerali->DatiGeneraliDocumento;

        $invoiceNumber = (string)$datiDocumento->Numero;
        $invoiceDate = (string)$datiDocumento->Data;
        $totalAmount = (float)($datiDocumento->ImportoTotaleDocumento ?? 0);

        // Verifica duplicato
        $existingInvoice = SupplierInvoice::where('supplier_id', $supplier->id)
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if ($existingInvoice) {
            throw new \Exception("Fattura numero {$invoiceNumber} già presente nel sistema");
        }

        return SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => $invoiceNumber,
            'filename' => $filename,
            'amount' => $totalAmount,
            'invoice_date' => $invoiceDate,
        ]);
    }

    private function extractProducts($xml, SupplierInvoice $invoice): int
    {
        $body = $xml->FatturaElettronicaBody;
        $dettaglioLinee = $body->DatiBeniServizi->DettaglioLinee;

        $count = 0;

        foreach ($dettaglioLinee as $linea) {
            $quantity = (float)($linea->Quantita ?? 0);

            // Salta righe descrittive
            if ($quantity <= 0) {
                continue;
            }

            SupplierInvoiceProduct::create([
                'supplier_invoice_id' => $invoice->id,
                'product_name' => (string)$linea->Descrizione,
                'quantity' => $quantity,
                'price' => (float)($linea->PrezzoUnitario ?? 0),
                'iva' => (float)($linea->AliquotaIVA ?? 0),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Associa automaticamente i material_stocks ai prodotti delle fatture
     * in base ai mapping già esistenti nella tabella mapping_products
     */
    private function associateMaterialStocks(): void
    {
        // Recupera tutti i prodotti delle fatture che hanno un mapping material ma non hanno ancora uno stock
        $productsToAssociate = SupplierInvoiceProduct::whereHas('material')
            ->whereDoesntHave('stock')
            ->get();

        foreach ($productsToAssociate as $product) {
            // Recupera il material_id tramite la relazione material
            $material = $product->material;

            if ($material) {
                // Crea il record MaterialStock
                $materialStock = MaterialStock::firstOrCreate([
                    'supplier_invoice_product_id' => $product->id,
                ], [
                    'material_id' => $material->id,
                    'stock' => $product->quantity,
                ]);

                // Se il MaterialStock è stato appena creato, aggiorna lo stock del Material
                if ($materialStock->wasRecentlyCreated) {
                    $materialModel = Material::find($material->id);
                    if ($materialModel) {
                        $materialModel->increment('stock', $product->quantity);
                    }
                }
            }
        }
    }

}
