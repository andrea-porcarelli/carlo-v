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
}
