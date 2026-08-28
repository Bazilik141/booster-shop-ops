@echo off
chcp 65001 >nul
title Booster Shop — зміна токена
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0launcher.ps1" -Mode ChangeToken
echo.
echo Натисни будь-яку клавішу, щоб закрити вікно.
pause >nul
