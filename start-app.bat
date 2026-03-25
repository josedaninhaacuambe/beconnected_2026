@echo off
title Beconnect - Arrancar Aplicacao
color 0A
echo ============================================
echo   BECONNECT - Iniciando todos os servicos
echo ============================================
echo.

:: 1. MySQL
echo [1/4] A iniciar MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    start /B "" "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --datadir=C:\laragon\data\mysql --port=3306 --bind-address=127.0.0.1
    timeout /t 3 /nobreak >NUL
    echo     MySQL iniciado na porta 3306
) else (
    echo     MySQL ja esta a correr
)

:: 2. Redis
echo [2/4] A iniciar Redis...
tasklist /FI "IMAGENAME eq redis-server.exe" 2>NUL | find /I "redis-server.exe" >NUL
if errorlevel 1 (
    start /B "" "C:\laragon\bin\redis\redis-x64-5.0.14.1\redis-server.exe" --port 6381
    timeout /t 2 /nobreak >NUL
    echo     Redis iniciado na porta 6381
) else (
    echo     Redis ja esta a correr
)

:: 3. Apache
echo [3/4] A iniciar Apache...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if errorlevel 1 (
    start /B "" "C:\laragon\bin\apache\httpd-2.4.66-260107-Win64-VS18\bin\httpd.exe"
    timeout /t 2 /nobreak >NUL
    echo     Apache iniciado na porta 80
) else (
    echo     Apache ja esta a correr
)

:: 4. PHP Artisan Serve
echo [4/4] A iniciar PHP (porta 8000)...
tasklist /FI "IMAGENAME eq php.exe" 2>NUL | find /I "php.exe" >NUL
if errorlevel 1 (
    cd /d C:\laragon\www\Beconnect
    start "Beconnect PHP Server" "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan serve --host=0.0.0.0 --port=8000
    timeout /t 2 /nobreak >NUL
    echo     PHP Server iniciado na porta 8000
) else (
    echo     PHP ja esta a correr
)

echo.
echo ============================================
echo   Aplicacao disponivel em:
echo   http://localhost:8000
echo ============================================
echo.
pause
