using DitronAgent.Models;

namespace DitronAgent.Services;

public interface IScontriniWriter
{
    Task<EmitReceiptResponse> WriteAndAwaitAsync(int receiptNumber, string command, CancellationToken cancellationToken);
}
