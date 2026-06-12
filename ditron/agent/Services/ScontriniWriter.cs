using System.Diagnostics;
using System.Globalization;
using System.Text;
using DitronAgent.Configuration;
using DitronAgent.Models;
using Microsoft.Extensions.Options;

namespace DitronAgent.Services;

public sealed class ScontriniWriter : IScontriniWriter
{
    private readonly DitronAgentOptions _options;
    private readonly ILogger<ScontriniWriter> _logger;

    public ScontriniWriter(IOptions<DitronAgentOptions> options, ILogger<ScontriniWriter> logger)
    {
        _options = options.Value;
        _logger = logger;
    }

    public async Task<EmitReceiptResponse> WriteAndAwaitAsync(int receiptNumber, string command, CancellationToken cancellationToken)
    {
        Directory.CreateDirectory(_options.ScontriniFolder);

        var nn = receiptNumber.ToString("D2", CultureInfo.InvariantCulture);
        var txtPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.txt");
        var errPath = Path.Combine(_options.ScontriniFolder, $"scontrino{nn}.err");

        if (File.Exists(errPath))
        {
            File.Delete(errPath);
        }

        var stopwatch = Stopwatch.StartNew();

        await File.WriteAllTextAsync(txtPath, command, new UTF8Encoding(false), cancellationToken);
        _logger.LogInformation("Receipt {ReceiptNumber} written to {Path} ({Bytes} bytes)", receiptNumber, txtPath, command.Length);

        var errContent = await WaitForErrAsync(txtPath, errPath, cancellationToken);
        stopwatch.Stop();

        var response = new EmitReceiptResponse
        {
            ReceiptNumber = receiptNumber,
            RawCommand = command,
            ElapsedMs = stopwatch.ElapsedMilliseconds,
        };

        if (errContent is null)
        {
            response.Ok = false;
            response.Error = $"Timeout: WinEcrCom did not produce {Path.GetFileName(errPath)} within {_options.ErrPollingTimeoutMs}ms";
            _logger.LogWarning("Receipt {ReceiptNumber} timed out after {Elapsed}ms", receiptNumber, response.ElapsedMs);
            return response;
        }

        response.RawErr = errContent;
        if (string.IsNullOrWhiteSpace(errContent))
        {
            response.Ok = true;
            _logger.LogInformation("Receipt {ReceiptNumber} succeeded in {Elapsed}ms", receiptNumber, response.ElapsedMs);
        }
        else
        {
            response.Ok = false;
            response.Error = errContent.Trim();
            _logger.LogWarning("Receipt {ReceiptNumber} failed: {Error}", receiptNumber, response.Error);
        }

        return response;
    }

    private async Task<string?> WaitForErrAsync(string txtPath, string errPath, CancellationToken cancellationToken)
    {
        var deadline = DateTime.UtcNow.AddMilliseconds(_options.ErrPollingTimeoutMs);
        while (DateTime.UtcNow < deadline)
        {
            cancellationToken.ThrowIfCancellationRequested();
            if (File.Exists(errPath) && !File.Exists(txtPath))
            {
                try
                {
                    return await File.ReadAllTextAsync(errPath, cancellationToken);
                }
                catch (IOException)
                {
                    // file still being written by spooler — retry next tick
                }
            }
            await Task.Delay(_options.ErrPollingIntervalMs, cancellationToken);
        }
        return null;
    }
}
