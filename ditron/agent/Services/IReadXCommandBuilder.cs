using DitronAgent.Models;

namespace DitronAgent.Services;

public interface IReadXCommandBuilder
{
    string Build(ReadXRequest request);
}
