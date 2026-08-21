@echo off
setlocal
cd /d "%~dp0"

where py >nul 2>nul
if %errorlevel%==0 (
    py -3 MapaLotowMigracja.py build
) else (
    python MapaLotowMigracja.py build
)

if errorlevel 1 (
    echo.
    echo MIGRACJA NIE POWIODLA SIE. Sprawdz najnowszy raport w katalogu reports.
) else (
    echo.
    echo Baza mapa_lotow_candidate zostala zbudowana i sprawdzona.
)
echo.
pause
