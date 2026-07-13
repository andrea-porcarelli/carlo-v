namespace DitronAgent.Services;

/// <summary>
/// Interroga la cassa RT via GETP (opcode 49) su un elenco arbitrario di proprietà
/// numeriche e restituisce le stringhe grezze lette. Le proprietà tipiche sono
/// documentate in ecrcomrt.ini paragrafo [49]:
///   1 = Matricola fiscale dell'ecr selezionato
///   9 = numero ultimo scontrino emesso (in transazione) / N
///   10 = Numero ultimo scontrino emesso
///   11 = subtotale corrente in transazione / ultimo totale
///   12 = Data ultimo Z Report
///   16 = Gran Totale relativo all'ultimo Z report (solo ECR Italia)
///   17 = Numero ultima nota di credito emessa dopo l'ultimo azzeramento
/// </summary>
public interface IPropertyReader
{
    /// <summary>
    /// Legge le proprietà indicate e ritorna un risultato con i valori grezzi per
    /// proprietà correttamente lette e la stringa RawErr se WinEcrCom ha risposto
    /// con un errore.
    /// </summary>
    Task<PropertyReadResult> ReadAsync(IReadOnlyCollection<int> propertyNumbers, CancellationToken cancellationToken);
}

public sealed class PropertyReadResult
{
    public bool Ok { get; init; }

    public IDictionary<int, string> Values { get; init; } = new Dictionary<int, string>();

    public string? RawCommand { get; init; }

    public string? RawErr { get; init; }

    public string? Error { get; init; }

    public long ElapsedMs { get; init; }
}
