namespace DitronAgent.Models;

public sealed class EmitReceiptResponse
{
    public bool Ok { get; set; }

    public int ReceiptNumber { get; set; }

    public string? Error { get; set; }

    public string? RawCommand { get; set; }

    public string? RawErr { get; set; }

    public long ElapsedMs { get; set; }
}

public sealed class HealthResponse
{
    public string Status { get; set; } = "ok";

    public string Mode { get; set; } = string.Empty;

    public bool ScontriniFolderExists { get; set; }

    public bool CounterFileWritable { get; set; }

    public int? NextReceiptNumber { get; set; }

    public DateTimeOffset Now { get; set; } = DateTimeOffset.UtcNow;

    public string Version { get; set; } = "0.1.0";
}
