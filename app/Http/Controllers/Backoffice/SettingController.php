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
        'Generali'              => ['restaurant_name', 'cover_charge', 'preconto_printer_id'],
        'Cassa Automatica'      => ['cash_drawer_integration', 'cash_drawer_ip'],
        'Pagamento Elettronico' => ['pos_integration', 'revolut.environment', 'revolut.api_key', 'revolut.location_id', 'revolut.webhook_secret', 'revolut.timeout_seconds', 'revolut.mock_mode'],
        'Dati Azienda'          => ['company_vat_number', 'company_name', 'indirizzo_fatturazione', 'cap_fatturazione', 'comune_fatturazione', 'provincia_fatturazione', 'tel_fatturazione', 'email_fatturazione'],
        'Pagamenti & IVA'       => ['iban', 'istituto_finanziario', 'invoice_vat_rate', 'invoice_counter'],
        'Agenzia delle Entrate' => ['agenzia_entrate.enabled', 'agenzia_entrate.username', 'agenzia_entrate.password', 'agenzia_entrate.pincode', 'agenzia_entrate.utenza'],
        'Scontrini Elettronici' => ['corrispettivo_enabled', 'corrispettivo_provider', 'corrispettivo_timeout_seconds', 'corrispettivo_max_attempts', 'corrispettivo_printer_id'],
        'Configurazione Mysond' => ['corrispettivo_aliquota_iva_default', 'corrispettivo_mock'],
        'Configurazione Ditron' => ['ditron_agent_url', 'ditron_agent_token', 'ditron_agent_timeout_seconds', 'ditron_default_reparto', 'ditron_default_tender', 'ditron_tender_contanti', 'ditron_tender_pos', 'ditron_cover_label'],
        'Sistema'               => ['deploy_git_user', 'carlov_url'],
    ];

    private const GROUP_ICONS = [
        'Generali'              => 'fa-utensils',
        'Cassa Automatica'      => 'fa-cash-register',
        'Pagamento Elettronico' => 'fa-credit-card',
        'Dati Azienda'          => 'fa-building',
        'Pagamenti & IVA'       => 'fa-file-invoice',
        'Agenzia delle Entrate' => 'fa-landmark',
        'Scontrini Elettronici' => 'fa-receipt',
        'Configurazione Mysond' => 'fa-cloud',
        'Configurazione Ditron' => 'fa-network-wired',
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
            $input    = $request->all();

            foreach ($settings as $setting) {
                $default = $setting->type === 'boolean' ? '0' : $setting->value;

                // Lookup robusto:
                //  1. Chiave letterale (es. 'pos_integration')
                //  2. Variante con underscore al posto del punto: PHP converte i nomi
                //     dei form-urlencoded sostituendo i dot ('revolut.api_key' →
                //     $_POST['revolut_api_key']).
                //  3. Default = valore corrente (boolean → '0' perché un checkbox non
                //     spuntato non invia il valore; il pattern hidden+checkbox copre
                //     comunque il caso, ma teniamo il default conservativo).
                $value = $input[$setting->key]
                      ?? $input[str_replace('.', '_', $setting->key)]
                      ?? $default;

                // Pattern hidden+checkbox per i boolean: il form invia *due* valori
                // con lo stesso name ('0' e '1'); jQuery li serializza come array
                // (`name[]=0&name[]=1`). Prendi l'ultimo valore — semantica HTML
                // form standard: last wins.
                if (is_array($value)) {
                    $value = end($value);
                }

                if (is_array($value) || is_object($value)) {
                    continue;
                }

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
