<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $customers = Customer::where('full_name', 'LIKE', "%{$q}%")
            ->orWhere('fiscal_code', 'LIKE', "%{$q}%")
            ->orWhere('vat_number', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get();

        return response()->json(['data' => $customers]);
    }
}
