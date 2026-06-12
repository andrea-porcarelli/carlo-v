using System.Globalization;
using DitronAgent.Configuration;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

public sealed class ReceiptNumberAllocator : IReceiptNumberAllocator
{
    private readonly DitronAgentOptions _options;
    private readonly object _gate = new();

    public ReceiptNumberAllocator(IOptions<DitronAgentOptions> options)
    {
        _options = options.Value;
        EnsureFile();
    }

    public int Peek()
    {
        lock (_gate)
        {
            return ReadCurrent();
        }
    }

    public int Allocate()
    {
        lock (_gate)
        {
            var current = ReadCurrent();
            var next = current >= _options.CounterMax ? _options.CounterStart : current + 1;
            File.WriteAllText(_options.CounterFile, next.ToString(CultureInfo.InvariantCulture));
            return next;
        }
    }

    private void EnsureFile()
    {
        var dir = Path.GetDirectoryName(_options.CounterFile);
        if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
        {
            Directory.CreateDirectory(dir);
        }
        if (!File.Exists(_options.CounterFile))
        {
            var initial = _options.CounterStart - 1;
            File.WriteAllText(_options.CounterFile, initial.ToString(CultureInfo.InvariantCulture));
        }
    }

    private int ReadCurrent()
    {
        var raw = File.ReadAllText(_options.CounterFile).Trim();
        if (int.TryParse(raw, NumberStyles.Integer, CultureInfo.InvariantCulture, out var value))
        {
            return value;
        }
        return _options.CounterStart - 1;
    }
}
