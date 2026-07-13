using DitronAgent.Models;

namespace DitronAgent.Services;

/// <summary>
/// Interroga la cassa RT (via GETP: prop 1 matricola, prop 10 num ultimo scontrino,
/// prop 12 data ultimo Z) subito dopo l'emissione di uno scontrino, per recuperare
/// i dati fiscali che Carlo V deve poi persistere.
///
/// Il come varia in base a come WinEcrCom Auto-Run restituisce le risposte di GETP
/// (file di output? ActiveX?). L'astrazione permette di cambiare implementazione
/// senza toccare Program.cs.
/// </summary>
public interface IFiscalInfoReader
{
    Task<FiscalInfo> ReadLastReceiptAsync(CancellationToken cancellationToken);
}

public sealed class FiscalInfo
{
    /// <summary>Numero documento fiscale (es. "0005" o "1300-0005").</summary>
    public string? FiscalNumber { get; init; }

    /// <summary>Data scontrino in formato ISO yyyy-MM-dd.</summary>
    public string? FiscalDate { get; init; }

    /// <summary>Numero chiusura Z corrente.</summary>
    public int? ZNumber { get; init; }

    /// <summary>Matricola ECR.</summary>
    public string? Matricola { get; init; }

    public bool IsComplete =>
        !string.IsNullOrWhiteSpace(FiscalNumber)
        && !string.IsNullOrWhiteSpace(FiscalDate)
        && ZNumber.HasValue
        && !string.IsNullOrWhiteSpace(Matricola);

    public static FiscalInfo Empty { get; } = new();
}
