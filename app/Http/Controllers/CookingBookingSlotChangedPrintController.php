<?php

namespace App\Http\Controllers;

use App\Jobs\PrintCookingBookingSlotChangedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint chiamato da Misuraca quando l'admin sposta una booking di
 * cooking class su un altro slot. Mette in coda
 * PrintCookingBookingSlotChangedJob sulla coda `printers`.
 *
 * POST /webhook/cooking-booking-slot-changed?key=<COOKING_BOOKING_PRINT_KEY>
 * Body JSON: vedi PrintCookingBookingSlotChangedJob per i campi attesi.
 *
 * CSRF-exempt via pattern `webhook/*` in bootstrap/app.php. Riusa la stessa
 * shared key dei webhook cooking booking — è la medesima integrazione,
 * solo un evento diverso nel ciclo di vita della booking.
 */
class CookingBookingSlotChangedPrintController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = (string) env('COOKING_BOOKING_PRINT_KEY', '');
        $provided = (string) ($request->query('key') ?? $request->header('X-Cooking-Booking-Key', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Cooking booking slot-changed webhook: invalid key', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'reference' => 'required|string|max:64',
            'class_title' => 'nullable|string|max:255',
            'old_date' => 'nullable|string|max:32',
            'old_start' => 'nullable|string|max:16',
            'old_end' => 'nullable|string|max:16',
            'new_slot_start' => 'nullable|string|max:64',
            'new_slot_end' => 'nullable|string|max:64',
            'pax' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:2000',
        ]);

        PrintCookingBookingSlotChangedJob::dispatch($data);

        return response()->json(['ok' => true, 'queued' => true], 202);
    }
}
