#!/usr/bin/env bash
# Turns a fresh Ubuntu 24.04 LTS Desktop into a VendaPOS till: a standard
# account that logs in by itself into a kiosk session running only VendaPOS,
# a password-free keyring protected by TPM-unlocked full-disk encryption,
# printer access, and an optional nightly restart for updates.
#
# Run as the administrator, from the folder holding the AppImage:
#   sudo bash setup-ubuntu-kiosk.sh --appimage ./VendaPOS-0.1.0.AppImage [--reboot-at 04:30]
#
# The disk must have been encrypted at install time ("Use LVM and encryption");
# the script asks for that passphrase once, to let the TPM unlock the disk.
# Safe to run again. See docs/TERMINAL-LINUX.md.

set -euo pipefail

TILL_USER=till
APPIMAGE=""
REBOOT_AT=""
SKIP_TPM=false

usage() {
    sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
    echo
    echo "Options: --appimage FILE (required)  --user NAME (default till)  --reboot-at HH:MM  --skip-tpm"
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        --appimage) APPIMAGE="${2:-}"; shift 2 ;;
        --user) TILL_USER="${2:-}"; shift 2 ;;
        --reboot-at) REBOOT_AT="${2:-}"; shift 2 ;;
        --skip-tpm) SKIP_TPM=true; shift ;;
        -h|--help) usage ;;
        *) echo "Unknown option: $1"; usage ;;
    esac
done

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

# --- Checks -----------------------------------------------------------------

[ "$(id -u)" -eq 0 ] || fail "Run with sudo."
[ -n "$APPIMAGE" ] && [ -f "$APPIMAGE" ] || fail "Give the VendaPOS AppImage with --appimage."
. /etc/os-release
[ "${ID:-}" = ubuntu ] && [ "${VERSION_ID:-}" = "24.04" ] || fail "This script is for Ubuntu 24.04 LTS (found ${PRETTY_NAME:-unknown})."
if [ -n "$REBOOT_AT" ] && ! [[ "$REBOOT_AT" =~ ^([01][0-9]|2[0-3]):[0-5][0-9]$ ]]; then
    fail "--reboot-at must look like 04:30."
fi

LUKS_DEVICE=""
if [ "$SKIP_TPM" = false ]; then
    [ -e /dev/tpmrm0 ] || fail "No TPM 2.0 found. Turn the TPM on in the firmware settings (or use --skip-tpm, not for a real till)."
    mokutil --sb-state 2>/dev/null | grep -q "SecureBoot enabled" || fail "Secure Boot is off. Turn it on in the firmware settings."
    LUKS_DEVICE="$(lsblk -rpno NAME,FSTYPE | awk '$2 == "crypto_LUKS" { print $1; exit }')"
    [ -n "$LUKS_DEVICE" ] || fail "The disk is not encrypted. Reinstall Ubuntu with 'Use LVM and encryption'."
fi

# --- Packages ---------------------------------------------------------------

step "Installing packages"
apt-get update -q
packages=(libfuse2t64 gnome-keyring gnome-kiosk-script-session)
[ "$SKIP_TPM" = false ] && packages+=(clevis clevis-luks clevis-tpm2 clevis-initramfs)
DEBIAN_FRONTEND=noninteractive apt-get install -y -q "${packages[@]}"

# --- The till account -------------------------------------------------------

step "The '$TILL_USER' account (standard, no password, may use the printer)"
if ! id "$TILL_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "VendaPOS till" "$TILL_USER"
fi
if id -nG "$TILL_USER" | grep -qwE 'sudo|admin'; then
    fail "'$TILL_USER' is an administrator. Use a standard account for the till."
fi
usermod -aG lp "$TILL_USER"
TILL_HOME="$(getent passwd "$TILL_USER" | cut -d: -f6)"
as_till() { sudo -u "$TILL_USER" -H "$@"; }

# --- VendaPOS ---------------------------------------------------------------

step "Installing VendaPOS"
as_till mkdir -p "$TILL_HOME/Applications" "$TILL_HOME/.local/bin" "$TILL_HOME/.local/share/keyrings"
install -o "$TILL_USER" -g "$TILL_USER" -m 755 "$APPIMAGE" "$TILL_HOME/Applications/VendaPOS.AppImage"

