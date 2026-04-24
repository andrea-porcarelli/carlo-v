<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\TableOrderCorrispettivo;
use App\Services\CorrispettivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class CorrispettivoController extends Controller
{
    public function __construct(private CorrispettivoService $service) {}

    /**
     * Nuovo tentativo di invio per un corrispettivo failed.
     */
    public function riprova(TableOrderCorrispettivo $corrispettivo): RedirectResponse
    {
        if (!$corrispettivo->canRetry()) {
            return back()->with('error', 'Il corrispettivo non è in uno stato ritentabile.');
        }

        Log::channel('corrispettivi')->info('Retry manuale da backoffice', $corrispettivo->getLogContext() + [
            'triggered_by_user_id' => Auth::id(),
        ]);

        try {
            $this->service->riprova($corrispettivo);
        } catch (Throwable $e) {
            return back()->with('error', 'Errore nel retry: ' . $e->getMessage());
        }

        $fresh = $corrispettivo->fresh();
        $message = $fresh->isSent()
            ? "Corrispettivo inviato. Progressivo SDI: {$fresh->progressivo_sdi}"
            : 'Retry eseguito ma il corrispettivo non risulta inviato. Verifica i log.';

        return back()->with($fresh->isSent() ? 'success' : 'warning', $message);
    }

    /**
     * Annullo manuale di un corrispettivo inviato.
     */
    public function annulla(TableOrderCorrispettivo $corrispettivo): RedirectResponse
    {
        if (!$corrispettivo->canCancel()) {
            return back()->with('error', 'Il corrispettivo non può essere annullato.');
        }

        Log::channel('corrispettivi')->info('Annullo manuale da backoffice', $corrispettivo->getLogContext() + [
            'triggered_by_user_id' => Auth::id(),
            'progressivo_sdi'      => $corrispettivo->progressivo_sdi,
        ]);

        try {
            $annullo = $this->service->annulla($corrispettivo, Auth::id());
        } catch (Throwable $e) {
            return back()->with('error', 'Errore nell\'annullo: ' . $e->getMessage());
        }

        $message = $annullo->isSent()
            ? "Corrispettivo {$corrispettivo->progressivo_sdi} annullato correttamente."
            : 'Richiesta di annullo non riuscita. Riprova dal pannello.';

        return back()->with($annullo->isSent() ? 'success' : 'warning', $message);
    }
}
