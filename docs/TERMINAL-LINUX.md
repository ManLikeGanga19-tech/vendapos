# The Linux till: releases, updates and the pilot PC

The till ships as a Linux AppImage (and a .deb), built by GitHub Actions
(`.github/workflows/terminal-release.yml`). Tills update themselves from an
updates folder on our Contabo VPS. The pilot PC runs Ubuntu 24.04 LTS as a
locked-down kiosk that starts by itself.

## 1. One-time setup: the updates folder on the VPS

Tills download two kinds of file over HTTPS: `latest-linux.yml` (which version
is newest) and the AppImage it names. CI uploads them over SSH with a key that
can only write into that folder; tills only read, and hold no key.

On the VPS, as root:

```bash
adduser --system --group --home /srv/vendapos-updates --shell /bin/sh vendapos-updates
install -d -o vendapos-updates -g vendapos-updates -m 755 /srv/vendapos-updates/terminal/linux
install -d -o vendapos-updates -g vendapos-updates -m 700 /srv/vendapos-updates/.ssh
```

On your own computer, make the upload key (no passphrase; it lives only in
GitHub secrets):

```bash
ssh-keygen -t ed25519 -N '' -C 'vendapos updates upload' -f vendapos-updates
```

Back on the VPS, allow that key to do one thing only: write into the folder
(`rrsync` comes with rsync; on Ubuntu it is `/usr/bin/rrsync`):

```bash
echo 'command="/usr/bin/rrsync -wo /srv/vendapos-updates/terminal/linux",restrict <contents of vendapos-updates.pub>' \
  > /srv/vendapos-updates/.ssh/authorized_keys
chown vendapos-updates: /srv/vendapos-updates/.ssh/authorized_keys
chmod 600 /srv/vendapos-updates/.ssh/authorized_keys
```

Serve `/srv/vendapos-updates` read-only at `https://updates.<your-domain>/` from
the VPS's web server (static files, no PHP). Through Cloudflare, add a cache
rule so `latest-linux.yml` is never cached (the AppImages may be: each version
has its own file name).

GitHub → the repository → **Settings** → **Secrets and variables** → **Actions**:

| Kind | Name | Value |
|---|---|---|
| Secret | `UPDATES_SSH_KEY` | the private key file `vendapos-updates` |
| Secret | `UPDATES_SSH_KNOWN_HOSTS` | output of `ssh-keyscan -p <port> <vps-host>` |
| Variable | `UPDATES_SSH_HOST` | the VPS address |
| Variable | `UPDATES_SSH_USER` | `vendapos-updates` |
| Variable | `UPDATES_SSH_PORT` | only if SSH is not on 22 |
| Variable | `VENDAPOS_UPDATES_URL` | `https://updates.<your-domain>/terminal/linux` |
| Variable | `VENDAPOS_API_URL` | the platform's address, e.g. `https://api.<your-domain>` |

Then delete the key files from your computer.

## 2. Making a release

- **Try a build:** GitHub → **Actions** → **Terminal release** → **Run workflow**.
  It only builds; download the AppImage from the run's **Artifacts** (kept 30
  days). Its version is `0.0.0-test.<run>` and it never updates itself.
- **Release:** tag the commit and push the tag:

  ```bash
  git tag terminal-v0.1.0
  git push origin terminal-v0.1.0
  ```

  CI builds, uploads the AppImage, then `latest-linux.yml`. A till installs a
  version newer than its own.

A till checks for an update each time it starts, downloads it in the background
and installs it the next time it starts. Restart tills after closing time (3.9)
so updates do not wait.

## 3. Setting up a pilot till (Ubuntu 24.04 LTS)

You need: an x64 touch PC **with a TPM 2.0 chip** (most PCs from 2018 on; check
the firmware settings), a USB 80 mm ESC/POS receipt printer (the cash drawer
plugs into the printer), and internet for pairing.

### How the till's secrets stay safe with automatic login

The till keeps its device token in the GNOME keyring. With automatic login no
one types a password, so the keyring must open without one. That is only safe
because of what is around it:

1. **The whole disk is encrypted**, and the TPM unlocks it only in this PC with
   its boot settings unchanged (3.2). A disk taken out or copied is unreadable.
2. **The firmware is locked** (3.1): a firmware password, Secure Boot on, boot
   only from the internal disk. Without this, someone could boot a USB stick in
   the same PC and ask the TPM for the disk key.
3. **The counter account can do nothing else** (3.6): a kiosk session that runs
   only VendaPOS, with no desktop, app launcher or terminal, and no admin rights.
