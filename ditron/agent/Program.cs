using System.Text.Json;
using System.Text.Json.Serialization;
using DitronAgent.Configuration;
using DitronAgent.Models;
using DitronAgent.Services;

var builder = WebApplication.CreateBuilder(args);

builder.Host.UseWindowsService();

builder.Services.Configure<DitronAgentOptions>(builder.Configuration.GetSection(DitronAgentOptions.SectionName));

builder.Services.AddSingleton<IReceiptNumberAllocator, ReceiptNumberAllocator>();
builder.Services.AddSingleton<IReceiptBuilder, ReceiptBuilder>();
builder.Services.AddSingleton<ICloseDayCommandBuilder, CloseDayCommandBuilder>();
builder.Services.AddSingleton<IScontriniWriter, ScontriniWriter>();

builder.Services.ConfigureHttpJsonOptions(options =>
{
    options.SerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
    options.SerializerOptions.DictionaryKeyPolicy = JsonNamingPolicy.SnakeCaseLower;
    options.SerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
});

var app = builder.Build();

app.Use(async (ctx, next) =>
{
    var options = ctx.RequestServices.GetRequiredService<Microsoft.Extensions.Options.IOptions<DitronAgentOptions>>().Value;
    if (!string.IsNullOrEmpty(options.AuthToken))
    {
        if (!ctx.Request.Headers.TryGetValue("Authorization", out var header) || header != $"Bearer {options.AuthToken}")
        {
            ctx.Response.StatusCode = 401;
            await ctx.Response.WriteAsync("Unauthorized");
            return;
        }
    }
    await next();
});

app.MapGet("/health", (Microsoft.Extensions.Options.IOptions<DitronAgentOptions> opt, IReceiptNumberAllocator allocator) =>
{
    var o = opt.Value;
    bool counterWritable;
    int? next = null;
    try
    {
        next = allocator.Peek() + 1;
        counterWritable = true;
    }
    catch
    {
        counterWritable = false;
    }
    return Results.Ok(new HealthResponse
    {
        Mode = o.Mode.ToString(),
        ScontriniFolderExists = Directory.Exists(o.ScontriniFolder),
        CounterFileWritable = counterWritable,
        NextReceiptNumber = next,
    });
});

app.MapPost("/emit-receipt", async (
    EmitReceiptRequest request,
    IReceiptBuilder builder,
    IReceiptNumberAllocator allocator,
    IScontriniWriter writer,
    CancellationToken cancellationToken) =>
{
    if (request is null)
    {
        return Results.BadRequest(new EmitReceiptResponse { Ok = false, Error = "Empty body" });
    }
    if (string.IsNullOrWhiteSpace(request.IdempotencyKey))
    {
        return Results.BadRequest(new EmitReceiptResponse { Ok = false, Error = "idempotency_key is required" });
    }

    var command = builder.Build(request);
    var receiptNumber = allocator.Allocate();
    var response = await writer.WriteAndAwaitAsync(receiptNumber, command, cancellationToken);
    return response.Ok ? Results.Ok(response) : Results.UnprocessableEntity(response);
});

app.MapPost("/close-day", async (
    CloseDayRequest request,
    ICloseDayCommandBuilder builder,
    IReceiptNumberAllocator allocator,
    IScontriniWriter writer,
    Microsoft.Extensions.Options.IOptions<DitronAgentOptions> opt,
    CancellationToken cancellationToken) =>
{
    if (request is null)
    {
        return Results.BadRequest(new CloseDayResponse { Ok = false, Error = "Empty body" });
    }
    if (string.IsNullOrWhiteSpace(request.IdempotencyKey))
    {
        return Results.BadRequest(new CloseDayResponse { Ok = false, Error = "idempotency_key is required" });
    }

    var command = builder.Build(request);
    var receiptNumber = allocator.Allocate();
    var emitResponse = await writer.WriteAndAwaitAsync(receiptNumber, command, cancellationToken);

    var response = new CloseDayResponse
    {
        Ok = emitResponse.Ok,
        ReceiptNumber = emitResponse.ReceiptNumber,
        Error = emitResponse.Error,
        RawCommand = emitResponse.RawCommand,
        RawErr = emitResponse.RawErr,
        ElapsedMs = emitResponse.ElapsedMs,
        Mode = opt.Value.Mode.ToString(),
    };

    return response.Ok ? Results.Ok(response) : Results.UnprocessableEntity(response);
});

app.Run();
