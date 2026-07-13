using System.Diagnostics;
using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

/// <summary>
/// Legge proprietà dalla cassa Ditron RT via istruzione <c>INFO</c> (opcode 71
/// definito in <c>ecrcomrt.ini</c>) in modalità WinEcrCom Auto-Run Spooler.
///
/// Nota storica: la prima implementazione usava <c>getp num=X</c> (opcode 49).
/// WinEcrCom rifiuta quella sintassi con "ERRORE DI SINTASSI 4: OPERANDO NON
/// TROVATO" perché su Ditron RT l'opcode <c>GETP</c> supporta solo gli operandi
/// simbolici <c>ERR</c> e <c>CURDIR</c>, non un numero di proprietà. Le info
/// fiscali (matricola, ultimo scontrino, data Z, gran totale…) vivono
/// sull'opcode <c>INFO CODICE=X</c>.
///
/// Auto-Run non popola il campo COM <c>ResultString</c>: per catturare
/// l'output dell'istruzione usiamo l'operando <c>FILE='&lt;path&gt;'</c>
/// (introdotto in WinEcrCom 1.9.0) che copia il ResultString in un file di
/// testo. Emettiamo un file per proprietà per evitare ambiguità di
/// append/overwrite tra letture consecutive.
/// </summary>
public sealed class AutoRunPropertyReader : IPropertyReader
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;
    private readonly IReceiptNumberAllocator _allocator;
    private readonly ILogger<AutoRunPropertyReader> _logger;

    public AutoRunPropertyReader(
        IOptions<DitronAgentOptions> options,
        IReceiptNumberAllocator allocator,
        ILogger<AutoRunPropertyReader> logger)
    {
        _options = options.Value;
        _allocator = allocator;
        _logger = logger;
    }

    public async Task<PropertyReadResult> ReadAsync(IReadOnlyCollection<int> propertyNumbers, CancellationToken cancellationToken)
    {
        if (propertyNumbers is null || propertyNumbers.Count == 0)
        {
            return new PropertyReadResult { Ok = false, Error = "No properties requested" };
        }

        Directory.CreateDirectory(_options.ScontriniFolder);

        var nn = _allocator.Allocate().ToString("D2", Inv);
        var txtPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.txt");
        var errPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.err");

        // Un file di output per ogni proprietà, così ogni ResultString finisce
        // in un file dedicato e possiamo mapparlo al numero di proprietà.
        var propOutPaths = propertyNumbers.ToDictionary(
            p => p,
            p => Path.Combine(_options.ScontriniFolder, $"scontrino{nn}_prop{p}.out"));

        foreach (var p in propOutPaths.Values)
        {
            if (File.Exists(p)) File.Delete(p);
        }
        if (File.Exists(errPath)) File.Delete(errPath);

        // Sintassi WinEcrCom (vedi WinEcrCom2.pdf pag.14): gli operandi di una
        // stessa istruzione vanno separati da virgola. Senza virgola il parser
        // interpreta `FILE=...` come continuazione del valore di CODICE e
        // solleva "ERRORE DI SINTASSI 8: VALORE ALFABETICO NON VALIDO".
        var sb = new StringBuilder();
        foreach (var (num, outPath) in propOutPaths)
        {
            sb.Append("INFO CODICE=").Append(num.ToString(Inv))
              .Append(", FILE='").Append(outPath).AppendLine("'");
        }
        var command = sb.ToString();

        var stopwatch = Stopwatch.StartNew();
        await File.WriteAllTextAsync(txtPath, command, new UTF8Encoding(false), cancellationToken);
        _logger.LogInformation("INFO command written to {Path}: {Cmd}", txtPath, command.Replace("\n", "; "));

        var rawErr = await WaitForBatchCompletionAsync(txtPath, errPath, cancellationToken);
        stopwatch.Stop();

        // Se il .err contiene un errore riconosciuto, la lettura è fallita.
        if (!string.IsNullOrWhiteSpace(rawErr))
        {
            var classification = ErrClassifier.Classify(rawErr, _options);
            if (classification.IsError)
            {
                return new PropertyReadResult
                {
                    Ok = false,
                    RawCommand = command,
                    RawErr = rawErr,
                    ElapsedMs = stopwatch.ElapsedMilliseconds,
                    Error = rawErr.Trim(),
                };
            }
        }

        // Timeout: né .err né alcun file di output prodotto.
        if (rawErr is null && !propOutPaths.Values.Any(File.Exists))
        {
            return new PropertyReadResult
            {
                Ok = false,
                RawCommand = command,
                ElapsedMs = stopwatch.ElapsedMilliseconds,
                Error = $"Timeout: nessun file di risposta prodotto entro {_options.ErrPollingTimeoutMs}ms",
            };
        }

        var values = new Dictionary<int, string>();
        foreach (var (num, path) in propOutPaths)
        {
            if (!File.Exists(path)) continue;

            var raw = await TryReadAsync(path, cancellationToken);
            var value = NormalizeInfoOutput(raw);
            if (!string.IsNullOrEmpty(value))
            {
                values[num] = value;
            }

            try { File.Delete(path); } catch (IOException) { /* best effort */ }
        }

        return new PropertyReadResult
        {
            Ok = values.Count > 0,
            Values = values,
            RawCommand = command,
            RawErr = rawErr,
            ElapsedMs = stopwatch.ElapsedMilliseconds,
            Error = values.Count == 0 ? "Nessun valore letto dai file di output INFO." : null,
        };
    }

    private async Task<string?> WaitForBatchCompletionAsync(string txtPath, string errPath, CancellationToken cancellationToken)
    {
        var deadline = DateTime.UtcNow.AddMilliseconds(_options.ErrPollingTimeoutMs);
        while (DateTime.UtcNow < deadline)
        {
            cancellationToken.ThrowIfCancellationRequested();
            if (!File.Exists(txtPath))
            {
                return File.Exists(errPath) ? await TryReadAsync(errPath, cancellationToken) : null;
            }
            await Task.Delay(_options.ErrPollingIntervalMs, cancellationToken);
        }
        return File.Exists(errPath) ? await TryReadAsync(errPath, cancellationToken) : null;
    }

    private static async Task<string?> TryReadAsync(string path, CancellationToken cancellationToken)
    {
        try
        {
            return await File.ReadAllTextAsync(path, cancellationToken);
        }
        catch (IOException)
        {
            return null;
        }
    }

    /// <summary>
    /// Il file prodotto da <c>INFO ... FILE=</c> contiene il ResultString grezzo
    /// (una singola stringa, senza formattazione), tipicamente su una riga.
    /// Ripuliamo whitespace, terminatori CR/LF e — se WinEcrCom decidesse di
    /// racchiudere il valore fra apici — anche quelli.
    /// </summary>
    private static string NormalizeInfoOutput(string? raw)
    {
        if (string.IsNullOrEmpty(raw)) return string.Empty;
        var trimmed = raw.Trim().Trim('\r', '\n').Trim();
        return trimmed.Trim('\'', '"');
    }
}
