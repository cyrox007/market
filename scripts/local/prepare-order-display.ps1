#requires -Version 5.1
[CmdletBinding()]
param([string] $Php = 'C:\OSPanel\modules\PHP-8.5\php.exe')
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$backend = Join-Path $root 'services\backend'
$settings = Get-Content -LiteralPath (Join-Path $backend '.env') -Raw
if ($settings -notmatch '(?m)^APP_ENV=local\r?$') { throw 'Expected APP_ENV=local.' }
Start-Transcript -LiteralPath (Join-Path $backend 'storage\logs\order-display-setup.log') -Force | Out-Null
try {
    Push-Location $backend
    try {
        foreach ($command in @('config:clear', 'migrate', 'route:clear', 'view:clear')) {
            & $Php (Join-Path $backend 'artisan') $command --no-interaction
            if ($LASTEXITCODE -ne 0) { throw "Artisan failed: $command" }
        }
        Write-Host 'ORDER_DISPLAY_SETUP_OK'
    } finally { Pop-Location }
} finally { Stop-Transcript | Out-Null }
