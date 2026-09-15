@echo off
wscript.exe //B "%~dp0start-hidden.vbs"
if errorlevel 1 powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0launcher.ps1" -Mode Start
if errorlevel 1 pause
