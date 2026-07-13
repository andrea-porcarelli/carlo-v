using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

public interface ICancelCommandBuilder
{
    string Build(CancelReceiptRequest request);
}

/// <summary>
/// Costruisce il comando WinEcrCom per emettere un DOCANNULLO (documento di
/// annullamento fiscale, opcode 124). Sintassi documentata in ecrcomrt.ini [124]:
///
///   1 = NUMSCO     numero dello scontrino fiscale da annullare
///   2 = DATASCO    data dello scontrino (formato GGMMAA)
///   3 = ZNUMBER    numero azzeramento (chiusura Z)
///   4 = MATRICOLA  matricola ECR
///   5 = AUTOMATICO flag automatico (SI/NO)
///
/// In modalità NonFiscal emettiamo un banner testuale a scopo test — la cassa NON
/// stampa realmente un annullo fiscale finché Mode = Fiscal.
/// </summary>
public sealed class CancelCommandBuilder : ICancelCommandBuilder
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;

    public CancelCommandBuilder(IOptions<DitronAgentOptions> options)
    {
        _options = options.Value;
    }

    public string Build(CancelReceiptRequest request)
    {
        var sb = new StringBuilder();

        if (_options.Mode == ReceiptMode.Fiscal)
        {
            sb.Append("docannullo NUMSCO=").Append(request.FiscalNumber);
            sb.Append(", DATASCO=").Append(FormatDate(request.FiscalDate));
            sb.Append(", ZNUMBER=").Append(request.ZNumber.ToString(Inv));
            sb.Append(", MATRICOLA=").Append(request.Matricola);
            sb.AppendLine(", AUTOMATICO=2");
            return sb.ToString();
        }

        var label = "ANNULLO SIMULATO " + DateTime.Now.ToString("yyyy-MM-dd HH:mm", Inv);
        sb.AppendLine("nofis apri");
        sb.Append("nofis riga='").Append(label).AppendLine("'");
        sb.Append("nofis riga='NUMSCO=").Append(request.FiscalNumber).AppendLine("'");
        sb.Append("nofis riga='DATASCO=").Append(FormatDate(request.FiscalDate)).AppendLine("'");
        sb.Append("nofis riga='ZNUMBER=").Append(request.ZNumber.ToString(Inv)).AppendLine("'");
        sb.Append("nofis riga='MATRICOLA=").Append(request.Matricola).AppendLine("'");
        sb.AppendLine("nofis chiudi");
        return sb.ToString();
    }

    /// <summary>Converte "2026-07-13" → "130726" (formato GGMMAA atteso da WinEcrCom).</summary>
    private static string FormatDate(string iso)
    {
        if (DateTime.TryParse(iso, Inv, DateTimeStyles.None, out var d))
        {
            return d.ToString("ddMMyy", Inv);
        }
        return iso;
    }
}
