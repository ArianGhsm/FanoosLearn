#!/usr/bin/env bash
set -euo pipefail

SOURCE_ROOT="${1:?source root is required}"
RELEASE_ID="${2:?release id is required}"

case "$RELEASE_ID" in
  *[!a-zA-Z0-9_.-]*|'') echo "Invalid release id" >&2; exit 2 ;;
esac

INSTALL_ROOT=/opt/integrated-dent/notifier
RELEASE_ROOT="$INSTALL_ROOT/releases/$RELEASE_ID"
STATE_ROOT=/var/lib/integrated-dent/deploy-notifier
ENV_ROOT=/etc/integrated-dent

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Installer must run as root" >&2
  exit 2
fi

id -u dentops >/dev/null 2>&1 || useradd --system --home-dir /var/lib/integrated-dent --shell /usr/sbin/nologin dentops
install -d -m 0755 "$INSTALL_ROOT/releases" "$RELEASE_ROOT"
install -d -o root -g root -m 0755 /var/lib/integrated-dent
install -d -o dentops -g dentops -m 0700 "$STATE_ROOT"
install -d -o root -g root -m 0711 "$ENV_ROOT"

cp -a "$SOURCE_ROOT/deploy_notifier" "$RELEASE_ROOT/"
cp -a "$SOURCE_ROOT/dent_bot" "$RELEASE_ROOT/"
cp -a "$SOURCE_ROOT/config" "$RELEASE_ROOT/"
cp -a "$SOURCE_ROOT/docs" "$RELEASE_ROOT/"
python3 -m compileall -q "$RELEASE_ROOT/deploy_notifier"
python3 -m compileall -q "$RELEASE_ROOT/dent_bot"
ln -sfn "$RELEASE_ROOT" "$INSTALL_ROOT/current"

if [[ ! -e "$ENV_ROOT/deploy-notifier.env" ]]; then
  telegram_env="$ENV_ROOT/dent-bot.env"
  bale_env="$ENV_ROOT/bale-bot.env"
  [[ -s "$telegram_env" && -s "$bale_env" ]] || { echo "Active bot environments are required" >&2; exit 3; }
  read_value() { sed -n "s/^$1=//p" "$2" | tail -n 1 | sed 's/^"//;s/"$//'; }
  telegram_token="$(read_value DENT_BOT_TELEGRAM_TOKEN "$telegram_env")"
  telegram_owner="$(read_value DENT_BOT_OWNER_TELEGRAM_ID "$telegram_env")"
  site_url="$(read_value DENT_BOT_SITE_API_URL "$telegram_env")"
  site_secret="$(read_value DENT_BOT_SITE_SERVICE_SECRET "$telegram_env")"
  bale_token="$(read_value DENT_BALE_BOT_TOKEN "$bale_env")"
  bale_owner="$(read_value DENT_BALE_OWNER_ID "$bale_env")"
  [[ -n "$telegram_token" && "$telegram_owner" =~ ^[0-9]+$ ]] || { echo "Telegram notifier inputs are invalid" >&2; exit 3; }
  [[ -n "$bale_token" && "$bale_owner" =~ ^[0-9]+$ ]] || { echo "Bale notifier inputs are invalid" >&2; exit 3; }
  [[ "$site_url" == https://* && -n "$site_secret" ]] || { echo "Site notifier inputs are invalid" >&2; exit 3; }
  umask 077
  {
    printf 'DENT_DEPLOY_NOTIFY_CHANNELS=telegram,bale,site\n'
    printf 'DENT_DEPLOY_TELEGRAM_BOT_TOKEN=%s\n' "$telegram_token"
    printf 'DENT_DEPLOY_TELEGRAM_CHAT_ID=%s\n' "$telegram_owner"
    printf 'DENT_DEPLOY_TELEGRAM_PROXY_URL=http://127.0.0.1:11080\n'
    printf 'DENT_DEPLOY_BALE_BOT_TOKEN=%s\n' "$bale_token"
    printf 'DENT_DEPLOY_BALE_CHAT_ID=%s\n' "$bale_owner"
    printf 'DENT_DEPLOY_SITE_API_URL=%s\n' "$site_url"
    printf 'DENT_DEPLOY_SITE_SERVICE_SECRET=%s\n' "$site_secret"
    printf 'DENT_DEPLOY_SITE_PLATFORM=telegram\n'
    printf 'DENT_DEPLOY_SITE_OWNER_ID=%s\n' "$telegram_owner"
    printf 'DENT_DEPLOY_NOTIFIER_STATE_DIR=/var/lib/integrated-dent/deploy-notifier\n'
    printf 'DENT_DEPLOY_NOTIFY_TIMEOUT_SECONDS=10\n'
  } > "$ENV_ROOT/deploy-notifier.env"
  chmod 0600 "$ENV_ROOT/deploy-notifier.env"
fi

install -m 0644 "$SOURCE_ROOT/ops/systemd/integrated-deploy-notifier-flush.service" /etc/systemd/system/
install -m 0644 "$SOURCE_ROOT/ops/systemd/integrated-deploy-notifier-flush.timer" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now integrated-deploy-notifier-flush.timer

cd "$INSTALL_ROOT/current"
set -a
# shellcheck disable=SC1091
source "$ENV_ROOT/deploy-notifier.env"
set +a
runuser -u dentops -- python3 -m deploy_notifier.cli health
echo "Deploy notifier installed without changing bot runtimes."
