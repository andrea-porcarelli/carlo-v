namespace DitronAgent.Models;

public sealed class CloseDayRequest
{
    public string IdempotencyKey { get; set; } = string.Empty;

    public int? Tipo { get; set; }
}
