<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\OperationalIncident;
use App\Models\User;
use App\Support\OperationalErrorCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Backoffice: dashboard degli incidenti operativi.
 * Solo admin — filtra per categoria/severity/data e permette di marcare
 * gli incidenti come acknowledged o resolved.
 */
class OperationalIncidentController extends Controller
{
    public function index(Request $request)
    {
        $this->assertAdmin();

        $query = OperationalIncident::with(['user:id,name', 'acknowledger:id,name', 'tableOrder.restaurantTable'])
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('code')) {
            $query->where('code', $request->code);
        }
        if ($request->filled('state')) {
            match ($request->state) {
                'unack'    => $query->whereNull('acknowledged_at'),
                'unres'    => $query->whereNull('resolved_at'),
                'resolved' => $query->whereNotNull('resolved_at'),
                default    => null,
            };
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $incidents = $query->paginate(50)->withQueryString();

        $topCodes = OperationalIncident::selectRaw('code, count(*) as total')
            ->whereBetween('created_at', [now()->subDays(30), now()])
            ->groupBy('code')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $availableCodes = collect(OperationalErrorCode::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->value])
            ->toArray();

        return view('backoffice.operational-incidents.index', [
            'incidents'      => $incidents,
            'topCodes'       => $topCodes,
            'availableCodes' => $availableCodes,
            'categories'     => ['print', 'ditron', 'cashdrawer', 'other'],
            'severities'     => ['info', 'warn', 'error', 'critical'],
        ]);
    }

    public function acknowledge(Request $request, OperationalIncident $incident): RedirectResponse
    {
        $this->assertAdmin();
        if ($incident->acknowledged_at === null) {
            $incident->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
            ])->save();
        }
        return back()->with('success', "Incidente #{$incident->id} marcato come letto.");
    }

    public function resolve(Request $request, OperationalIncident $incident): RedirectResponse
    {
        $this->assertAdmin();
        $updates = ['resolved_at' => now()];
        if ($incident->acknowledged_at === null) {
            $updates['acknowledged_at'] = now();
            $updates['acknowledged_by'] = Auth::id();
        }
        $incident->forceFill($updates)->save();
        return back()->with('success', "Incidente #{$incident->id} marcato come risolto.");
    }

    private function assertAdmin(): User
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Solo un amministratore può accedere alla dashboard degli incidenti operativi.');
        }
        return $user;
    }
}
