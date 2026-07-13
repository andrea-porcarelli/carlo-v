namespace DitronAgent.Models;

public sealed class ReadPropertiesRequest
{
    public int[] Properties { get; set; } = Array.Empty<int>();
}

public sealed class ReadPropertiesResponse
{
    public bool Ok { get; set; }

    public string? Error { get; set; }

    public string? RawCommand { get; set; }

    public string? RawErr { get; set; }

    public long ElapsedMs { get; set; }

    /// <summary>
    /// Map property_number → raw_value (stringa così come letta dalla cassa).
    /// </summary>
    public IDictionary<int, string> Values { get; set; } = new Dictionary<int, string>();
}
