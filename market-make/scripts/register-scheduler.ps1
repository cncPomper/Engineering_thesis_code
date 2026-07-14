# Registers (or updates) the "market-make scheduler" Windows task that drives
# the Laravel schedule (app/Console/Kernel.php). Safe to re-run: -Force
# overwrites the existing task with these exact settings.
#
#   powershell -ExecutionPolicy Bypass -File scripts\register-scheduler.ps1
#
# The task fires every minute and runs run-scheduler.vbs, which calls
# `php artisan schedule:run` without flashing a console window. Laravel
# decides which jobs are actually due each minute.

$taskName = "market-make scheduler"
$vbs = Join-Path $PSScriptRoot "run-scheduler.vbs"

$action = New-ScheduledTaskAction -Execute "wscript.exe" -Argument "`"$vbs`""

# One trigger, repeating every minute indefinitely (no -RepetitionDuration
# means "repeat forever"; an explicit MaxValue is rejected by Task Scheduler).
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date `
    -RepetitionInterval (New-TimeSpan -Minutes 1)

# WakeToRun + battery flags: the pipeline jobs (16:15/21:30 UTC weekdays) are
# skipped entirely if the minute passes while the PC is asleep or the task is
# blocked on battery power — Laravel's schedule:run has no catch-up.
$settings = New-ScheduledTaskSettingsSet `
    -WakeToRun `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask -TaskName $taskName -Action $action `
    -Trigger $trigger -Settings $settings -Force | Out-Null

Write-Output "Task '$taskName' registered."
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName, State
