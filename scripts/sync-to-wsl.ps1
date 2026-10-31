$ErrorActionPreference = "Stop"

$source = "/mnt/d/projects/erp/"
$destination = "/home/felipe/servidor/erp/"

if (-not (Test-Path "D:\projects\erp\composer.json")) {
    throw "Projeto não encontrado em D:\projects\erp"
}

wsl -d Ubuntu -- rsync -a --delete `
    --exclude ".env" `
    --exclude ".env.backup" `
    --exclude ".env.production" `
    --exclude "vendor/" `
    --exclude "node_modules/" `
    --exclude "storage/" `
    --exclude "bootstrap/cache/" `
    --exclude "public/hot" `
    --exclude "public/storage" `
    --exclude ".git/" `
    --exclude "*.log" `
    --exclude "database/*.sqlite" `
    --exclude "docker/_*.py" `
    --exclude "docker/_*.php" `
    $source $destination

if ($LASTEXITCODE -ne 0) {
    throw "A sincronização para o WSL falhou."
}

Write-Output "Código sincronizado para /home/felipe/servidor/erp"
