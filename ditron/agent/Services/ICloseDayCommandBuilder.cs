using DitronAgent.Models;

namespace DitronAgent.Services;

public interface ICloseDayCommandBuilder
{
    string Build(CloseDayRequest request);
}
