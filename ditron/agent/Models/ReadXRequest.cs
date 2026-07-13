namespace DitronAgent.Models;

public sealed class ReadXRequest
{
    public string IdempotencyKey { get; set; } = string.Empty;
}
