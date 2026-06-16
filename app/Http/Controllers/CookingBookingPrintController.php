<?php

namespace App\Http\Controllers;

use App\Jobs\PrintCookingBookingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint chiamato da Misuraca quando una booking di corso di cucina
 * passa a `paid`. Mette in coda PrintCookingBookingJob sulla coda
 * `printers` (stessi worker supervisor di tutte le altre stampe).
 *
 * POST /webhook/cooking-booking-paid?key=<COOKING_BOOKING_PRINT_KEY>
 * Body JSON: vedi PrintCookingBookingJob per i campi attesi.
 *
 * CSRF-exempt via pattern `webhook/*` in bootstrap/app.php.
 */
class CookingBookingPrintController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = (string) env('COOKING_BOOKING_PRINT_KEY', '');
        $provided = (string) ($request->query('key') ?? $request->header('X-Cooking-Booking-Key', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Cooking booking print webhook: invalid key', ['ip' => $request->ip()]);
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'reference' => 'required|string|max:64',
            'class_title' => 'nullable|string|max:255',
            'slot_start' => 'nullable|string|max:64',
            'slot_end' => 'nullable|string|max:64',
            'pax' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:2000',
            'total_cents' => 'required|integer|min:0',
            'currency' => 'nullable|string|max:8',
        ]);

        PrintCookingBookingJob::dispatch($data);

        return response()->json(['ok' => true, 'queued' => true], 202);
    }
}
