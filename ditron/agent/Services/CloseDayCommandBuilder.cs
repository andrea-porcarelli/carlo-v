using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

public sealed class CloseDayCommandBuilder : ICloseDayCommandBuilder
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;

    public CloseDayCommandBuilder(IOptions<DitronAgentOptions> options)
    {
        _options = options.Value;
    }

    public string Build(CloseDayRequest request)
    {
        var tipo = request.Tipo is int t && t is 1 or 2 or 3 ? t : 2;
        var sb = new StringBuilder();

        if (_options.Mode == ReceiptMode.Fiscal)
        {
            sb.Append("azzgio tipo=").AppendLine(tipo.ToString(Inv));
            return sb.ToString();
        }

        var label = "Z SIMULATA " + DateTime.Now.ToString("yyyy-MM-dd HH:mm", Inv);
        sb.AppendLine("nofis apri");
        sb.Append("nofis riga='").Append(label).AppendLine("'");
        sb.Append("nofis riga='tipo=").Append(tipo.ToString(Inv)).AppendLine("'");
        sb.AppendLine("nofis chiudi");
        return sb.ToString();
    }
}
