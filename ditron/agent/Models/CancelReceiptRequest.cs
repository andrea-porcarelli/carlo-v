namespace DitronAgent.Models;

/// <summary>
/// Richiesta di emissione DOCANNULLO (opcode 124 in ecrcomrt.ini) per uno scontrino
/// fiscale già emesso. I 4 dati identificativi (NUMSCO/DATASCO/ZNUMBER/MATRICOLA)
/// vengono presi da Carlo V dal record `ditron_receipts` originale.
/// </summary>
public sealed class CancelReceiptRequest
{
    public string IdempotencyKey { get; set; } = string.Empty;

    /// <summary>Numero dello scontrino fiscale da annullare (NUMSCO).</summary>
    public string FiscalNumber { get; set; } = string.Empty;

    /// <summary>Data dello scontrino fiscale (yyyy-MM-dd, verrà tradotta a GGMMAA).</summary>
    public string FiscalDate { get; set; } = string.Empty;

    /// <summary>Numero della chiusura Z in cui era compreso lo scontrino (ZNUMBER).</summary>
    public int ZNumber { get; set; }

    /// <summary>Matricola ECR (MATRICOLA).</summary>
    public string Matricola { get; set; } = string.Empty;
}
