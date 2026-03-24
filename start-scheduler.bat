@echo off
title Beconnect Scheduler
echo Iniciando Scheduler (sanctum:prune-expired, etc.)...
echo Pressiona Ctrl+C para parar.
echo.
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan schedule:work
pause
