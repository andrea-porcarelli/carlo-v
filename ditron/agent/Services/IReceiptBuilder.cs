using DitronAgent.Models;

namespace DitronAgent.Services;

public interface IReceiptBuilder
{
    string Build(EmitReceiptRequest request);
}
