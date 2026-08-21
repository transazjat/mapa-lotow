@echo off
setlocal
cd /d "%~dp0"

echo UWAGA: ta operacja zastapi baze mapa_lotow zawartoscia bazy mapa_lotow_candidate.
echo Przed podmiana zostanie wykonana kopia obecnej bazy mapa_lotow.
echo.
set /p POTWIERDZENIE=Wpisz PROMUJ aby kontynuowac: 
if not "%POTWIERDZENIE%"=="PROMUJ" (
    echo Operacja anulowana.
    pause
    exit /b 1
)

where py >nul 2>nul
if %errorlevel%==0 (
    py -3 MapaLotowMigracja.py promote --confirm PROMUJ
) else (
    python MapaLotowMigracja.py promote --confirm PROMUJ
)

if errorlevel 1 (
    echo.
    echo PROMOCJA NIE POWIODLA SIE. Sprawdz najnowszy raport w katalogu reports.
) else (
    echo.
    echo Promocja zakonczona. Kandydat zostal zaimportowany jako mapa_lotow.
)
echo.
pause
