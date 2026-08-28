@echo off
chcp 65001 >nul
title Booster Shop — 3D-друк
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0launcher.ps1" -Mode Start
if errorlevel 1 (
  echo.
  echo Натисни будь-яку клавішу, щоб закрити вікно.
  pause >nul
)
