<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Http\Controllers\Backoffice\Requests\StoreDishRequest;
use App\Interfaces\DishInterface;
use App\Interfaces\MaterialInterface;
use App\Models\Category;
use App\Models\Material;
use App\Models\Printer;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DishController extends BaseController
{
    use DatatableTrait;

    protected DishInterface $interface;
    protected string $name;
    public function __construct(
        DishInterface $interface,
    )
    {
        $this->interface = $interface;
        $this->name = 'dishes';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.dishes')
                ->addColumn('dish', function ($item) {
                   return $item->label . '<br /><small>' . $item->category->label . '</small>';
                })
                ->addColumn('ingredients', function ($item) {
                   return $item->ingredients_view;
                })
                ->addColumn('price', function ($item) {
                   return Utils::price($item->price);
                })
                ->addColumn('allergens', function ($item) {
                   return implode(', ', $item->allergens->pluck('label')->toArray());
                })
                ->rawColumns(['dish', 'ingredients'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request) : View {
        try {
            $categories = Utils::map_collection(Category::where('is_active', 1)->orderBy('label'));
            return view('backoffice.' . $this->name . '.create', compact('categories'));
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(StoreDishRequest $request) : JsonResponse {
        try {

            $ingredients = json_decode($request->get('materials'), true);
            $allergens = json_decode($request->get('allergens'), true);
            $ingredients_with_quantity = collect($ingredients)->filter(fn($ingredient) => $ingredient['quantity'] > 0);
            if ($ingredients_with_quantity->count() == 0) {
                return $this->error(['message' => 'Devi inserire almeno un ingrediente']);
            }
            $store = $request->except('ingredients', 'allergens');

            $item = $this->interface->store($store);
            if (isset($item)) {
                $item->allergens()->sync($allergens);
                $ingredients = $ingredients_with_quantity->mapWithKeys(fn($ingredient) => [$ingredient['id'] => ['quantity' => $ingredient['quantity']]]);

                $item->materials()->sync($ingredients);
            }

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
                $categories = Utils::map_collection(Category::where('is_active', 1)->orderBy('label'));
                return view('backoffice.' . $this->name . '.edit', compact('object' , 'categories'));
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function edit(int $id, StoreDishRequest $request) : JsonResponse {
        try {
            $ingredients = json_decode($request->get('materials'), true);
            $ingredients_with_quantity = collect($ingredients)->filter(fn($ingredient) => $ingredient['quantity'] > 0);
            if ($ingredients_with_quantity->count() == 0) {
                return $this->error(['message' => 'Devi inserire almeno un ingrediente']);
            }
            $item = $this->interface->find($id);
            if ($item->id) {
                $store = $request->validated();
                if ($this->interface->edit($item, $store)) {
                    if ($request->has('materials')) {
                        $materials = json_decode($request->materials, true);
                        $syncData = [];
                        foreach ($materials as $material) {
                            $syncData[$material['id']] = ['quantity' => $material['quantity']];
                        }
                        $item->materials()->sync($syncData);
                    } else {
                        $item->materials()->sync([]);
                    }
                    if ($request->has('allergens')) {
                        $allergens = json_decode($request->allergens, true);
                        $item->allergens()->sync($allergens);
                    } else {
                        $item->allergens()->sync([]);
                    }
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
