# ─── Build ────────────────────────────────────────────────────────────────────
FROM mcr.microsoft.com/dotnet/sdk:8.0-alpine AS build
WORKDIR /src
COPY PaymentService.csproj ./
RUN dotnet restore

COPY . .
RUN dotnet publish -c Release -o /app/publish --no-restore

# ─── Runtime — Debian (Alpine não tem ICU libs necessárias para Serilog) ──────
FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS runtime
RUN groupadd -r beconnect && useradd -r -g beconnect beconnect
WORKDIR /app
COPY --from=build /app/publish .
RUN mkdir -p logs && chown -R beconnect:beconnect /app

USER beconnect
EXPOSE 8082

HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
  CMD bash -c 'echo > /dev/tcp/localhost/8082' || exit 1

ENTRYPOINT ["dotnet", "PaymentService.dll"]
