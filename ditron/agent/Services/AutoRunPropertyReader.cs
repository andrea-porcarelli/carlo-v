using System.Diagnostics;
using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

/// <summary>
/// Legge proprietà via GETP in modalità WinEcrCom Auto-Run: scrive un file `scontrinoNN.txt`
/// con una riga `getp num=X` per ogni proprietà, e attende un file di output.
///
/// TODO after spike: il formato del file di risposta va verificato sulla cassa reale.
/// Ipotesi in ordine di probabilità decrescente:
///   1) WinEcrCom scrive un file `scontrinoNN.out` (o `.rsp`) accanto al `.err` con
///      righe `num=X val=YYY` o simili.
///   2) La risposta di GETP finisce nel `.err` stesso quando non c'è un errore vero.
///   3) Auto-Run non supporta GETP e serve passare a `CoEcrCom.ocx` (ActiveX, COM).
///
/// Per gestire tutte e tre le ipotesi il reader cerca in ordine: `.out`, `.rsp`, e
/// (se l'`.err` è vuoto o non matcha una keyword di errore) prova a parsarlo come output.
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
        var outPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.out");
        var rspPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.rsp");

        foreach (var p in new[] { errPath, outPath, rspPath })
        {
            if (File.Exists(p)) File.Delete(p);
        }

        var sb = new StringBuilder();
        foreach (var n in propertyNumbers)
        {
            sb.Append("getp num=").AppendLine(n.ToString(Inv));
        }
        var command = sb.ToString();

        var stopwatch = Stopwatch.StartNew();
        await File.WriteAllTextAsync(txtPath, command, new UTF8Encoding(false), cancellationToken);
        _logger.LogInformation("GETP command written to {Path}: {Cmd}", txtPath, command.Replace("\n", "; "));

        var (rawResponse, rawErr) = await WaitForResponseAsync(txtPath, outPath, rspPath, errPath, cancellationToken);
        stopwatch.Stop();

        if (rawResponse is null && rawErr is null)
        {
            return new PropertyReadResult
            {
                Ok = false,
                RawCommand = command,
                ElapsedMs = stopwatch.ElapsedMilliseconds,
                Error = $"Timeout: nessun file di risposta prodotto entro {_options.ErrPollingTimeoutMs}ms",
            };
        }

        // Se l'`.err` non-vuoto matcha una keyword di errore, lo consideriamo fallito.
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
            // altrimenti (warning/info) potrebbe contenere anche la risposta — proviamo a parsare
            rawResponse ??= rawErr;
        }

        var values = ParseGetpOutput(rawResponse ?? string.Empty);

        return new PropertyReadResult
        {
            Ok = values.Count > 0,
            Values = values,
            RawCommand = command,
            RawErr = rawErr,
            ElapsedMs = stopwatch.ElapsedMilliseconds,
            Error = values.Count == 0 ? "Nessun valore riconosciuto nella risposta della cassa. Verifica formato .out/.rsp." : null,
        };
    }

    private async Task<(string? response, string? err)> WaitForResponseAsync(string txtPath, string outPath, string rspPath, string errPath, CancellationToken cancellationToken)
    {
        var deadline = DateTime.UtcNow.AddMilliseconds(_options.ErrPollingTimeoutMs);
        while (DateTime.UtcNow < deadline)
        {
            cancellationToken.ThrowIfCancellationRequested();
            if (!File.Exists(txtPath))
            {
                string? response = null;
                string? err = null;
                if (File.Exists(outPath)) response = await TryReadAsync(outPath, cancellationToken);
                if (response is null && File.Exists(rspPath)) response = await TryReadAsync(rspPath, cancellationToken);
                if (File.Exists(errPath)) err = await TryReadAsync(errPath, cancellationToken);

                if (response is not null || err is not null)
                {
                    return (response, err);
                }
            }
            await Task.Delay(_options.ErrPollingIntervalMs, cancellationToken);
        }
        return (null, null);
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
    /// Parser euristico. Le forme più probabili sono:
    ///   `num=1 val=EU84011934`
    ///   `1=EU84011934`
    ///   `getp num=1 val=EU84011934`
    ///   `1 EU84011934`
    /// TODO after spike: sostituire con parser preciso quando abbiamo un file reale.
    /// </summary>
    private static Dictionary<int, string> ParseGetpOutput(string raw)
    {
        var result = new Dictionary<int, string>();
        if (string.IsNullOrWhiteSpace(raw)) return result;

        foreach (var lineRaw in raw.Split('\n'))
        {
            var line = lineRaw.Replace("\r", string.Empty).Trim();
            if (line.Length == 0) continue;

            var propIdx = line.IndexOf("num=", StringComparison.OrdinalIgnoreCase);
            var eqIdx = line.LastIndexOf('=');

            int? prop = null;
            string? val = null;

            if (propIdx >= 0)
            {
                var afterNum = line[(propIdx + 4)..];
                var spaceIdx = afterNum.IndexOfAny(new[] { ' ', '=', ',', ';' });
                var numStr = spaceIdx > 0 ? afterNum[..spaceIdx] : afterNum;
                if (int.TryParse(numStr, out var n)) prop = n;

                if (eqIdx > propIdx && eqIdx < line.Length - 1)
                {
                    val = line[(eqIdx + 1)..].Trim().Trim('\'', '"');
                }
            }
            else if (eqIdx > 0)
            {
                var left = line[..eqIdx].Trim();
                if (int.TryParse(left, out var n))
                {
                    prop = n;
                    val = line[(eqIdx + 1)..].Trim().Trim('\'', '"');
                }
            }
            else
            {
                var parts = line.Split(' ', 2, StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);
                if (parts.Length == 2 && int.TryParse(parts[0], out var n))
                {
                    prop = n;
                    val = parts[1];
                }
            }

            if (prop.HasValue && !string.IsNullOrWhiteSpace(val))
            {
                result[prop.Value] = val!;
            }
        }
        return result;
    }
}
