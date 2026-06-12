FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY DitronAgent.csproj ./
RUN dotnet restore --runtime win-x64
COPY . .
RUN dotnet publish DitronAgent.csproj \
    -c Release \
    -r win-x64 \
    --self-contained true \
    -p:PublishSingleFile=true \
    -p:PublishTrimmed=false \
    -o /out

FROM scratch AS artifact
COPY --from=build /out /

FROM mcr.microsoft.com/dotnet/sdk:8.0 AS dev
WORKDIR /app
ENV ASPNETCORE_ENVIRONMENT=Development
ENV ASPNETCORE_URLS=http://0.0.0.0:9090
ENV DOTNET_USE_POLLING_FILE_WATCHER=1
EXPOSE 9090
CMD ["sh", "-c", "dotnet restore && dotnet run --no-launch-profile --no-restore"]
