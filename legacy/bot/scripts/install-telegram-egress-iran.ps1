[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string[]]$EncryptedSubscriptionUrls = @()
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-telegram-egress-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$xrayVersion = "v26.3.27"
$xraySha256 = "23cd9af937744d97776ee35ecad4972cf4b2109d1e0fe6be9930467608f7c8ae"
$downloadRoot = Join-Path $root ".codex-local\xray-download"
$archive = Join-Path $downloadRoot "Xray-linux-64.zip"
$expanded = Join-Path $downloadRoot "expanded"
$xrayBinary = Join-Path $expanded "xray"
$remotePrefix = "/tmp/integrated-dent-telegram-egress-$([Guid]::NewGuid().ToString('N'))"
$releaseId = "telegram-egress-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$lifecycleBaseId = "integrated-ops-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")

try {
    Publish-DentDeployLifecycle -Service integrated-ops -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram egress subscription and selector deployment started." -ServerConfig $ServerConfig
    $lifecycleStarted = $true
    & (Join-Path $PSScriptRoot "backup-vps-state.ps1") -ServerConfig $ServerConfig -Quiet
    if ($LASTEXITCODE -ne 0) { throw "Pre-deployment VPS backup failed." }
    New-Item -ItemType Directory -Force -Path $temporaryRoot, $downloadRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts -Force
    if (-not (Test-Path -LiteralPath $archive -PathType Leaf)) {
        Invoke-WebRequest -UseBasicParsing `
            -Uri "https://github.com/XTLS/Xray-core/releases/download/$xrayVersion/Xray-linux-64.zip" `
            -OutFile $archive
    }
    $actualHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash.ToLowerInvariant()
    if ($actualHash -ne $xraySha256) { throw "Xray release checksum mismatch." }
    if (-not (Test-Path -LiteralPath $xrayBinary -PathType Leaf)) {
        Expand-Archive -LiteralPath $archive -DestinationPath $expanded -Force
    }

    Add-Type -AssemblyName System.Security
    if ($EncryptedSubscriptionUrls.Count -eq 0) {
        $EncryptedSubscriptionUrls = @(
            Get-ChildItem -LiteralPath (Join-Path $root ".codex-local") -File |
                Where-Object { $_.Name -match '^telegram-egress-subscription-url(?:-[0-9]+)?\.dpapi$' } |
                Sort-Object Name |
                ForEach-Object FullName
        )
    }
    if ($EncryptedSubscriptionUrls.Count -eq 0) {
        throw "At least one DPAPI-encrypted Telegram subscription is required."
    }
    $environmentLines = [Collections.Generic.List[string]]::new()
    $secretBuffers = [Collections.Generic.List[byte[]]]::new()
    $subscriptionIndex = 0
    foreach ($encryptedSubscription in $EncryptedSubscriptionUrls) {
        $encryptedPath = if ([IO.Path]::IsPathRooted($encryptedSubscription)) {
            $encryptedSubscription
        } else {
            Join-Path $root $encryptedSubscription
        }
        if (-not (Test-Path -LiteralPath $encryptedPath -PathType Leaf)) {
            throw "A configured encrypted Telegram subscription is missing."
        }
        $encryptedBytes = [IO.File]::ReadAllBytes($encryptedPath)
        $urlBytes = [Security.Cryptography.ProtectedData]::Unprotect(
            $encryptedBytes, $null, [Security.Cryptography.DataProtectionScope]::CurrentUser
        )
        $secretBuffers.Add($urlBytes)
        $subscriptionIndex++
        $suffix = if ($subscriptionIndex -eq 1) { "" } else { "_$subscriptionIndex" }
        $environmentLines.Add(
            "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64$suffix=$([Convert]::ToBase64String($urlBytes))"
        )
        [Array]::Clear($encryptedBytes, 0, $encryptedBytes.Length)
    }
    $envPath = Join-Path $temporaryRoot "telegram-egress.env"
    [IO.File]::WriteAllText(
        $envPath,
        (($environmentLines -join "`n") + "`n"),
        [Text.UTF8Encoding]::new($false)
    )
    foreach ($buffer in $secretBuffers) { [Array]::Clear($buffer, 0, $buffer.Length) }
    $environmentLines.Clear()

    $selectorPath = Join-Path $root "scripts\select-xray-telegram-egress.py"
    $installerPath = Join-Path $temporaryRoot "install.sh"
    $installer = @'
#!/usr/bin/env bash
set -euo pipefail
umask 077
prefix='__REMOTE_PREFIX__'
trap 'rm -f -- "${prefix}.xray" "${prefix}.selector.py" "${prefix}.env" "${prefix}.install.sh"' EXIT
install -d -m 0755 /usr/local/lib/integrated-dent/xray
install -d -o root -g root -m 0711 /etc/integrated-dent
if ! id dentegress >/dev/null 2>&1; then
  useradd --system --home /nonexistent --shell /usr/sbin/nologin dentegress
fi
install -o root -g root -m 0755 "${prefix}.xray" /usr/local/lib/integrated-dent/xray/xray
install -o root -g root -m 0755 "${prefix}.selector.py" /usr/local/lib/integrated-dent/xray/select-telegram-egress.py
install -o root -g root -m 0600 "${prefix}.env" /etc/integrated-dent/telegram-egress.env

cat >/usr/local/lib/integrated-dent/xray/refresh-telegram-egress <<'SH'
#!/usr/bin/env bash
set -euo pipefail
exec 9>/run/lock/integrated-dent-telegram-egress-refresh.lock
if ! flock -n 9; then
  echo '{"success":true,"skipped":"already-running"}'
  exit 0
fi
current_hash=''
previous_config="$(mktemp /run/integrated-dent-egress-previous.XXXXXX)"
trap 'rm -f -- "$previous_config"' EXIT
if test -s /etc/integrated-dent/telegram-egress.json; then
  current_hash="$(sha256sum /etc/integrated-dent/telegram-egress.json | cut -d' ' -f1)"
  cp -a /etc/integrated-dent/telegram-egress.json "$previous_config"
fi
was_active=0
if systemctl is-active --quiet integrated-dent-telegram-egress.service; then
  was_active=1
fi
/usr/local/lib/integrated-dent/xray/select-telegram-egress.py \
  --env-file /etc/integrated-dent/telegram-egress.env \
  --xray /usr/local/lib/integrated-dent/xray/xray \
  --output /etc/integrated-dent/telegram-egress.json \
  --port 11080 --probe-port 11081
chown root:dentegress /etc/integrated-dent/telegram-egress.json
chmod 0640 /etc/integrated-dent/telegram-egress.json
selected_hash="$(sha256sum /etc/integrated-dent/telegram-egress.json | cut -d' ' -f1)"
if test "$selected_hash" != "$current_hash"; then
  if test "$was_active" = 1; then
    systemctl restart integrated-dent-telegram-egress.service
    live_ok=0
    listener_ready=0
    for wait_attempt in $(seq 1 50); do
      if ss -H -lnt 'sport = :11080' | grep -q '127.0.0.1:11080'; then
        listener_ready=1
        break
      fi
      sleep 0.2
    done
    if test "$listener_ready" = 1; then
      for live_attempt in 1 2; do
        if curl --silent --show-error --proxy http://127.0.0.1:11080 --max-time 12 \
          https://api.telegram.org/bot0:invalid/getMe | grep -q '"error_code":401'; then
          live_ok=$((live_ok + 1))
        fi
      done
    fi
    if test "$live_ok" != 2; then
      if test -s "$previous_config"; then
        install -o root -g dentegress -m 0640 "$previous_config" /etc/integrated-dent/telegram-egress.json
        systemctl restart integrated-dent-telegram-egress.service
      fi
      echo '{"success":false,"reason":"live-post-activation-probe"}' >&2
      exit 1
    fi
  fi
fi
SH
chmod 0755 /usr/local/lib/integrated-dent/xray/refresh-telegram-egress

cat >/etc/systemd/system/integrated-dent-telegram-egress.service <<'UNIT'
[Unit]
Description=IntegratedDent Telegram-only Xray egress
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=dentegress
Group=dentegress
ExecStart=/usr/local/lib/integrated-dent/xray/xray run -c /etc/integrated-dent/telegram-egress.json
Restart=on-failure
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
PrivateDevices=true
ProtectSystem=strict
ProtectHome=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true
MemoryDenyWriteExecute=true
RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6

[Install]
WantedBy=multi-user.target
UNIT

cat >/etc/systemd/system/integrated-dent-telegram-egress-refresh.service <<'UNIT'
[Unit]
Description=Refresh IntegratedDent Telegram egress node
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/lib/integrated-dent/xray/refresh-telegram-egress
TimeoutStartSec=20m
Nice=10
IOSchedulingClass=idle
UNIT

cat >/etc/systemd/system/integrated-dent-telegram-egress-refresh.timer <<'UNIT'
[Unit]
Description=Ten-minute refresh of IntegratedDent Telegram egress node

[Timer]
OnCalendar=*-*-* *:0/10:00 Asia/Tehran
RandomizedDelaySec=30s
AccuracySec=15s
Persistent=true

[Install]
WantedBy=timers.target
UNIT

systemctl daemon-reload
/usr/local/lib/integrated-dent/xray/refresh-telegram-egress
systemctl enable --now integrated-dent-telegram-egress.service integrated-dent-telegram-egress-refresh.timer
systemctl is-active --quiet integrated-dent-telegram-egress.service
ss -H -lnt 'sport = :11080' | grep -q '127.0.0.1:11080'
echo TELEGRAM_EGRESS_INSTALL_OK
/usr/local/lib/integrated-dent/xray/xray version | sed -n '1p'
'@
    $installer = $installer.Replace('__REMOTE_PREFIX__', $remotePrefix)
    [IO.File]::WriteAllText($installerPath, $installer, [Text.UTF8Encoding]::new($false))

    $sshOptions = @(
        "-i", $identityFile, "-p", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    $scpOptions = @(
        "-q", "-i", $identityFile, "-P", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    & scp @scpOptions $xrayBinary "${target}:${remotePrefix}.xray"
    if ($LASTEXITCODE -ne 0) { throw "Xray transfer failed." }
    & scp @scpOptions $selectorPath "${target}:${remotePrefix}.selector.py"
    if ($LASTEXITCODE -ne 0) { throw "Selector transfer failed." }
    & scp @scpOptions $envPath "${target}:${remotePrefix}.env"
    if ($LASTEXITCODE -ne 0) { throw "Egress environment transfer failed." }
    & scp @scpOptions $installerPath "${target}:${remotePrefix}.install.sh"
    if ($LASTEXITCODE -ne 0) { throw "Installer transfer failed." }
    & ssh @sshOptions $target "sudo bash '${remotePrefix}.install.sh'"
    if ($LASTEXITCODE -ne 0) { throw "Telegram egress installation failed." }
    & (Join-Path $PSScriptRoot "backup-vps-state.ps1") -ServerConfig $ServerConfig -Quiet
    if ($LASTEXITCODE -ne 0) { throw "Post-deployment VPS backup failed." }
    Publish-DentDeployLifecycle -Service integrated-ops -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Two-source Telegram egress passed selection, timer, listener and backup verification."
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        try {
            Publish-DentDeployLifecycle -Service integrated-ops -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram egress deployment failed; the previous working configuration remains available." -ServerConfig $ServerConfig
        } catch { }
        $lifecycleStarted = $false
    }
    throw
}
finally {
    if ($sshOptions) {
        & ssh @sshOptions $target "sudo rm -f -- '${remotePrefix}.xray' '${remotePrefix}.selector.py' '${remotePrefix}.env' '${remotePrefix}.install.sh'" 2>$null
    }
    if ($secretBuffers) {
        foreach ($buffer in $secretBuffers) { [Array]::Clear($buffer, 0, $buffer.Length) }
    }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
