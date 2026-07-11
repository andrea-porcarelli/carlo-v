<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\DitronDailyClosure;
use App\Models\Setting;
use App\Services\DitronCloseDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class DitronCloseDayController extends Controller
{
    public function __construct(private DitronCloseDayService $service) {}

    /**
     * Esegue la chiusura Ditron per la giornata corrente. Solo admin.
     */
    public function run(): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Solo un amministratore può eseguire la chiusura Ditron.');
        }

        if ((string) Setting::get('corrispettivo_provider', 'mysond') !== 'ditron') {
            return back()->with('error', 'Provider corrispettivi non è "ditron": chiusura non applicabile.');
        }

        Log::channel('corrispettivi')->info('Chiusura Ditron avviata da backoffice', [
            'triggered_by_user_id' => $user->id,
        ]);

        try {
            $closure = $this->service->close(
                Carbon::today(),
                DitronDailyClosure::SOURCE_MANUAL,
                $user->id,
            );
        } catch (Throwable $e) {
            return back()->with('error', 'Errore durante la chiusura: ' . $e->getMessage());
        }

        if ($closure->isDone()) {
            return back()->with('success', "Chiusura Ditron eseguita ({$closure->elapsed_ms}ms).");
        }

        return back()->with('error', 'Chiusura Ditron fallita: ' . ($closure->last_error ?? 'errore sconosciuto'));
    }
}
