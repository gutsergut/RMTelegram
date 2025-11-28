Param([string]$Version = '')
$ErrorActionPreference = 'Stop'

function Find-7Zip {
    $c = Get-Command 7z -ErrorAction SilentlyContinue
    if ($c) { Write-Host "Found 7-Zip: $($c.Source)" -ForegroundColor Green; return $c.Source }
    $paths = @("C:\Program Files\7-Zip\7z.exe", "${env:ProgramFiles}\7-Zip\7z.exe")
    foreach ($p in $paths) { if (Test-Path $p) { Write-Host "Found: $p" -ForegroundColor Green; return $p } }
    Write-Warning "7-Zip not found"
    return $null
}

function New-ZipFromStage {
    Param([string]$StageDir, [string]$ZipPath, [string]$SevenZipExe)
    if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }
    Push-Location $StageDir
    try {
        if ($SevenZipExe) {
            Write-Host "Creating archive with 7-Zip..." -ForegroundColor Cyan
            & $SevenZipExe a -tzip -mx=9 -mm=Deflate -- $ZipPath * | Out-Null
            if ($LASTEXITCODE -ne 0) { throw "7-Zip failed" }
            Write-Host "Archive created" -ForegroundColor Green
        } else {
            Write-Host "Creating archive with Compress-Archive..." -ForegroundColor Yellow
            Compress-Archive -Path * -DestinationPath $ZipPath -CompressionLevel Optimal -Force
            Write-Host "Archive created" -ForegroundColor Green
        }
    } finally { Pop-Location }
}

function Read-ComponentVersion {
    Param([string]$ManifestPath)
    if (!(Test-Path $ManifestPath)) { return '' }
    $xml = [xml](Get-Content -Raw -Path $ManifestPath)
    return ($xml.extension.version | Select-Object -First 1)
}

function Zip-Child {
    Param([string]$SrcDir, [string]$ZipName, [string]$SevenZipExe, [string]$StageDir)
    $ZipPath = Join-Path $StageDir $ZipName
    if (!(Test-Path $SrcDir)) { return }
    if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }
    Write-Host "Creating $ZipName..." -ForegroundColor Cyan -NoNewline
    Push-Location $SrcDir
    try {
        if ($SevenZipExe) {
            & $SevenZipExe a -tzip -mx=9 -mm=Deflate -- $ZipPath * 2>&1 | Out-Null
            if ($LASTEXITCODE -eq 0) { Write-Host " OK" -ForegroundColor Green }
            else { Write-Host " FAIL" -ForegroundColor Red; throw "Failed" }
        } else {
            Compress-Archive -Path * -DestinationPath $ZipPath -CompressionLevel Optimal -Force
            Write-Host " OK" -ForegroundColor Green
        }
    } finally { Pop-Location }
    Remove-Item -Recurse -Force $SrcDir
}

Write-Host "Starting build process..." -ForegroundColor Cyan
$Root = (Resolve-Path "$PSScriptRoot\..").Path
$Dist = Join-Path $Root 'dist'
if (!(Test-Path $Dist)) { New-Item -ItemType Directory -Path $Dist | Out-Null }
$Stage = Join-Path $Root 'build\package'
if (Test-Path $Stage) { Remove-Item -Recurse -Force $Stage }
New-Item -ItemType Directory -Path $Stage | Out-Null

$PackageSource = Join-Path $Root 'com_radicalmart_telegram-package'

Copy-Item -Path (Join-Path $PackageSource 'pkg_radicalmart_telegram.xml') -Destination $Stage -Force
Copy-Item -Path (Join-Path $PackageSource 'script.php') -Destination $Stage -Force
New-Item -ItemType Directory -Path (Join-Path $Stage 'language\ru-RU') -Force | Out-Null
Copy-Item -Path (Join-Path $Root 'administrator\language\ru-RU\ru-RU.pkg_radicalmart_telegram.sys.ini') -Destination (Join-Path $Stage 'language\ru-RU') -Force
New-Item -ItemType Directory -Path (Join-Path $Stage 'language\en-GB') -Force | Out-Null
Copy-Item -Path (Join-Path $Root 'administrator\language\en-GB\en-GB.pkg_radicalmart_telegram.sys.ini') -Destination (Join-Path $Stage 'language\en-GB') -Force

$PackagesDir = Join-Path $Stage 'packages'
if (Test-Path $PackagesDir) { Remove-Item -Recurse -Force $PackagesDir }
New-Item -ItemType Directory -Path $PackagesDir -Force | Out-Null

$AdminSrc = Join-Path $Root 'administrator\components\com_radicalmart_telegram'
$AdminDst = Join-Path $Stage 'administrator\components\com_radicalmart_telegram'
New-Item -ItemType Directory -Path $AdminDst -Force | Out-Null
@('radicalmart_telegram.xml','config.xml','forms','language','services','sql','src','tmpl') | ForEach-Object {
    $srcPath = Join-Path $AdminSrc $_
    if (Test-Path $srcPath) {
        $dstPath = Join-Path $AdminDst $_
        if ((Get-Item $srcPath).PSIsContainer) { Copy-Item -Path $srcPath -Destination $dstPath -Recurse -Force }
        else { Copy-Item -Path $srcPath -Destination $AdminDst -Force }
    }
}

