@echo off
:: Script de arranque do Beconnect no Windows
:: Executar: docker\start.bat

echo.
echo ==========================================
echo   Beconnect -- Arranque Docker (Windows)
echo ==========================================
echo.

:: 1. Copiar .env.docker para .env se nao existir
if not exist .env (
    echo [1/8] A copiar .env.docker para .env ...
    copy .env.docker .env
) else (
    echo [1/8] Ficheiro .env ja existe, a manter.
)

:: 2. Subir contentores
echo [2/8] A iniciar contentores Docker...
docker compose up -d --build
if %ERRORLEVEL% NEQ 0 (
    echo ERRO: Falha ao iniciar contentores. Verifica se o Docker Desktop esta a correr.
    pause
    exit /b 1
)

:: 3. Aguardar MySQL
echo [3/8] A aguardar MySQL ficar disponivel (30 segundos)...
timeout /t 30 /nobreak >nul

:: 4. Instalar dependencias PHP
echo [4/8] A instalar dependencias PHP...
docker compose exec app composer install --no-interaction --prefer-dist

:: 5. Executar migracoes
echo [5/8] A executar migracoes e seeders...
docker compose exec app php artisan migrate --seed --force

:: 6. Storage link
echo [6/8] A criar link de storage...
docker compose exec app php artisan storage:link

:: 7. Limpar caches
echo [7/8] A limpar caches...
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear

:: 8. Build frontend
echo [8/8] A compilar frontend...
docker compose --profile build run --rm node

echo.
echo ==========================================
echo   Beconnect pronto!
echo ==========================================
echo.
echo   Aplicacao:   http://localhost:8000
echo   phpMyAdmin:  http://localhost:8081
echo   Admin email: admin@beconnect.co.mz
echo   Admin pass:  Beconnect@2025
echo.
echo   MySQL externo: 127.0.0.1:3307
echo   User: beconnect / beconnect_secret
echo.
pause