4. **A lost or stolen till is revoked** in the Owner panel → **Tills**: its token
   stops working everywhere at once. Staff still need their PINs to sell.

### 3.1 Firmware

In the PC's firmware (UEFI) settings: set an administrator password, turn
**Secure Boot** on, turn the **TPM** on, and allow booting only from the internal
disk (no USB or network boot). Keep the firmware password with the recovery
passphrase (3.2).

### 3.2 Install Ubuntu with full-disk encryption

Install **Ubuntu 24.04 LTS Desktop**. At **Disk setup** choose
**Advanced features** → **Use LVM and encryption**. The passphrase you set there
is the **recovery passphrase**: store it safely off the till; it is needed only
if the TPM refuses (firmware changed, Secure Boot turned off, disk moved).

Create two accounts: an **administrator** for you (with a password), and a
standard account **`till`** (not an administrator).

Then, as the administrator, let the TPM unlock the disk at boot:

```bash
sudo apt install -y clevis clevis-luks clevis-tpm2 clevis-initramfs
lsblk -f                                   # the partition marked crypto_LUKS, e.g. /dev/nvme0n1p3
sudo clevis luks bind -d /dev/nvme0n1p3 tpm2 '{"pcr_bank":"sha256","pcr_ids":"7"}'
sudo update-initramfs -u -k all
```

Reboot: the till should start without asking for the passphrase. PCR 7 ties
the key to the Secure Boot state, so it still unlocks after normal Ubuntu
updates. **Test this on the pilot PC before relying on it.**

### 3.3 Packages and the printer

```bash
sudo apt install -y libfuse2t64 gnome-keyring gnome-kiosk-script-session
ls /dev/usb/lp*                    # the printer shows as /dev/usb/lp0
sudo usermod -aG lp till           # let the till account write to it
```

`libfuse2t64` lets AppImages run on 24.04; `gnome-keyring` holds the device
token (the till refuses to store it without one).

### 3.4 Install VendaPOS

As `till`:

```bash
mkdir -p ~/Applications
mv ~/Downloads/VendaPOS-*.AppImage ~/Applications/VendaPOS.AppImage
chmod +x ~/Applications/VendaPOS.AppImage
```

Keep the name `VendaPOS.AppImage`: updates replace that file in place.

### 3.5 The keyring opens by itself

Still signed in as `till` in the normal desktop: open **Passwords and Keys**,
right-click **Login** → **Change Password**, enter the current password and
leave the new one empty. The keyring is now protected by the disk encryption
instead of a password.

### 3.6 The kiosk session

Create `~/.local/bin/gnome-kiosk-script` (make it executable with `chmod +x`):

```bash
#!/bin/sh
# VendaPOS is the only thing this session runs; if it ever stops, start it again.
while true; do
  "$HOME/Applications/VendaPOS.AppImage" --password-store=gnome-libsecret
  sleep 2
done
```

As the administrator, make `till` log in automatically into that session:

```bash
ls /usr/share/wayland-sessions/      # note the kiosk script session's name, e.g. gnome-kiosk-script-wayland
sudo tee /etc/gdm3/custom.conf >/dev/null <<'EOF'
[daemon]
AutomaticLoginEnable=true
AutomaticLogin=till
EOF
printf '[User]\nSession=gnome-kiosk-script-wayland\n' | sudo tee /var/lib/AccountsService/users/till
```

Reboot: the till goes straight into VendaPOS, full screen. The kiosk session
has no on-screen keyboard; keep a small USB keyboard at the counter for typing
notes and names (an on-screen keyboard inside VendaPOS can come later).

### 3.7 Pair

Owner panel → **Tills** → **Pair a till**, then type the 8-character code on the
till. Staff sign in with their PINs.

### 3.8 Printer setup

In VendaPOS → **Printer**, choose **Device file**, `/dev/usb/lp0`, 80 mm, and
print a test page; the drawer opens on the first cash sale.

### 3.9 Restart after closing (optional)

So updates install overnight, as the administrator run `sudo crontab -e` and add
a reboot after the business closes, e.g. 04:30 for a bar open late:

```
30 4 * * * /sbin/shutdown -r now
```

### Maintenance

With a USB keyboard, press **Ctrl+Alt+F3** for a text login and sign in as the
administrator (**Ctrl+Alt+F1** goes back to the till). For the desktop, set
`AutomaticLoginEnable=false` in `/etc/gdm3/custom.conf` and reboot; set it back
to `true` when done.
