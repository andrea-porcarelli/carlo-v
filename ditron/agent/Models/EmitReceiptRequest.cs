namespace DitronAgent.Models;

public sealed class EmitReceiptRequest
{
    public string IdempotencyKey { get; set; } = string.Empty;

    public int? TableNumber { get; set; }

    public int Covers { get; set; }

    public decimal? CoverChargeUnitPrice { get; set; }

    public List<ReceiptItem> Items { get; set; } = new();

    public int? Tender { get; set; }

    public int? Reparto { get; set; }
}

public sealed class ReceiptItem
{
    public string Description { get; set; } = string.Empty;

    public decimal UnitPrice { get; set; }

    public decimal Quantity { get; set; } = 1m;

    public int? Reparto { get; set; }
}
