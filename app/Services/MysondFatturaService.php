<?php

namespace App\Services;

use App\Facades\Utils;
use SoapClient;
use Exception;
use Illuminate\Support\Facades\Log;

class MysondFatturaService
{
    protected $client;
    protected $auth;
    public $wsdl;
    protected $authAde;
    protected $endpoint;
    protected $endpoints = [
        'mysond'                          => 'https://cloud.mysond.it/service-ejb/FatturaElettronicaService?wsdl',
        'CorrispettivoElettronicoService' => 'https://api-cassa.eportale.eu/service-ejb/CorrispettivoElettronicoService',
    ];

    public function __construct()
    {
        $this->wsdl = 'mysond';
        $this->endpoint = $this->endpoints[$this->wsdl];
        $this->auth = [
            'codiceAzienda' => config('services.mysond.codice_azienda'),
            'username'      => config('services.mysond.username'),
            'password'      => config('services.mysond.password'),
        ];
        $this->authAde = [
            'tipoUtenza'    => 0,
            'username'      => Utils::setting('agenzia_entrate.username'),
            'password'      => Utils::setting('agenzia_entrate.password'),
            'pincode'      => Utils::setting('agenzia_entrate.pincode'),
            'utenza1'      => Utils::setting('agenzia_entrate.utenza'),
        ];

        $this->client = $this->initSoapClient();
    }

    private function initSoapClient()
    {
        ini_set('soap.wsdl_cache_enabled', 0);
        ini_set('soap.wsdl_cache_ttl', 0);

        $wsdl = base_path('resources/wsdl/' .  $this->wsdl . '.wsdl');
        $location = $this->endpoint;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        return new SoapClient($wsdl, [
            'location'       => $location, // Forza l'invio qui
            'stream_context' => $context,
            'trace'          => true,
            'exceptions'     => true,
            'cache_wsdl'     => WSDL_CACHE_NONE
        ]);
    }

    /**
     * Funzione centralizzata per i log di debug
     */
    private function logDebug($metodo, $parametri, $risposta = null, $errore = null)
    {
        $logData = [
            'metodo'   => $metodo,
            'endpoint' => $this->endpoint,
            'azienda'  => $this->auth['codiceAzienda'],
            'utente'   => $this->auth['username'],
        ];

        if ($errore) {
            Log::error("SOAP Error [$metodo]: " . $errore->getMessage());
            // Logghiamo l'XML esatto inviato per capire l'errore di validazione
            Log::error("XML INVIATO: " . $this->client->__getLastRequest());
            Log::error("RISPOSTA SERVER: " . $this->client->__getLastResponse());
        } else {
            Log::info("SOAP Request [$metodo] eseguita correttamente.");
            // In produzione potresti voler loggare l'XML solo se una riga nel .env è true
            if (config('app.debug')) {
                Log::debug("Dati inviati:", $parametri);
            }
        }
    }

    public function importFeAttivo(string $xmlContent, string $fileName, bool $signAndSand = true)
    {
        $this->setWsdl('mysond');

        // NB: $xmlContent viene passato come stringa raw. Il SoapClient PHP serializza
        // automaticamente il campo `xmlDoc` come xsd:base64Binary (encoding gestito dal WSDL).
        // Pre-encodarlo qui causerebbe un doppio base64 e MySond risponde "formato non valido".
        $params = [
            'arg0' => [
                'utente'      => $this->auth,
                'fileName'    => $fileName,
                'xmlDoc'      => $xmlContent,
                'signAndSand' => $signAndSand,
            ],
        ];

        try {
            $response = $this->client->importFeAttivo($params);
            $this->logDebug('importFeAttivo', ['fileName' => $fileName, 'signAndSand' => $signAndSand]);
            return $response->return ?? null;
        } catch (Exception $e) {
            $this->logDebug('importFeAttivo', ['fileName' => $fileName], null, $e);
            throw $e;
        }
    }

