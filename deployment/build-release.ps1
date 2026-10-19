param([string]$ReleaseName)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$commit = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0) { throw 'No se pudo identificar el commit.' }
if (git status --porcelain --untracked-files=normal) {
    throw 'Guarda y revisa los cambios antes de construir una entrega.'
}
if (-not $ReleaseName) { $ReleaseName = 'demo-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + $commit.Substring(0, 8) }
if ($ReleaseName -notmatch '^[a-z0-9][a-z0-9-]{1,70}$') { throw 'Identificador de entrega no válido.' }
$releaseDir = Join-Path $PWD "storage/app/releases/$ReleaseName"
if (Test-Path -LiteralPath $releaseDir) { throw 'La entrega ya existe; se conserva sin sobrescribir.' }
New-Item -ItemType Directory -Path $releaseDir | Out-Null
$archivePath = Join-Path $releaseDir 'source.zip'
git archive --format=zip --output=$archivePath HEAD app bootstrap config database/migrations resources routes public artisan composer.json composer.lock
if ($LASTEXITCODE -ne 0) { throw 'No se pudo exportar el código versionado.' }
$containerDir = "/var/www/app/storage/app/releases/$ReleaseName"
docker compose --env-file .env -f deployment/compose.yaml exec -T web php deployment/build-package.php $containerDir $commit
if ($LASTEXITCODE -ne 0) { throw 'Falló la construcción del paquete.' }
docker compose --env-file .env -f deployment/compose.yaml exec -T web php deployment/export-database.php $containerDir
if ($LASTEXITCODE -ne 0) { throw 'Falló la exportación de la base ficticia.' }
Write-Output "Entrega preparada: $releaseDir"
Write-Output 'application.zip: código público. initial-database.sql: privado, solo para una base vacía.'
