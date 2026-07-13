namespace DitronAgent.Configuration;

public sealed class DitronAgentOptions
{
    public const string SectionName = "DitronAgent";

    public string ScontriniFolder { get; set; } = @"C:\Program Files (x86)\Ditron\WinEcrCom 3.0\Utilities\Scontrini";

    public string CounterFile { get; set; } = @"C:\ProgramData\DitronAgent\counter.txt";

    public int CounterStart { get; set; } = 200;

    public int CounterMax { get; set; } = 9999;

    public int ErrPollingTimeoutMs { get; set; } = 15000;

    public int ErrPollingIntervalMs { get; set; } = 100;

    public ReceiptDefaults Defaults { get; set; } = new();

    public ReceiptMode Mode { get; set; } = ReceiptMode.NonFiscal;

    public string? AuthToken { get; set; }

    public string LogwecFile { get; set; } = @"C:\logwec_1.txt";
}

public sealed class ReceiptDefaults
{
    public int Reparto { get; set; } = 1;

    public int Tender { get; set; } = 5;

    public string CoverLabel { get; set; } = "COPERTO";

    public string DiscountLabel { get; set; } = "SCONTO";

    public int DescrMaxLen { get; set; } = 16;
}

public enum ReceiptMode
{
    NonFiscal,
    Fiscal,
}
