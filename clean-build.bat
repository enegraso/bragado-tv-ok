@echo off
cd /d "%~dp0"
echo === Clean + Build APK ===
echo.
echo [1/4] Limpiando build...
echo y | npx expo prebuild --non-interactive --clean
echo [2/4] Configurando Android...
call .\setup-android.bat
echo [3/4] Buildando APK...
echo 1. Debug
echo 2. Release
echo.
set /p choice="Elegí una opción (1/2): "
if "%choice%"=="1" (
    echo Buildando Debug...
    cd android
    .\gradlew --stop
    .\gradlew assembleDebug --no-parallel
) else if "%choice%"=="2" (
    echo Buildando Release...
    cd android
    .\gradlew --stop
    .\gradlew assembleRelease --no-parallel
) else (
    echo Opción inválida
    pause
    exit /b 1
)

echo [4/4] ¡Listo!
pause
