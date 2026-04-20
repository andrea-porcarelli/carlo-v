<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Models\Printer;
use App\Models\Setting;
use App\Services\MysondFatturaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SettingController extends BaseController
{
    /**
     * Setting groups definition: key => list of setting keys in display order.
     */
    private const GROUPS = [
        'Generali'              => ['cover_charge', 'preconto_printer_id', 'cash_drawer_printer_id'],
//        'Terminale POS'         => ['pos_terminal_ip', 'pos_terminal_port'],
        'Dati Azienda'          => ['company_vat_number', 'company_name', 'indirizzo_fatturazione', 'cap_fatturazione', 'comune_fatturazione', 'provincia_fatturazione', 'tel_fatturazione', 'email_fatturazione'],
        'Pagamenti & IVA'       => ['iban', 'istituto_finanziario', 'invoice_vat_rate', 'invoice_counter'],
        'Agenzia delle Entrate' => ['agenzia_entrate.username', 'agenzia_entrate.password', 'agenzia_entrate.pincode', 'agenzia_entrate.utenza'],
        'Sistema'               => ['deploy_git_user', 'carlov_url'],
    ];

    private const GROUP_ICONS = [
        'Generali'              => 'fa-utensils',
//        'Terminale POS'         => 'fa-credit-card',
        'Dati Azienda'          => 'fa-building',
        'Pagamenti & IVA'       => 'fa-file-invoice',
        'Agenzia delle Entrate' => 'fa-landmark',
        'Sistema'               => 'fa-server',
    ];

    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');

        $printers = Printer::where('is_active', true)->get()->map(function ($printer) {
            return ['id' => $printer->id, 'label' => $printer->label];
        })->toArray();

        // Build grouped settings, preserving order; ungrouped settings go to "Altro"
        $grouped = [];
        $assignedKeys = collect(self::GROUPS)->flatten()->all();

        foreach (self::GROUPS as $group => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if ($settings->has($key)) {
                    $items[] = $settings->get($key);
                }
            }
            if ($items) {
                $grouped[$group] = $items;
            }
        }

        $ungrouped = $settings->except($assignedKeys)->values()->all();
        if ($ungrouped) {
            $grouped['Altro'] = $ungrouped;
        }

        $groupIcons = self::GROUP_ICONS + ['Altro' => 'fa-cog'];

        return view('backoffice.settings.index', compact('grouped', 'groupIcons', 'printers'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $settings = Setting::all();

            foreach ($settings as $setting) {
                $value = $request->input($setting->key, $setting->type === 'boolean' ? '0' : $setting->value);
                Setting::set($setting->key, $value, $setting->type, $setting->description);
            }

            return $this->success();
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function adeCambioPassword(): View
    {
        $utenza         = Utils::setting('agenzia_entrate.utenza');
        $changedAtIso   = Setting::get('agenzia_entrate.password_changed_at');
        $changedAt      = $changedAtIso ? Carbon::parse($changedAtIso)->format('d/m/Y H:i') : null;
        $daysSinceChange = $changedAtIso ? Carbon::parse($changedAtIso)->diffInDays(now()) : null;

        return view('backoffice.settings.ade-cambio-password', compact('utenza', 'changedAt', 'daysSinceChange'));
    }

    public function adeCambioPasswordStore(Request $request, MysondFatturaService $service): JsonResponse
    {
        $data = $request->validate([
            'utenza'            => ['required', 'string'],
            'vecchia_password'  => ['required', 'string'],
            'nuova_password'    => ['required', 'string', 'min:8', 'different:vecchia_password'],
            'conferma_password' => ['required', 'string', 'same:nuova_password'],
        ]);

        try {
            $result = $service->cambiaPasswordAde(
                $data['utenza'],
                $data['vecchia_password'],
                $data['nuova_password'],
                $data['conferma_password'],
            );
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }

        $esito       = isset($result->esito) ? (int) $result->esito : null;
        $codice      = $result->codice ?? null;
        $descrizione = $result->descrizione ?? ($result->messaggio ?? null);

        if ($esito !== 0) {
            return $this->error([
                'message' => $descrizione ?: 'Cambio password non riuscito',
                'codice'  => $codice,
                'esito'   => $esito,
            ]);
        }

        Setting::set('agenzia_entrate.password', $data['nuova_password']);
        Setting::set('agenzia_entrate.password_changed_at', now()->toIso8601String());

        return $this->success([
            'response'    => 'success',
            'message'     => $descrizione ?: 'Password aggiornata correttamente',
            'codice'      => $codice,
            'esito'       => $esito,
        ]);
    }
}
