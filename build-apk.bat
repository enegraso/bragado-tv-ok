@echo off
cd /d "%~dp0"
echo === Build APK ===
echo 1. Debug
echo 2. Release
echo.
set /p choice="Elegí una opción (1/2): "
if "%choice%"=="1" (
    echo Buildando Debug...
    node bump-version.js
	cd android
	.\gradlew --stop
    .\gradlew assembleDebug --no-parallel
) else if "%choice%"=="2" (
    echo Buildando Release...
    node bump-version.js
	cd android
	.\gradlew --stop
    .\gradlew assembleRelease --no-parallel
) else (
    echo Opción inválida
)

pause
