@echo off
REM Inicia 8 workers PHP em portas diferentes
SET PHP=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
SET APP=c:\laragon\www\Beconnect

echo Iniciando 8 workers PHP...

start /B cmd /c "%PHP% %APP%\artisan serve --port=8001 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8002 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8003 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8004 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8005 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8006 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8007 --no-interaction > NUL 2>&1"
start /B cmd /c "%PHP% %APP%\artisan serve --port=8008 --no-interaction > NUL 2>&1"

echo Workers iniciados nas portas 8001-8008
echo Aguarda 3 segundos...
timeout /t 3 /nobreak > NUL
echo Pronto!
