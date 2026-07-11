<?php

namespace App\Http\Controllers;

use App\Jobs\PrintTableReservationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint chiamato da Misuraca quando una prenotazione tavolo viene
 * confermata (auto-conferma sotto la soglia pax, oppure approvazione
 * manuale dell'admin per prenotazioni sopra soglia). Mette in coda
 * PrintTableReservationJob sulla coda `printers` (stessi worker
 * supervisor di tutte le altre stampe).
 *
 * POST /webhook/table-reservation?key=<TABLE_RESERVATION_PRINT_KEY>
 * Body JSON: vedi PrintTableReservationJob per i campi attesi.
 *
 * CSRF-exempt via pattern `webhook/*` in bootstrap/app.php.
 *
 * La shared key è distinta da quella delle cooking class ma con
 * fallback: se `TABLE_RESERVATION_PRINT_KEY` non è impostata,
 * viene usata `COOKING_BOOKING_PRINT_KEY` — così i deploy esistenti
 * continuano a funzionare senza toccare le env.
 */
class TableReservationPrintController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = (string) (env('TABLE_RESERVATION_PRINT_KEY') ?: env('COOKING_BOOKING_PRINT_KEY', ''));
        $provided = (string) ($request->query('key') ?? $request->header('X-Table-Reservation-Key', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Table reservation print webhook: invalid key', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'reference' => 'required|string|max:64',
            'reservation_date' => 'required|string|max:32',
            'slot_time' => 'required|string|max:16',
            'adults' => 'required|integer|min:0',
            'children' => 'nullable|integer|min:0',
            'total_pax' => 'nullable|integer|min:1',
            'special_requests' => 'nullable|string|max:2000',
            'customer_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'country_code' => 'nullable|string|max:2',
        ]);

        Log::info('Table reservation print request received', [
            'reference' => $data['reference'],
            'reservation_date' => $data['reservation_date'],
            'slot_time' => $data['slot_time'],
            'total_pax' => $data['total_pax'] ?? ((int) $data['adults'] + (int) ($data['children'] ?? 0)),
            'last_name' => $data['last_name'] ?? null,
            'ip' => $request->ip(),
        ]);

        PrintTableReservationJob::dispatch($data);

        return response()->json(['ok' => true, 'queued' => true], 202);
    }
}
