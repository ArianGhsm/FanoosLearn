[CmdletBinding()]
param([string]$TaskName = "IntegratedDent-VPS-State-Backup")

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$runnerPath = Join-Path $PSScriptRoot "run-scheduled-vps-backup.ps1"
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument (
    "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$runnerPath`""
)
$daily = New-ScheduledTaskTrigger -Daily -At 8:00PM
$login = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 20)
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger @($daily, $login) `
    -Settings $settings -Description "Independent encrypted backups of IntegratedDent VPS runtime state" -Force | Out-Null
Write-Output "SCHEDULED_TASK=$TaskName"
