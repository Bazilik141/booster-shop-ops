@echo off
chcp 65001 >nul
title Booster Shop — перевірка заборон
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0negative-qa.ps1"
echo.
echo Натисни будь-яку клавішу, щоб закрити вікно.
pause >nul
