using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

public sealed class ReceiptBuilder : IReceiptBuilder
{
    private static readonly CultureInfo Inv = CultureInfo.InvariantCulture;
    private readonly DitronAgentOptions _options;

    public ReceiptBuilder(IOptions<DitronAgentOptions> options)
    {
        _options = options.Value;
    }

    public string Build(EmitReceiptRequest request)
    {
        var defaults = _options.Defaults;
        var sb = new StringBuilder();

        var mode = _options.Mode;

        if (mode == ReceiptMode.NonFiscal)
        {
            sb.AppendLine("nofis apri");
            if (request.TableNumber is int tn)
            {
                sb.Append("nofis riga='").Append(PadDescription("TAVOLO " + tn.ToString(Inv), defaults.DescrMaxLen * 2)).AppendLine("'");
            }
            foreach (var item in EnumerateAllItems(request, defaults))
            {
                var line = FormatNonFiscalLine(item, defaults);
                sb.Append("nofis riga='").Append(line).AppendLine("'");
            }
            if (TryGetDiscount(request, out var nofisDiscountValue, out var nofisDiscountLabel))
            {
                sb.Append("nofis riga='").Append(FormatNonFiscalDiscountLine(nofisDiscountLabel, nofisDiscountValue, defaults)).AppendLine("'");
            }
            sb.AppendLine("nofis chiudi");
            return sb.ToString();
        }

        if (request.TableNumber is int tableNumber)
        {
            var label = PadDescription("TAVOLO " + tableNumber.ToString(Inv), defaults.DescrMaxLen * 2);
            sb.Append("prmsg riga='").Append(label).AppendLine("'");
        }

        foreach (var item in EnumerateAllItems(request, defaults))
        {
            sb.AppendLine(FormatVendLine(item, defaults));
        }

        if (TryGetDiscount(request, out var discountValue, out var discountLabel))
        {
            sb.AppendLine("subt");
            sb.AppendLine(FormatDiscountLine(discountLabel, discountValue, defaults));
        }

        var tender = request.Tender ?? defaults.Tender;
        sb.Append("chius T=").AppendLine(tender.ToString(Inv));

        return sb.ToString();
    }

    private static bool TryGetDiscount(EmitReceiptRequest request, out decimal value, out string label)
    {
        if (request.Discount is { Value: > 0m } d)
        {
            value = decimal.Round(d.Value, 2);
            label = string.IsNullOrWhiteSpace(d.Description) ? string.Empty : d.Description!;
            return true;
        }
        value = 0m;
        label = string.Empty;
        return false;
    }

    private string FormatDiscountLine(string label, decimal value, ReceiptDefaults defaults)
    {
        var descr = string.IsNullOrWhiteSpace(label) ? defaults.DiscountLabel : label;
        var sanitizedDescr = SanitizeDescription(descr);
        var sb = new StringBuilder();
        sb.Append("sconto valore=").Append(value.ToString("0.00", Inv));
        sb.Append(", subt=1");
        sb.Append(", descr='").Append(sanitizedDescr).Append('\'');
        return sb.ToString();
    }

    private static string FormatNonFiscalDiscountLine(string label, decimal value, ReceiptDefaults defaults)
    {
        var descr = string.IsNullOrWhiteSpace(label) ? defaults.DiscountLabel : label;
        var description = PadDescription(descr, defaults.DescrMaxLen);
        var amount = (-value).ToString("0.00", Inv);
        return $"{description}      = {amount}";
    }

    private IEnumerable<ReceiptItem> EnumerateAllItems(EmitReceiptRequest request, ReceiptDefaults defaults)
    {
        if (request.Covers > 0 && request.CoverChargeUnitPrice is decimal coverPrice && coverPrice > 0m)
        {
            yield return new ReceiptItem
            {
                Description = defaults.CoverLabel,
                UnitPrice = coverPrice,
                Quantity = request.Covers,
                Reparto = request.Reparto ?? defaults.Reparto,
            };
        }
        foreach (var item in request.Items)
        {
            yield return item;
        }
    }

    private string FormatVendLine(ReceiptItem item, ReceiptDefaults defaults)
    {
        var rep = item.Reparto ?? defaults.Reparto;
        var price = item.UnitPrice.ToString("0.00", Inv);
        var description = PadDescription(item.Description, defaults.DescrMaxLen);

        var sb = new StringBuilder();
        sb.Append("vend rep=").Append(rep.ToString(Inv));
        sb.Append(", pre=").Append(price);
        if (item.Quantity != 1m)
        {
            sb.Append(", qty=").Append(item.Quantity.ToString("0.000", Inv));
        }
        sb.Append(", des='").Append(description).Append('\'');
        return sb.ToString();
    }

    private static string FormatNonFiscalLine(ReceiptItem item, ReceiptDefaults defaults)
    {
        var price = item.UnitPrice.ToString("0.00", Inv);
        var qty = item.Quantity.ToString("0.###", Inv);
        var description = PadDescription(item.Description, defaults.DescrMaxLen);
        return $"{description} x{qty} = {price}";
    }

    private static string PadDescription(string raw, int targetLen)
    {
        var sanitized = SanitizeDescription(raw);
        if (sanitized.Length >= targetLen)
        {
            return sanitized[..targetLen];
        }
        return sanitized.PadRight(targetLen, ' ');
    }

    private static string SanitizeDescription(string raw)
    {
        if (string.IsNullOrEmpty(raw)) return string.Empty;
        var sb = new StringBuilder(raw.Length);
        foreach (var ch in raw)
        {
            if (ch == '\'') { sb.Append("''"); continue; }
            if (ch < 0x20) { sb.Append(' '); continue; }
            sb.Append(ch);
        }
        return sb.ToString();
    }
}
