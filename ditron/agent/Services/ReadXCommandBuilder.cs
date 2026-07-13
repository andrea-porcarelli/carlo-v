using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

/// <summary>
/// Costruisce il comando WinEcrCom per una Lettura X giornaliera
/// (opcode <c>azzgio tipo=1</c>): stampa i totali della giornata in corso
/// SENZA azzerare i contatori fiscali. Non ha valore fiscale.
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
            sb.AppendLine("azzgio tipo=1");
            return sb.ToString();
        }

        var label = "LETTURA X SIMULATA " + DateTime.Now.ToString("yyyy-MM-dd HH:mm", Inv);
        sb.AppendLine("nofis apri");
        sb.Append("nofis riga='").Append(label).AppendLine("'");
        sb.AppendLine("nofis chiudi");
        return sb.ToString();
    }
}
