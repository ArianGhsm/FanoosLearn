[CmdletBinding()]
param(
    [string]$SiteRepository = "..",
    [string]$ServerConfig = ".codex-local\iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$siteRoot = [IO.Path]::GetFullPath((Join-Path $root $SiteRepository))
$ftpConfig = Get-Content -Raw (Join-Path $siteRoot ".vscode\sftp.json") | ConvertFrom-Json
$server = Get-Content -Raw (Join-Path $root $ServerConfig) | ConvertFrom-Json
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $root ".codex-local\server-backups\grade-sync-$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

function Protect-Bytes([byte[]]$value, [string]$path) {
    Add-Type -AssemblyName System.Security
    $protected = [Security.Cryptography.ProtectedData]::Protect(
        $value,
        $null,
        [Security.Cryptography.DataProtectionScope]::CurrentUser
    )
    [IO.File]::WriteAllBytes($path, $protected)
}

function Unprotect-Bytes([string]$path) {
    Add-Type -AssemblyName System.Security
    return [Security.Cryptography.ProtectedData]::Unprotect(
        [IO.File]::ReadAllBytes($path),
        $null,
        [Security.Cryptography.DataProtectionScope]::CurrentUser
    )
}

function Invoke-SshScript([string]$scriptText) {
    $startInfo = [Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = "ssh"
    $startInfo.Arguments = "-i `"$identityFile`" -p $($server.port) -o BatchMode=yes -o StrictHostKeyChecking=yes $sshUser@$($server.host) `"bash -s`""
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $process = [Diagnostics.Process]::Start($startInfo)
    $process.StandardInput.Write(($scriptText -replace "`r", ""))
    $process.StandardInput.Close()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        throw "Remote SSH configuration failed."
    }
}

function New-FtpRequest([string]$method) {
    $remotePath = ([string]$ftpConfig.remotePath).Trim("/")
    $uri = "ftp://$($ftpConfig.host)/$remotePath/.env"
    $request = [Net.FtpWebRequest]::Create($uri)
    $request.Method = $method
    $request.Credentials = [Net.NetworkCredential]::new([string]$ftpConfig.username, [string]$ftpConfig.password)
    $request.UseBinary = $true
    $request.UsePassive = $ftpConfig.passive -ne $false
    $request.KeepAlive = $false
    return $request
}

$download = New-FtpRequest ([Net.WebRequestMethods+Ftp]::DownloadFile)
$response = $download.GetResponse()
try {
    $memory = [IO.MemoryStream]::new()
    $response.GetResponseStream().CopyTo($memory)
    $siteEnvBytes = $memory.ToArray()
} finally {
    $response.Close()
}
Protect-Bytes $siteEnvBytes (Join-Path $backupRoot "site-env-before.dpapi")

$vpsEnv = & ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes `
    "$sshUser@$($server.host)" "cat /etc/integrated-dent/dent-bot.env"
if ($LASTEXITCODE -ne 0) { throw "Could not back up the VPS bot environment." }
Protect-Bytes ([Text.Encoding]::UTF8.GetBytes(($vpsEnv -join "`n") + "`n")) (Join-Path $backupRoot "vps-dent-bot-env-before.dpapi")

$secretPath = Join-Path $root ".codex-local\grade-sync-service-secret.dpapi"
if (Test-Path -LiteralPath $secretPath) {
    $secret = [Text.Encoding]::UTF8.GetString((Unprotect-Bytes $secretPath))
} else {
    $random = [byte[]]::new(32)
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($random)
    } finally {
        $generator.Dispose()
    }
    $secret = -join ($random | ForEach-Object { $_.ToString("x2") })
    Protect-Bytes ([Text.Encoding]::UTF8.GetBytes($secret)) $secretPath
}
if ($secret -notmatch '^[a-f0-9]{64}$') { throw "Stored service secret is invalid." }

$siteEnv = [Text.Encoding]::UTF8.GetString($siteEnvBytes)
$line = "DENT_BOT_SERVICE_SECRET=$secret"
if ($siteEnv -match '(?m)^DENT_BOT_SERVICE_SECRET=.*$') {
    $siteEnv = [regex]::Replace($siteEnv, '(?m)^DENT_BOT_SERVICE_SECRET=.*$', $line)
} else {
    $siteEnv = $siteEnv.TrimEnd() + "`n" + $line + "`n"
}
$updatedBytes = [Text.Encoding]::UTF8.GetBytes($siteEnv)
$upload = New-FtpRequest ([Net.WebRequestMethods+Ftp]::UploadFile)
$upload.ContentLength = $updatedBytes.Length
$stream = $upload.GetRequestStream()
try {
    $stream.Write($updatedBytes, 0, $updatedBytes.Length)
} finally {
    $stream.Close()
}
$uploadResponse = $upload.GetResponse()
$uploadResponse.Close()

$remoteScript = @"
set -euo pipefail
env_file=/etc/integrated-dent/dent-bot.env
if grep -q '^DENT_BOT_SITE_SERVICE_SECRET=' "`$env_file"; then
  sed -i 's/^DENT_BOT_SITE_SERVICE_SECRET=.*/DENT_BOT_SITE_SERVICE_SECRET=$secret/' "`$env_file"
else
  printf '\nDENT_BOT_SITE_SERVICE_SECRET=$secret\n' >> "`$env_file"
fi
if grep -q '^DENT_BOT_SITE_API_URL=' "`$env_file"; then
  sed -i 's|^DENT_BOT_SITE_API_URL=.*|DENT_BOT_SITE_API_URL=https://dentistry1402tums.ir/api/bot_api.php?action=service|' "`$env_file"
else
  printf 'DENT_BOT_SITE_API_URL=https://dentistry1402tums.ir/api/bot_api.php?action=service\n' >> "`$env_file"
fi
chmod 600 "`$env_file"
"@
Invoke-SshScript $remoteScript

Write-Output "GRADE_SYNC_SECRET_CONFIGURED=true"
Write-Output "ENCRYPTED_BACKUP=$backupRoot"
