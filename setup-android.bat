@echo off
cd /d "%~dp0"
echo Configurando Android...

:: Actualizar gradle.properties
powershell -Command "$c = Get-Content 'android\gradle.properties' -Raw; $nl = [char]13 + [char]10; $c = $c -replace 'org\.gradle\.jvmargs=-Xmx2048m -XX:MaxMetaspaceSize=512m', ('org.gradle.jvmargs=-Xmx2048m -XX:MaxMetaspaceSize=512m -XX:+UseG1GC' + $nl + 'org.gradle.workers.max=2' + $nl + 'android.experimental.runInProcess=true'); Set-Content 'android\gradle.properties' $c -NoNewline"

:: Crear local.properties
echo sdk.dir=C:/Users/Federico/AppData/Local/Android/Sdk>android\local.properties

echo Listo.
