@echo off
setlocal
cd /d "%~dp0"

where py >nul 2>nul
if %errorlevel%==0 (
    py -3 -m pip install -r requirements.txt
) else (
    python -m pip install -r requirements.txt
)

if errorlevel 1 (
    echo.
    echo INSTALACJA NIE POWIODLA SIE.
) else (
    echo.
    echo Wymagane biblioteki zostaly zainstalowane.
)
echo.
pause
