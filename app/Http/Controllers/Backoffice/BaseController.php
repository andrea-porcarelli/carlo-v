<?php

namespace App\Http\Controllers\Backoffice;


use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class BaseController extends Controller
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function exception(Exception $e, $request = null) : JsonResponse|View {
        Log::info((Auth::check() ? '[User ID: ' . Auth::id() . '] ' : '[Session ID: ' . Session::getId() . '] ') . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine(), ['trace' => $e->getTrace()]);
        if (!is_null($request) && $request->expectsJson()) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => isset($e->validator) ? $e->validator->getMessageBag() : '',
                'trace' => $e->getTraceAsString()
            ], 422);
        }
        return view('error', [
            'message' => (Auth::check() ? '[User ID: ' . Auth::id() . '] ' : '[Session ID: ' . Session::getId() . '] ') . ': ' . $e->getMessage(). ' in ' . $e->getFile() . ' at line ' . $e->getLine(),
            'errors' => $e->getTrace(),
            'message_bag' => isset($e->validator) ? $e->validator->getMessageBag() : ''
        ]);
    }

    public function success(array $response = ['response' => 'success']) : JsonResponse {
        return response()->json($response);
    }

    public function error(array $response) : JsonResponse {
        return response()->json($response, 422);
    }


}
