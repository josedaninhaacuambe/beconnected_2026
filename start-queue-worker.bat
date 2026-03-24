@echo off
title Beconnect Queue Worker
echo Iniciando Queue Worker (Redis)...
echo Pressiona Ctrl+C para parar.
echo.
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan queue:work --queue=default --tries=3 --sleep=1
pause
