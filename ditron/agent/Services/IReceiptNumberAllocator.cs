namespace DitronAgent.Services;

public interface IReceiptNumberAllocator
{
    int Peek();

    int Allocate();
}