    /**
     * Recupera l'ultima notifica SDI per un file fattura.
     * $fileName può essere passato con o senza estensione `.xml` — viene rimossa.
     * Restituisce l'oggetto `return` dalla risposta SOAP, oppure null in caso di errore.
     */
    public function getNotifica(string $fileName)
    {
        $this->setWsdl('mysond');

        $baseName = preg_replace('/\.xml$/i', '', $fileName);

        $params = [
            'arg0' => [
                'aziendaCod' => $this->auth['codiceAzienda'],
                'utenteCod'  => $this->auth['username'],
                'fileName'   => $baseName,
            ],
        ];

        try {
            $response = $this->client->getNotifica($params);
            $this->logDebug('getNotifica', ['fileName' => $baseName]);
            return $response->return ?? null;
        } catch (Exception $e) {
            $this->logDebug('getNotifica', ['fileName' => $baseName], null, $e);
            throw $e;
        }
    }

    /**
     * Batch: ultima notifica per più file in una sola chiamata SOAP.
     * MySond accetta i file separati da pipe `|`. Le estensioni `.xml` vengono rimosse.
     * Restituisce un array di oggetti notifica (ordine non garantito dalla doc).
     */
    public function getUltimaNotificaList(array $fileNames): array
    {
        if (empty($fileNames)) {
            return [];
        }

        $this->setWsdl('mysond');

        $cleaned = array_map(fn($n) => preg_replace('/\.xml$/i', '', $n), $fileNames);
        $joined  = implode('|', $cleaned);

        $params = [
            'arg0' => [
                'aziendaCod' => $this->auth['codiceAzienda'],
                'utenteCod'  => $this->auth['username'],
                'fileName'   => $joined,
            ],
        ];

        try {
            $response = $this->client->getUltimaNotificaList($params);
            $this->logDebug('getUltimaNotificaList', ['count' => count($cleaned)]);
            $return = $response->return ?? null;
            if ($return === null) {
                return [];
            }
            // La risposta può essere singolo oggetto o array a seconda del numero di elementi.
            return is_array($return) ? $return : [$return];
        } catch (Exception $e) {
            $this->logDebug('getUltimaNotificaList', ['count' => count($cleaned)], null, $e);
            throw $e;
        }
    }

    public function riceviFatture(string $dal, string $al)
    {

        $params = [
            'arg0' => [
                'dataDal'        => $dal,
                'dataAl'        => $al,
                'signAndSand' => false, // Obbligatorio per docImportPaItem
                'utente'      => $this->auth,
            ]
        ];

        try {
            $response = $this->client->getFeRicevuteLink($params);
            return $response->return ?? [];
        } catch (\Exception $e) {
            $this->logDebug("getFeRicevute", $params, null, $e);
            throw $e;
        }
    }

    public function getFattureInviate(string $dal, string $al)
    {
        $params = [
            'arg0' => [
                'dataDal'        => $dal,
                'dataAl'        => $al,
                'signAndSand' => false, // Obbligatorio per docImportPaItem
                'utente'      => $this->auth,
            ]
        ];
        try {
            $response = $this->client->getFeInviateLink($params);
            print("<pre>".print_r($response,true)."</pre>");
            $this->logDebug("getFeInviate", $params);
            return $response->return ?? [];
        } catch (Exception $e) {
            $this->logDebug("getFeInviate", $params, null, $e);
            throw $e;
        }
    }

    public function resetFlagDownload(int $idDoc)
    {
        $params = ['parameters' => array_merge($this->auth, ['idDoc' => $idDoc])];
        try {
            return $this->client->resetDownloadFlag($params);
        } catch (Exception $e) {
            $this->logDebug("resetDownloadFlag", $params, null, $e);
            throw $e;
        }
    }

    public function getDatiAzienda()
    {
        $ddc = array (
            'arg0'  =>
                array('date' => null,
                    'utente' => $this->auth,
                    'signAndSand' => false,
                    'fileName' => null,
                    'xmlDoc' => null,
                    'dateLong' => null
                ),
        );
        try {
            $response = $this->client->getAzienda($ddc);
            print("<pre>".print_r($response,true)."</pre>");
            return $response->return ?? [];
        } catch (Exception $e) {
            $this->logDebug("getFeInviate", $ddc, null, $e);
            throw $e;
        }
    }

