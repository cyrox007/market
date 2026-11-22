#requires -Version 5.1
[CmdletBinding()]
param([string] $Php = 'C:\OSPanel\modules\PHP-8.5\php.exe')
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$backend = Join-Path $root 'services\backend'
$settings = Get-Content -LiteralPath (Join-Path $backend '.env') -Raw
if ($settings -notmatch '(?m)^APP_ENV=local\r?$' -or $settings -notmatch '(?m)^MAIL_MAILER=log\r?$') {
    throw 'Expected local APP_ENV=local and MAIL_MAILER=log.'
}
Start-Transcript -LiteralPath (Join-Path $backend 'storage\logs\notification-preferences-setup.log') -Force | Out-Null
try {
    Push-Location $backend
    try {
        $artisan = Join-Path $backend 'artisan'
        foreach ($command in @('config:clear', 'migrate', 'route:clear', 'view:clear', 'queue:restart')) {
            & $Php $artisan $command --no-interaction
            if ($LASTEXITCODE -ne 0) { throw "Artisan failed: $command" }
        }
        # Allow the previous worker to finish its current local job before restarting.
        for ($attempt = 0; $attempt -lt 10; $attempt++) {
            $workers = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
                $_.CommandLine -like '*queue:work*' -and $_.CommandLine -like "*$artisan*"
            })
            if (-not $workers.Count) { break }
            Start-Sleep -Seconds 1
        }
        if ($workers.Count) { throw 'Previous queue worker has not stopped yet. Retry after it finishes.' }
        Start-Process -FilePath $Php -ArgumentList @(('"' + $artisan + '"'), 'queue:work', '--queue=default', '--sleep=1', '--tries=3', '--timeout=60') `
            -WorkingDirectory $backend -WindowStyle Hidden `
            -RedirectStandardOutput (Join-Path $backend 'storage\logs\sub-develop-worker.log') `
            -RedirectStandardError (Join-Path $backend 'storage\logs\sub-develop-worker-error.log') | Out-Null
        Write-Host 'NOTIFICATION_PREFERENCES_SETUP_OK'
    } finally { Pop-Location }
} finally { Stop-Transcript | Out-Null }