$SiteSrc = Join-Path $Root 'components\com_radicalmart_telegram'
$SiteDst = Join-Path $AdminDst 'site'
if (Test-Path $SiteDst) { Remove-Item -Recurse -Force $SiteDst }
New-Item -ItemType Directory -Path $SiteDst | Out-Null
@('services','src','tmpl','language') | ForEach-Object {
    $from = Join-Path $SiteSrc $_
    if (Test-Path $from) { Copy-Item -Path $from -Destination (Join-Path $SiteDst $_) -Recurse -Force }
}

$SiteLangSrc = Join-Path $SiteSrc 'language\ru-RU\com_radicalmart_telegram.ini'
if (Test-Path $SiteLangSrc) {
    New-Item -ItemType Directory -Path (Join-Path $SiteDst 'language\ru-RU') -Force | Out-Null
    Copy-Item -Path $SiteLangSrc -Destination (Join-Path $SiteDst 'language\ru-RU') -Force
}

$PluginsAllow = @{
    'plugins\system\radicalmart_telegram' = @('radicalmart_telegram.xml','radicalmart_telegram.php','services','src','language');
    'plugins\task\radicalmart_telegram_fetch' = @('radicalmart_telegram_fetch.xml','services','src','language');
    'plugins\radicalmart_payment\telegramcards' = @('telegramcards.xml','telegramcards.php','language');
    'plugins\radicalmart_payment\telegramstars' = @('telegramstars.xml','telegramstars.php','language');
    'plugins\radicalmart\telegram_notifications' = @('telegram_notifications.xml','services','src','language');
}
foreach ($kv in $PluginsAllow.GetEnumerator()) {
    $base = Join-Path $Root $kv.Key
    $dstBase = Join-Path $Stage $kv.Key
    New-Item -ItemType Directory -Path $dstBase -Force | Out-Null
    foreach ($item in $kv.Value) {
        $srcPath = Join-Path $base $item
        if (Test-Path $srcPath) {
            $dstPath = Join-Path $dstBase $item
            if ((Test-Path $srcPath) -and (Get-Item $srcPath).PSIsContainer) { Copy-Item -Path $srcPath -Destination $dstPath -Recurse -Force }
            else { Copy-Item -Path $srcPath -Destination $dstBase -Force }
        }
    }
}

$MediaSrc = Join-Path $Root 'media\com_radicalmart_telegram'
$MediaDst = Join-Path $Stage 'media\com_radicalmart_telegram'
Copy-Item -Path $MediaSrc -Destination $MediaDst -Recurse -Force

# Verify new WebApp JS exists in package stage
$AppJs = Join-Path $MediaDst 'js\app.js'
if (!(Test-Path $AppJs)) {
    Write-Error "Missing media JS: $AppJs"
}
else {
    $len = (Get-Item $AppJs).Length
    $kb = [math]::Round($len / 1KB, 1)
    Write-Host "Included WebApp JS: media/com_radicalmart_telegram/js/app.js ($kb KB)" -ForegroundColor Green
}

Write-Host "`nSearching for 7-Zip..." -ForegroundColor Cyan
$Seven = Find-7Zip
Write-Host ""

Zip-Child -SrcDir $AdminDst -ZipName 'com_radicalmart_telegram.zip' -SevenZipExe $Seven -StageDir $PackagesDir
Zip-Child -SrcDir (Join-Path $Stage 'plugins\system\radicalmart_telegram') -ZipName 'plg_system_radicalmart_telegram.zip' -SevenZipExe $Seven -StageDir $PackagesDir
Zip-Child -SrcDir (Join-Path $Stage 'plugins\task\radicalmart_telegram_fetch') -ZipName 'plg_task_radicalmart_telegram_fetch.zip' -SevenZipExe $Seven -StageDir $PackagesDir
Zip-Child -SrcDir (Join-Path $Stage 'plugins\radicalmart_payment\telegramcards') -ZipName 'plg_radicalmart_payment_telegramcards.zip' -SevenZipExe $Seven -StageDir $PackagesDir
Zip-Child -SrcDir (Join-Path $Stage 'plugins\radicalmart_payment\telegramstars') -ZipName 'plg_radicalmart_payment_telegramstars.zip' -SevenZipExe $Seven -StageDir $PackagesDir
Zip-Child -SrcDir (Join-Path $Stage 'plugins\radicalmart\telegram_notifications') -ZipName 'plg_radicalmart_telegram_notifications.zip' -SevenZipExe $Seven -StageDir $PackagesDir

Remove-Item -Recurse -Force (Join-Path $Stage 'plugins') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $Stage 'administrator\components') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $Stage 'components') -ErrorAction SilentlyContinue

Get-ChildItem -Path $Stage -Filter '*.zip' | ForEach-Object { Copy-Item -Path $_.FullName -Destination (Join-Path $Dist $_.Name) -Force }

if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = Read-ComponentVersion (Join-Path $AdminSrc 'radicalmart_telegram.xml')
    if ([string]::IsNullOrWhiteSpace($Version)) { $Version = '0.1.0' }
}
$ZipName = "pkg_radicalmart_telegram-$Version.zip"
$ZipPath = Join-Path $Dist $ZipName
if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }

Write-Host "`nCreating final package..." -ForegroundColor Cyan
New-ZipFromStage -StageDir $Stage -ZipPath $ZipPath -SevenZipExe $Seven

Write-Host "`nBUILD SUCCESSFUL" -ForegroundColor Green
Write-Host "Package: $ZipPath" -ForegroundColor White
$zipSize = (Get-Item $ZipPath).Length
$sizeMB = [math]::Round($zipSize / 1MB, 2)
Write-Host "Size: $sizeMB MB" -ForegroundColor White
Write-Host ""
