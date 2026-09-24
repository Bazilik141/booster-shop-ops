Option Explicit
Dim shell, fso, basePath, command
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
basePath = fso.GetParentFolderName(WScript.ScriptFullName)
command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File """ & basePath & "\launcher.ps1"" -Mode Start"
shell.Run command, 0, False
