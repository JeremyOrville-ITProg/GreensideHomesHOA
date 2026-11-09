@echo off
setlocal

cd /d "%~dp0"

set "PHP=C:\xampp\php\php.exe"
set "APP_DIR=%~dp0"
if "%APP_DIR:~-1%"=="\" set "APP_DIR=%APP_DIR:~0,-1%"
set "PORT=8080"
set "URL=http://127.0.0.1:%PORT%/"

if not exist "%PHP%" (
    echo Could not find XAMPP PHP at "%PHP%".
    echo Edit start_portal.bat and set PHP to your php.exe location.
    goto failed
)

if not exist "router.php" (
    echo Could not find router.php in "%CD%".
    echo Keep start_portal.bat in the GreensideHomesHOA project folder.
    goto failed
)

echo Starting the Greenside Homes portal without Apache...
echo.
echo Address: %URL%
echo Keep this window open while using the portal.
echo Press Ctrl+C to stop the server.
echo.

start "" "%URL%"
"%PHP%" -S 127.0.0.1:%PORT% -t "%APP_DIR%" "%APP_DIR%\router.php"

echo.
echo The PHP web server has stopped. The portal is no longer available.
goto stopped

:failed
echo.
echo The portal could not start. Read the message above, then press any key to close.
pause >nul
exit /b 1

:stopped
echo Press any key to close this window.
pause >nul
endlocal