    public function inviaCorrispettivo($item)
    {
        $params = [
            'CorrispettivoElettronicoItem' => [
                'utenteAdeItem'     => $this->authAde,
                'utenteItem'        => $this->auth,
                'tipoTrasmissione'  => 1,
                'corrispettivoTestataItem' => $item
            ]
        ];

        try {
            $response = $this->client->inviaCorrispettivo($params);
            return $response->return ?? [];
        } catch (\Exception $e) {
            $this->logDebug("inviaCorrispettivo", $params, null, $e);
            throw $e;
        }
    }

    public function annullaCorrispettivo($progressivoSdi)
    {
        $params = [
            'CorrispettivoElettronicoItem' => [
                'utenteAdeItem'     => $this->authAde,
                'utenteItem'        => $this->auth,
                'progressivoSdi'    => $progressivoSdi,
            ]
        ];

        try {
            $response = $this->client->annullaCorrispettivo($params);
            return $response->return ?? [];
        } catch (\Exception $e) {
            $this->logDebug("inviaCorrispettivo", $params, null, $e);
            throw $e;
        }
    }

    public function exportWsdlStructure()
    {
        $types = $this->client->__getTypes();
        $functions = $this->client->__getFunctions();

        $content = "=== METODI DISPONIBILI ===\n";
        $content .= implode("\n", $functions);
        $content .= "\n\n=== STRUTTURE DATI (TYPES) ===\n";
        $content .= implode("\n", $types);

        $path = storage_path('app/mysond_structure.txt');
        file_put_contents($path, $content);

        return $path;
    }

