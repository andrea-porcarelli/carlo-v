#!/usr/bin/env php
<?php

/**
 * Script di test per verificare la connessione alla stampante POS
 *
 * Uso:
 *   docker-compose exec app php docker/test-printer.php 192.168.8.200
 */

if ($argc < 2) {
    echo "Uso: php test-printer.php <IP_STAMPANTE>\n";
    echo "Esempio: php test-printer.php 192.168.8.200\n";
    exit(1);
}

$printerIp = $argv[1];
$port = 9100;
$timeout = 5;

echo "========================================\n";
echo "Test Connessione Stampante POS\n";
echo "========================================\n\n";

echo "IP Stampante: $printerIp\n";
echo "Porta: $port\n";
echo "Timeout: $timeout secondi\n\n";

// Test 1: Verifica estensioni PHP
echo "--- Test 1: Estensioni PHP ---\n";
$extensions = ['mbstring', 'intl'];
$allPresent = true;
foreach ($extensions as $ext) {
    $present = extension_loaded($ext);
    echo sprintf("  %-15s %s\n", $ext, $present ? '✓ OK' : '✗ MANCANTE');
    if (!$present) $allPresent = false;
}
echo $allPresent ? "  Tutte le estensioni presenti!\n" : "  ATTENZIONE: Ricostruire l'immagine Docker\n";
echo "\n";

// Test 2: Risoluzione DNS (se applicabile)
echo "--- Test 2: Risoluzione IP ---\n";
if (filter_var($printerIp, FILTER_VALIDATE_IP)) {
    echo "  IP valido: $printerIp ✓\n";
} else {
    echo "  Risoluzione hostname...\n";
    $resolved = gethostbyname($printerIp);
    echo "  $printerIp -> $resolved\n";
    $printerIp = $resolved;
}
echo "\n";

// Test 3: Ping/Connessione TCP
echo "--- Test 3: Connessione TCP ---\n";
echo "  Tentativo di connessione a $printerIp:$port...\n";

$errno = 0;
$errstr = '';
$startTime = microtime(true);
$fp = @fsockopen($printerIp, $port, $errno, $errstr, $timeout);
$endTime = microtime(true);
$responseTime = round(($endTime - $startTime) * 1000, 2);

if ($fp) {
    echo "  ✓ Connessione riuscita!\n";
    echo "  Tempo di risposta: {$responseTime}ms\n";
    fclose($fp);

    // Test 4: Tentativo di stampa di test (opzionale)
    echo "\n--- Test 4: Stampa di Test ---\n";
    echo "  Inviare una stampa di test? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) === 's') {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $connector = new \Mike42\Escpos\PrintConnectors\NetworkPrintConnector($printerIp, $port, $timeout);
            $printer = new \Mike42\Escpos\Printer($connector);

            $printer->initialize();
            $printer->setEmphasis(true);
            $printer->text("TEST CONNESSIONE\n");
            $printer->setEmphasis(false);
            $printer->text("Data: " . date('d/m/Y H:i:s') . "\n");
            $printer->text("IP: $printerIp\n");
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            echo "  ✓ Stampa di test inviata con successo!\n";
        } catch (\Exception $e) {
            echo "  ✗ Errore durante la stampa: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  Stampa di test saltata.\n";
    }

    echo "\n========================================\n";
    echo "RISULTATO: ✓ STAMPANTE RAGGIUNGIBILE\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "  ✗ Connessione fallita!\n";
    echo "  Errore: [$errno] $errstr\n";

    echo "\n--- Suggerimenti ---\n";
    echo "  1. Verifica che la stampante sia accesa\n";
    echo "  2. Verifica che l'IP sia corretto (ping $printerIp)\n";
    echo "  3. Verifica che la porta 9100 sia aperta sulla stampante\n";
    echo "  4. Se usi Docker Desktop (Mac/Windows), potrebbe servire network_mode: host\n";
    echo "  5. Prova dal container:\n";
    echo "     docker-compose exec app ping -c 3 $printerIp\n";
    echo "     docker-compose exec app nc -zv $printerIp $port\n";

    echo "\n========================================\n";
    echo "RISULTATO: ✗ STAMPANTE NON RAGGIUNGIBILE\n";
    echo "========================================\n";
    exit(1);
}
