using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

/// <summary>
/// Legge i dati fiscali dell'ultimo scontrino emesso interrogando la cassa via GETP
/// in modalità WinEcrCom Auto-Run.
///
/// TODO after spike:
/// Come WinEcrCom Auto-Run scriva la risposta di `getp num=X` va confermato sulla cassa
/// reale. Ipotesi di partenza (da validare):
///  - WinEcrCom mette il valore letto sulla stessa riga del comando nel file di input
///    che poi rinomina in `.out` (o simile).
///  - In alternativa serve leggere `ERR.OUT` che nella modalità File esterni contiene
///    anche dati echoed.
///  - Peggior caso: WinEcrCom Auto-Run non supporta GETP e serve passare a CoEcrCom
///    ActiveX (COM interop) — in quel caso rimane l'interfaccia, cambia solo qui.
///
/// Fino a spike concluso questa implementazione ritorna FiscalInfo.Empty e logga un
/// warning, così l'emissione dello scontrino di vendita procede comunque OK ma Carlo V
/// non potrà emettere annulli su quello scontrino (`isCancellable()` sarà false).
/// </summary>
public sealed class AutoRunFiscalInfoReader : IFiscalInfoReader
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;
    private readonly IReceiptNumberAllocator _allocator;
    private readonly ILogger<AutoRunFiscalInfoReader> _logger;

    public AutoRunFiscalInfoReader(
        IOptions<DitronAgentOptions> options,
        IReceiptNumberAllocator allocator,
        ILogger<AutoRunFiscalInfoReader> logger)
    {
        _options = options.Value;
        _allocator = allocator;
        _logger = logger;
    }

    public async Task<FiscalInfo> ReadLastReceiptAsync(CancellationToken cancellationToken)
    {
        if (_options.Mode != ReceiptMode.Fiscal)
        {
            // In NonFiscal la cassa non emette documenti fiscali: nulla da leggere.
            return FiscalInfo.Empty;
        }

        var nn = _allocator.Allocate().ToString("D2", Inv);
        var txtPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.txt");
        var errPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.err");
        var outPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.out");

        var command = new StringBuilder();
        command.AppendLine("getp num=1");   // Matricola ECR
        command.AppendLine("getp num=10");  // Numero ultimo scontrino
        command.AppendLine("getp num=12");  // Data ultimo Z (usata come prossima Z per DOCANNULLO)

        try
        {
            await File.WriteAllTextAsync(txtPath, command.ToString(), new UTF8Encoding(false), cancellationToken);

            var deadline = DateTime.UtcNow.AddMilliseconds(_options.ErrPollingTimeoutMs);
            while (DateTime.UtcNow < deadline)
            {
                cancellationToken.ThrowIfCancellationRequested();
                if (!File.Exists(txtPath) && (File.Exists(outPath) || File.Exists(errPath)))
                {
                    var outContent = File.Exists(outPath) ? await File.ReadAllTextAsync(outPath, cancellationToken) : null;
                    var errContent = File.Exists(errPath) ? await File.ReadAllTextAsync(errPath, cancellationToken) : null;

                    if (!string.IsNullOrWhiteSpace(errContent))
                    {
                        _logger.LogWarning("GETP fallito, err='{Err}'", errContent.Trim());
                        return FiscalInfo.Empty;
                    }

                    return ParseGetpOutput(outContent);
                }
                await Task.Delay(_options.ErrPollingIntervalMs, cancellationToken);
            }
            _logger.LogWarning("Timeout in lettura fiscal info via GETP");
            return FiscalInfo.Empty;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Errore lettura fiscal info via GETP");
            return FiscalInfo.Empty;
        }
    }

    /// <summary>
    /// TODO after spike: il formato di output di GETP in Auto-Run va verificato sulla cassa.
    /// Ipotesi: righe key=value o testo libero. Il parser va adeguato quando abbiamo un file
    /// di esempio reale.
    /// </summary>
    private FiscalInfo ParseGetpOutput(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw))
        {
            return FiscalInfo.Empty;
        }

        string? matricola = null;
        string? fiscalNumber = null;
        int? zNumber = null;
        string? fiscalDate = null;

        foreach (var line in raw.Split('\n', StringSplitOptions.RemoveEmptyEntries))
        {
            var trimmed = line.Trim();
            if (trimmed.StartsWith("num=1", StringComparison.OrdinalIgnoreCase) && trimmed.Contains('='))
            {
                matricola = ExtractValue(trimmed);
            }
            else if (trimmed.StartsWith("num=10", StringComparison.OrdinalIgnoreCase))
            {
                fiscalNumber = ExtractValue(trimmed);
            }
            else if (trimmed.StartsWith("num=12", StringComparison.OrdinalIgnoreCase))
            {
                var raw12 = ExtractValue(trimmed);
                if (int.TryParse(raw12, out var z))
                {
                    zNumber = z;
                }
                else
                {
                    fiscalDate = raw12;
                }
            }
        }

        return new FiscalInfo
        {
            Matricola = matricola,
            FiscalNumber = fiscalNumber,
            ZNumber = zNumber,
            FiscalDate = fiscalDate ?? DateTime.Today.ToString("yyyy-MM-dd", Inv),
        };
    }

    private static string? ExtractValue(string line)
    {
        var idx = line.LastIndexOf('=');
        if (idx < 0 || idx == line.Length - 1) return null;
        return line[(idx + 1)..].Trim().Trim('\'', '"');
    }
}
