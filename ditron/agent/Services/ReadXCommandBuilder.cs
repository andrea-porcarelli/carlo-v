using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

/// <summary>
/// Costruisce il comando WinEcrCom per una Lettura X giornaliera
/// (opcode <c>report num=2 modo=0</c>): stampa i totali della giornata in corso
/// SENZA azzerare i contatori fiscali. Non ha valore fiscale.
/// Attenzione: <c>azzgio</c> (opcode 27) è sempre un azzeramento Z, anche con
/// <c>tipo=1</c> — usare esclusivamente <c>report</c> (opcode 26) con
/// <c>modo=0</c> per la lettura X.
/// </summary>
public sealed class ReadXCommandBuilder : IReadXCommandBuilder
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;

    public ReadXCommandBuilder(IOptions<DitronAgentOptions> options)
    {
        _options = options.Value;
    }

    public string Build(ReadXRequest request)
    {
        var sb = new StringBuilder();

        if (_options.Mode == ReceiptMode.Fiscal)
        {
            sb.AppendLine("report num=2 modo=0");
            return sb.ToString();
        }

        var label = "LETTURA X SIMULATA " + DateTime.Now.ToString("yyyy-MM-dd HH:mm", Inv);
        sb.AppendLine("nofis apri");
        sb.Append("nofis riga='").Append(label).AppendLine("'");
        sb.AppendLine("nofis chiudi");
        return sb.ToString();
    }
}
