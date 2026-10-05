@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0set-telegram-webhook.ps1"
if errorlevel 1 (
  echo.
  echo Webhook belum berhasil disetel.
) else (
  echo.
  echo Selesai. Kamu bisa menutup jendela ini.
)
pause