    public function getLastRequestXml(): ?string
    {
        try {
            return $this->client?->__getLastRequest();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getLastResponseXml(): ?string
    {
        try {
            return $this->client?->__getLastResponse();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setWsdl($wsdl)
    {
        if ($this->wsdl === $wsdl) {
            return;
        }
        $this->wsdl = $wsdl;
        if (isset($this->endpoints[$wsdl])) {
            $this->endpoint = $this->endpoints[$wsdl];
        }
        $this->client = $this->initSoapClient();
    }

    public function cambiaPasswordAde(string $utenza, string $vecchiaPassword, string $nuovaPassword, ?string $confermaPassword = null)
    {
        $this->setWsdl('CorrispettivoElettronicoService');

        $params = [
            'arg0' => [
                'utenteItem'       => $this->auth,
                'utenza'           => $utenza,
                'vecchiaPassword'  => $vecchiaPassword,
                'nuovaPassword'    => $nuovaPassword,
                'confermaPassword' => $confermaPassword ?? $nuovaPassword,
            ],
        ];

        try {
            $response = $this->client->cambiaPasswordAde($params);
            $this->logDebug('cambiaPasswordAde', ['utenza' => $utenza]);
            return $response->return ?? null;
        } catch (Exception $e) {
            $this->logDebug('cambiaPasswordAde', ['utenza' => $utenza], null, $e);
            throw $e;
        }
    }

    public function verificaCredenzialiAde(?array $utenteAdeOverride = null)
    {
        $this->setWsdl('CorrispettivoElettronicoService');

        $params = [
            'arg0' => [
                'utenteItem'    => $this->auth,
                'utenteAdeItem' => $utenteAdeOverride ?? $this->authAde,
            ],
        ];

        try {
            $response = $this->client->verificaCredenzialiAde($params);
            $this->logDebug('verificaCredenzialiAde', $params);
            return $response->return ?? null;
        } catch (Exception $e) {
            $this->logDebug('verificaCredenzialiAde', $params, null, $e);
            throw $e;
        }
    }

    public function getXmlFromP7m(string $xml) : string
    {
        $params = [
            'arg0' => [
                'dataDal'        => '',
                'dataAl'        => '',
                'signAndSand' => false, // Obbligatorio per docImportPaItem
                'utente'      => $this->auth,
                'xmlDoc'        => base64_encode($xml),
            ]
        ];

        try {
            $response = $this->client->getXmlFromP7m($params);
            return $response->return->xmlDoc ?? '';
        } catch (\Exception $e) {
            $this->logDebug("getXmlFromP7m", $params, null, $e);
            throw $e;
        }
    }

    public function createInvoice($invoice) : array
    {
        return InvoiceService::make_xml($invoice);
    }

    /**
     * True when credenziali MySond sono configurate. Usato dai chiamanti per
     * disattivare silenziosamente sync/operazioni SOAP se l'integrazione non è
     * configurata (es. ambienti dev senza credenziali reali).
     */
    public function isConfigured(): bool
    {
        return !empty($this->auth['codiceAzienda'])
            && !empty($this->auth['username'])
            && !empty($this->auth['password']);
    }

    /**
     * Lista fatture emesse (FatturaPA attivo) per un anno. Variante "Link"
     * idempotente: ri-interrogare restituisce lo stesso set e non marca i
     * documenti come "scaricati" (a differenza di getFattureInviate). Usata
     * da `maxIssuedNumberForYear` per allineare il contatore locale con il
     * progressivo già presente su MySond.
     */
    public function getFeInviateLink(?int $year = null): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $this->setWsdl('mysond');

        $filter = ['utente' => $this->auth];
        if ($year !== null) {
            $filter['dataDal'] = sprintf('%d-01-01T00:00:00', $year);
            $filter['dataAl']  = sprintf('%d-12-31T23:59:59', $year);
        }

        $params = ['arg0' => $filter];

        try {
            $response = $this->client->getFeInviateLink($params);
            $this->logDebug('getFeInviateLink', ['year' => $year]);

            $return = $response->return ?? null;
            if ($return === null) {
                return [];
            }
            return is_array($return) ? $return : [$return];
        } catch (Exception $e) {
            $this->logDebug('getFeInviateLink', ['year' => $year], null, $e);
            throw $e;
        }
    }

    /**
     * Estrazione "best-effort" dell'anno dal campo data della docFe. MySond
     * può restituire stringhe ISO, DateTime (quando il SoapClient mappa
     * xsd:dateTime), epoch in ms o null. In caso di parsing fallito accettiamo
     * comunque l'item (meglio sovrastimare che perdere una fattura).
     */
    private function itemYear($item): ?int
    {
        $date = $item->date ?? $item->dateLong ?? null;

        if ($date instanceof \DateTimeInterface) {
            return (int) $date->format('Y');
        }
        if (is_int($date) || (is_string($date) && ctype_digit($date))) {
            $ts = (int) $date;
            if ($ts > 10_000_000_000) {
                $ts = (int) ($ts / 1000);
            }
            return (int) date('Y', $ts);
        }
        if (is_string($date) && preg_match('/(20\d{2})/', $date, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Estrae un intero ordinabile da un "Numero" fattura.
     *   "42"          -> 42
     *   "2026-00042"  -> 42 (run finale)
     *   "00042/2026"  -> 42 (run iniziale)
     *   "FT 42 2026"  -> 2026 (max wins; fallback sicuro)
     */
    private function numeroToInt(string $numero): ?int
    {
        if (preg_match('/-(\d+)\s*$/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\s*(\d+)/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match_all('/\d+/', $numero, $m)) {
            return max(array_map('intval', $m[0]));
        }
        return null;
    }

    /**
     * Numero più alto già presente su MySond per ($year), o null se non
     * possiamo determinarlo (MySond irraggiungibile, lista vuota, nessun
     * numero parsabile). Usato per allineare il contatore locale prima di
     * emettere una nuova fattura, evitando collisioni di numerazione.
     */
    public function maxIssuedNumberForYear(int $year): ?int
    {
        $allItems = $this->getFeInviateLink($year);
        if (empty($allItems)) {
            return null;
        }

        $items = array_values(array_filter($allItems, function ($item) use ($year) {
            $y = $this->itemYear($item);
            return $y === null || $y === $year;
        }));

        if (empty($items)) {
            return null;
        }

        $max = null;
        $matches = 0;

        foreach ($items as $item) {
            $code = $item->code ?? null;
            if (!is_string($code) || $code === '') {
                continue;
            }

            $n = $this->numeroToInt($code);
            if ($n === null) {
                continue;
            }

            $matches++;
            if ($max === null || $n > $max) {
                $max = $n;
            }
        }

        if ($max !== null) {
            return $max;
        }

        // Ultimo fallback: nessun `code` parsabile come intero. Restituiamo il
        // conteggio degli item così almeno non ripartiamo da 1.
        return $matches > 0 ? $matches : count($items);
    }
}
