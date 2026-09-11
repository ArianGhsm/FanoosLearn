#!/usr/bin/env bash
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Provisioning must run as root" >&2
  exit 2
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
  ca-certificates curl git rsync jq unzip sqlite3 qpdf \
  python3 python3-venv python3-pip \
  poppler-utils imagemagick fontconfig fonts-noto-core fonts-noto-extra \
  ufw fail2ban unattended-upgrades

if ! swapon --show=NAME --noheadings | grep -qx /swapfile; then
  if [[ ! -e /swapfile ]]; then
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=progress
    chmod 0600 /swapfile
    mkswap /swapfile >/dev/null
  fi
  swapon /swapfile
fi
grep -qE '^/swapfile\s' /etc/fstab || printf '%s\n' '/swapfile none swap sw 0 0' >> /etc/fstab

cat >/etc/sysctl.d/60-integrated-dent.conf <<'EOF'
vm.swappiness=10
fs.protected_hardlinks=1
fs.protected_symlinks=1
kernel.kptr_restrict=2
EOF
sysctl --system >/dev/null

rm -f /etc/ssh/sshd_config.d/60-integrated-dent.conf
cat >/etc/ssh/sshd_config.d/00-integrated-dent.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 4
EOF
sshd -t
systemctl reload ssh

ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw --force enable

systemctl enable --now fail2ban
systemctl enable --now unattended-upgrades

echo "PROVISION_OK"
