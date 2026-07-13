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

    /// <summary>
    /// Politica di classificazione del contenuto di scontrinoNN.err.
    /// - Strict: qualsiasi contenuto non-whitespace = errore (comportamento originale).
    /// - KeywordBased: errore solo se il contenuto matcha una keyword di ErrErrorKeywords,
    ///   altrimenti trattato come warning/info benigno (ok=true, RawErr conservato).
    /// </summary>
    public ErrClassificationPolicy ErrPolicy { get; set; } = ErrClassificationPolicy.KeywordBased;

    /// <summary>
    /// Regex/keyword (case-insensitive) che marcano il .err come errore reale.
    /// Usate solo se ErrPolicy = KeywordBased.
    /// </summary>
    public string[] ErrErrorKeywords { get; set; } = new[]
    {
        @"\berrore\b",
        @"\berror\b",
        @"\babort(?:ed)?\b",
        @"\btimeout\b",
        @"\bfault\b",
        @"\bfail(ed|ure)?\b",
        @"\bimpossibile\b",
        @"\bnon\s+ammess",
        @"\billegale\b",
        @"^\s*\d+\s+\d+\s+\S",
    };
}

public enum ErrClassificationPolicy
{
    Strict,
    KeywordBased,
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
