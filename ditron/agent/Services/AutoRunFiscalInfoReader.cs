using System.Globalization;
using DitronAgent.Configuration;

namespace DitronAgent.Services;

/// <summary>
/// Legge i dati fiscali dell'ultimo scontrino emesso combinando GETP prop 1 (matricola),
/// prop 10 (numero ultimo scontrino), prop 12 (data ultimo Z). Delega a IPropertyReader
/// per la meccanica di comunicazione con la cassa; qui rimane solo la logica di
/// mapping in FiscalInfo.
/// </summary>
public sealed class AutoRunFiscalInfoReader : IFiscalInfoReader
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;
    private readonly IPropertyReader _propertyReader;
    private readonly ILogger<AutoRunFiscalInfoReader> _logger;

    public AutoRunFiscalInfoReader(
        Microsoft.Extensions.Options.IOptions<DitronAgentOptions> options,
        IPropertyReader propertyReader,
        ILogger<AutoRunFiscalInfoReader> logger)
    {
        _options = options.Value;
        _propertyReader = propertyReader;
        _logger = logger;
    }

    public async Task<FiscalInfo> ReadLastReceiptAsync(CancellationToken cancellationToken)
    {
        if (_options.Mode != ReceiptMode.Fiscal)
        {
            // In NonFiscal la cassa non emette documenti fiscali: nulla da leggere.
            return FiscalInfo.Empty;
        }

        var result = await _propertyReader.ReadAsync(new[] { 1, 10, 12 }, cancellationToken);
        if (!result.Ok)
        {
            _logger.LogWarning("Fiscal info non recuperabile: {Err}", result.Error);
            return FiscalInfo.Empty;
        }

        result.Values.TryGetValue(1, out var matricola);
        result.Values.TryGetValue(10, out var lastReceipt);
        result.Values.TryGetValue(12, out var lastZorDate);

        int? zNumber = null;
        string? fiscalDate = null;
        if (!string.IsNullOrWhiteSpace(lastZorDate))
        {
            if (int.TryParse(lastZorDate, out var z))
            {
                zNumber = z;
                fiscalDate = DateTime.Today.ToString("yyyy-MM-dd", Inv);
            }
            else
            {
                fiscalDate = lastZorDate;
            }
        }

        return new FiscalInfo
        {
            Matricola = matricola,
            FiscalNumber = lastReceipt,
            ZNumber = zNumber,
            FiscalDate = fiscalDate ?? DateTime.Today.ToString("yyyy-MM-dd", Inv),
        };
    }
}
