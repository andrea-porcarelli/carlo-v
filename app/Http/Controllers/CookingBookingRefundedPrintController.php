<?php

namespace App\Http\Controllers;

use App\Jobs\PrintCookingBookingRefundedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint chiamato da Misuraca dopo il rimborso di una booking di
 * cooking class (gateway refund riuscito e stato prenotazione = refunded).
 * Mette in coda PrintCookingBookingRefundedJob sulla coda `printers`.
 *
 * POST /webhook/cooking-booking-refunded?key=<COOKING_BOOKING_PRINT_KEY>
 * Body JSON: vedi PrintCookingBookingRefundedJob per i campi attesi.
 *
 * CSRF-exempt via pattern `webhook/*` in bootstrap/app.php. Riusa la stessa
 * shared key degli altri webhook cooking booking.
 */
class CookingBookingRefundedPrintController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = (string) env('COOKING_BOOKING_PRINT_KEY', '');
        $provided = (string) ($request->query('key') ?? $request->header('X-Cooking-Booking-Key', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Cooking booking refunded webhook: invalid key', ['ip' => $request->ip()]);

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
            'payment_provider' => 'nullable|string|max:32',
        ]);

        PrintCookingBookingRefundedJob::dispatch($data);

        return response()->json(['ok' => true, 'queued' => true], 202);
    }
}
