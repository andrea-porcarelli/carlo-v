using System.Text.RegularExpressions;
using DitronAgent.Configuration;

namespace DitronAgent.Services;

/// <summary>
/// Classifica il contenuto di scontrinoNN.err come errore reale, warning benigno, o vuoto.
///
/// Contesto: WinEcrCom sembra scrivere nel .err anche in alcuni casi in cui la cassa ha
/// emesso lo scontrino correttamente (info/warning). La logica originale considerava
/// ogni contenuto non-whitespace come errore, marcando come failed scontrini effettivamente
/// emessi. Con policy KeywordBased marchiamo errore solo se il testo matcha keyword
/// tipiche di codici errore (tabella pag. 31-33 WinEcrCom2.pdf).
/// </summary>
public static class ErrClassifier
{
    public sealed record Result(ErrKind Kind, string? DetectedKeyword)
    {
        public bool IsError => Kind == ErrKind.Error;
    }

    public enum ErrKind
    {
        Empty,
        Warning,
        Error,
    }

    public static Result Classify(string? content, DitronAgentOptions options)
    {
        if (string.IsNullOrWhiteSpace(content))
        {
            return new Result(ErrKind.Empty, null);
        }

        if (options.ErrPolicy == ErrClassificationPolicy.Strict)
        {
            return new Result(ErrKind.Error, "policy=Strict");
        }

        foreach (var pattern in options.ErrErrorKeywords ?? Array.Empty<string>())
        {
            if (string.IsNullOrWhiteSpace(pattern)) continue;
            try
            {
                if (Regex.IsMatch(content, pattern, RegexOptions.IgnoreCase | RegexOptions.Multiline))
                {
                    return new Result(ErrKind.Error, pattern);
                }
            }
            catch (RegexParseException)
            {
                // pattern configurato male: lo si salta silenziosamente per non bloccare l'agent
            }
        }

        return new Result(ErrKind.Warning, null);
    }
}
