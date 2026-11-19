#requires -Version 5.1
[CmdletBinding()]
param([string] $Php = 'C:\OSPanel\modules\PHP-8.5\php.exe')

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$backend = Join-Path $projectRoot 'services\backend'
$log = Join-Path $backend 'storage\logs\sub-develop-setup.log'
if (-not (Test-Path -LiteralPath $Php -PathType Leaf)) { throw "PHP not found: $Php" }
$settings = @{}
foreach ($line in Get-Content -LiteralPath (Join-Path $backend '.env')) {
    if ($line -match '^([A-Z_]+)=(.*)$') { $settings[$matches[1]] = $matches[2].Trim().Trim('"', "'") }
}
if ($settings.APP_ENV -ne 'local' -or $settings.APP_URL -notmatch '^https?://(localhost|127\.0\.0\.1|[^/]+\.local)(:\d+)?/?$' -or
    $settings.ONEC_API_ENABLED -ne 'false' -or $settings.LOCAL_SBER_SIMULATOR_ENABLED -ne 'true') {
    throw 'Expected a local .env with ONEC_API_ENABLED=false and LOCAL_SBER_SIMULATOR_ENABLED=true.'
}

function Invoke-Artisan([string[]] $Arguments) {
    & $Php (Join-Path $backend 'artisan') @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Artisan failed: $($Arguments -join ' ')" }
}

Start-Transcript -LiteralPath $log -Force | Out-Null
try {
    Push-Location $backend
    try {
        Invoke-Artisan -Arguments @('config:clear')
        Invoke-Artisan -Arguments @('migrate', '--no-interaction')
        Invoke-Artisan -Arguments @('view:clear')
        Invoke-Artisan -Arguments @('sber:simulator-setup')
        Invoke-Artisan -Arguments @('queue:restart')
        Invoke-Artisan -Arguments @('migrate:status', '--no-interaction')
        $artisan = Join-Path $backend 'artisan'
        $workers = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
            $_.CommandLine -like '*queue:work*' -and $_.CommandLine -like "*$artisan*"
        })
        if (-not $workers.Count) {
            Start-Process -FilePath $Php -ArgumentList @(('"' + $artisan + '"'), 'queue:work', '--queue=default', '--sleep=1', '--tries=3', '--timeout=60') `
                -WorkingDirectory $backend -WindowStyle Hidden `
                -RedirectStandardOutput (Join-Path $backend 'storage\logs\sub-develop-worker.log') `
                -RedirectStandardError (Join-Path $backend 'storage\logs\sub-develop-worker-error.log') | Out-Null
        }
        Write-Host 'SUB_DEVELOP_SETUP_OK'
        Write-Host 'Local default queue worker is running. Emails use the configured local mailer.'
    } finally { Pop-Location }
} finally { Stop-Transcript | Out-Null }