# The kiosk session runs this script and nothing else; VendaPOS restarts if it ever stops.
cat > "$TILL_HOME/.local/bin/gnome-kiosk-script" <<'KIOSK'
#!/bin/sh
while true; do
    "$HOME/Applications/VendaPOS.AppImage" --password-store=gnome-libsecret
    sleep 2
done
KIOSK
chown "$TILL_USER:$TILL_USER" "$TILL_HOME/.local/bin/gnome-kiosk-script"
chmod 755 "$TILL_HOME/.local/bin/gnome-kiosk-script"

# --- Keyring without a password ----------------------------------------------

step "A login keyring that opens without a password (protected by the disk encryption)"
keyring="$TILL_HOME/.local/share/keyrings/login.keyring"
if [ ! -f "$keyring" ]; then
    # gnome-keyring keeps a keyring with no password in this plain format.
    cat > "$keyring" <<'KEYRING'
[keyring]
display-name=Login
ctime=0
mtime=0
lock-on-idle=false
lock-after=false
KEYRING
    echo login > "$TILL_HOME/.local/share/keyrings/default"
    chown -R "$TILL_USER:$TILL_USER" "$TILL_HOME/.local/share/keyrings"
    chmod 700 "$TILL_HOME/.local/share/keyrings"
    chmod 600 "$keyring" "$TILL_HOME/.local/share/keyrings/default"
else
    echo "A login keyring already exists: if it has a password, clear it in Passwords and Keys (docs/TERMINAL-LINUX.md 3.5)."
fi

# --- Automatic login into the kiosk session ---------------------------------

step "Automatic login into the VendaPOS kiosk session"
session=""
for file in /usr/share/wayland-sessions/*kiosk-script*.desktop; do
    [ -e "$file" ] && session="$(basename "$file" .desktop)" && break
done
[ -n "$session" ] || fail "The GNOME Kiosk script session is missing after install."

install -d -m 755 /var/lib/AccountsService/users
cat > "/var/lib/AccountsService/users/$TILL_USER" <<EOF
[User]
Session=$session
SystemAccount=false
EOF

gdm_conf=/etc/gdm3/custom.conf
[ -f "$gdm_conf.vendapos-backup" ] || cp "$gdm_conf" "$gdm_conf.vendapos-backup"
# Drop any earlier automatic-login lines, then set ours under [daemon].
sed -i -E '/^[#[:space:]]*AutomaticLogin(Enable)?[[:space:]]*=/d' "$gdm_conf"
grep -q '^\[daemon\]' "$gdm_conf" || printf '[daemon]\n' >> "$gdm_conf"
sed -i "/^\[daemon\]/a AutomaticLoginEnable=true\nAutomaticLogin=$TILL_USER" "$gdm_conf"

# --- The TPM unlocks the disk ------------------------------------------------

if [ "$SKIP_TPM" = false ]; then
    step "Letting the TPM unlock the encrypted disk ($LUKS_DEVICE)"
    if clevis luks list -d "$LUKS_DEVICE" 2>/dev/null | grep -q tpm2; then
        echo "Already bound to the TPM."
    else
        echo "Type the disk passphrase you chose when installing Ubuntu (it stays your recovery passphrase):"
        clevis luks bind -d "$LUKS_DEVICE" tpm2 '{"pcr_bank":"sha256","pcr_ids":"7"}'
    fi
    update-initramfs -u -k all
fi

# --- Nightly restart ---------------------------------------------------------

if [ -n "$REBOOT_AT" ]; then
    step "Restarting every night at $REBOOT_AT, so updates install"
    printf '%s %s * * * root /sbin/shutdown -r now\n' "${REBOOT_AT#*:}" "${REBOOT_AT%:*}" > /etc/cron.d/vendapos-reboot
    chmod 644 /etc/cron.d/vendapos-reboot
fi

cat <<DONE

Done. Next:
  1. Reboot. The disk should unlock by itself and VendaPOS should open full screen.
     If the disk asks for the passphrase, the TPM step did not take: tell your VendaPOS contact.
  2. Pair the till: Owner panel -> Tills -> Pair a till, type the code on the till.
  3. In VendaPOS -> Printer: Device file, /dev/usb/lp0, 80 mm, print a test page.
  4. Lock the firmware: set a firmware password and allow booting only from the internal disk.
  5. Keep the disk passphrase and the firmware password somewhere safe, off the till.
DONE
