namespace DitronAgent.Models;

public sealed class ReadXResponse
{
    public bool Ok { get; set; }

    public int ReceiptNumber { get; set; }

    public string? Error { get; set; }

    public string? RawCommand { get; set; }

    public string? RawErr { get; set; }

    public long ElapsedMs { get; set; }

    public string Mode { get; set; } = string.Empty;
}
