$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$dest = Join-Path $root 'deploy\hostinger'
$publicDest = Join-Path $dest 'public_html'
$privateDest = Join-Path $dest 'private'
$htTemplate = Join-Path $dest '_templates\.htaccess.production'

Write-Host "ROOT=$root"

foreach ($extra in @('pkg_public_html', 'pkg_private')) {
    $p = Join-Path $dest $extra
    if (Test-Path $p) { Remove-Item $p -Recurse -Force }
}

if (Test-Path $publicDest) { Remove-Item $publicDest -Recurse -Force }
if (Test-Path $privateDest) { Remove-Item $privateDest -Recurse -Force }
New-Item -ItemType Directory -Force -Path $publicDest, $privateDest | Out-Null

# public
$rc = (Start-Process -FilePath robocopy -ArgumentList @(
    (Join-Path $root 'public'), $publicDest, '/E', '/XD', 'uploads', '/XF', 'hash.php', '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS', '/NP'
) -Wait -PassThru).ExitCode
if ($rc -ge 8) { throw "robocopy public failed: $rc" }

Copy-Item $htTemplate (Join-Path $publicDest '.htaccess') -Force

foreach ($d in @('uploads', 'uploads\consultorios', 'uploads\consultorios\portadas', 'uploads\personas')) {
    $p = Join-Path $publicDest $d
    New-Item -ItemType Directory -Force -Path $p | Out-Null
    Set-Content -Path (Join-Path $p '.gitkeep') -Value ''
}

# private parts
foreach ($pair in @(
    @('app', 'app'),
    @('routes', 'routes'),
    @('vendor', 'vendor')
)) {
    $src = Join-Path $root $pair[0]
    $dst = Join-Path $privateDest $pair[1]
    $rc = (Start-Process -FilePath robocopy -ArgumentList @(
        $src, $dst, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NC', '/NS', '/NP'
    ) -Wait -PassThru).ExitCode
    if ($rc -ge 8) { throw "robocopy $($pair[0]) failed: $rc" }
}

Copy-Item (Join-Path $root 'composer.json') (Join-Path $privateDest 'composer.json') -Force
if (Test-Path (Join-Path $root 'composer.lock')) {
    Copy-Item (Join-Path $root 'composer.lock') (Join-Path $privateDest 'composer.lock') -Force
}
Copy-Item (Join-Path $root '.env.production.example') (Join-Path $privateDest '.env.production.example') -Force

foreach ($d in @('storage', 'storage\tmp', 'storage\logs')) {
    $p = Join-Path $privateDest $d
    New-Item -ItemType Directory -Force -Path $p | Out-Null
    Set-Content -Path (Join-Path $p '.gitkeep') -Value ''
}

$scriptsDest = Join-Path $privateDest 'scripts'
New-Item -ItemType Directory -Force -Path $scriptsDest | Out-Null
Copy-Item (Join-Path $dest 'scripts\set_admin_password.php') $scriptsDest -Force
Copy-Item (Join-Path $dest 'scripts\validar_urls_produccion.php') $scriptsDest -Force

Write-Host 'public_html files:' (Get-ChildItem $publicDest -Force | Measure-Object).Count
Write-Host 'private files top:' ((Get-ChildItem $privateDest -Force | Select-Object -ExpandProperty Name) -join ', ')
Write-Host 'DONE'
