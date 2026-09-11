function Publish-DentDeployLifecycle {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)][ValidateSet("website", "archive-worker", "telegram-bot", "bale-bot", "integrated-ops")][string]$Service,
        [Parameter(Mandatory)][ValidateSet("started", "succeeded", "failed", "rolled_back")][string]$Status,
        [Parameter(Mandatory)][string]$ReleaseId,
        [Parameter(Mandatory)][string]$EventBaseId,
        [string]$Summary = "",
        [string]$Actor = "deployment-script",
        [string]$ServerConfig = ".codex-local/iran-server.json"
    )

    $emitter = Join-Path $PSScriptRoot "emit-deploy-status.ps1"
    & $emitter `
        -Service $Service `
        -Status $Status `
        -EventId "$EventBaseId-$Status" `
        -Version $ReleaseId `
        -Summary $Summary `
        -Actor $Actor `
        -ServerConfig $ServerConfig
    if ($LASTEXITCODE -ne 0) {
        throw "Deployment lifecycle event '$Service/$Status' could not be queued."
    }
}
