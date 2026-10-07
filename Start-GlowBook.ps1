param(
    [ValidateRange(1024,65535)][int]$Port = 8000,
    [string]$PhpPath = 'php',
    [string]$IniPath = '',
    [switch]$Check
)
$ErrorActionPreference = 'Stop'
Push-Location $PSScriptRoot
$previousPhpRc = $env:PHPRC
try {
    # Optional machine settings are ignored by Git. Explicit arguments take precedence.
    if (Test-Path 'glowbook.local.json') {
        $localSettings = Get-Content 'glowbook.local.json' -Raw | ConvertFrom-Json
        if (!$PSBoundParameters.ContainsKey('PhpPath') -and $localSettings.PhpPath) { $PhpPath = $localSettings.PhpPath }
        if (!$PSBoundParameters.ContainsKey('IniPath') -and $localSettings.IniPath) { $IniPath = $localSettings.IniPath }
    }
    $phpCommand = Get-Command $PhpPath -CommandType Application -ErrorAction Stop
    $phpArguments = @()
    if ($IniPath) {
        $env:PHPRC = (Resolve-Path -LiteralPath $IniPath).Path
        $phpArguments = @('-c', $env:PHPRC)
    }
    $phpVersion = & $phpCommand.Source @phpArguments -r 'echo PHP_VERSION_ID;'
    if ($LASTEXITCODE -ne 0 -or [int]$phpVersion -lt 80300) { throw 'PHP 8.3 or newer is required.' }
    $modules = @(& $phpCommand.Source @phpArguments -m)
    if ($LASTEXITCODE -ne 0) { throw 'Cannot load PHP modules.' }
    foreach ($requiredModule in @('mbstring','openssl','pdo_mysql','fileinfo','curl','zip')) {
        if ($modules -notcontains $requiredModule) { throw "Enable PHP extension: $requiredModule" }
    }
    if (!(Test-Path .env)) { throw 'Missing .env. Follow README setup before starting.' }
    if (!(Test-Path vendor/autoload.php)) { throw 'Missing dependencies. Run composer install first.' }
    if ($Check) {
        Write-Output 'PHP version, required extensions, .env and dependencies: OK.'
    } else {
        # PHPRC is inherited by the web worker; -c alone only configures the Artisan parent.
        & $phpCommand.Source @phpArguments artisan serve --host=127.0.0.1 --port=$Port --no-reload
        if ($LASTEXITCODE -ne 0) { throw 'Laravel server stopped with an error.' }
    }
} finally {
    $env:PHPRC = $previousPhpRc
    Pop-Location
}
