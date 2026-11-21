# The Linux till: releases, updates and the pilot PC

The till ships as a Linux AppImage (and a .deb), built by GitHub Actions
(`.github/workflows/terminal-release.yml`). Tills update themselves from a
Cloudflare R2 bucket. The pilot PC runs Ubuntu 24.04 LTS as a kiosk: it logs in
by itself and opens VendaPOS full screen.

## 1. One-time setup: the update bucket (Cloudflare R2)

1. Cloudflare dashboard → **R2** → **Create bucket**, e.g. `vendapos-updates`.
2. The bucket → **Settings** → **Public access** → connect a custom domain such
   as `updates.<your-domain>` (the `r2.dev` address is rate-limited; use it only
   to try things out).
3. **R2** → **Manage API tokens** → **Create API token**: permission
   *Object Read & Write*, limited to this bucket. Note the access key ID, the
   secret and the S3 endpoint (`https://<account-id>.r2.cloudflarestorage.com`).
4. GitHub → the repository → **Settings** → **Secrets and variables** → **Actions**:

   | Kind | Name | Value |
   |---|---|---|
   | Secret | `R2_ACCESS_KEY_ID` | from step 3 |
   | Secret | `R2_SECRET_ACCESS_KEY` | from step 3 |
   | Variable | `R2_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
   | Variable | `R2_BUCKET` | `vendapos-updates` |
   | Variable | `VENDAPOS_UPDATES_URL` | `https://updates.<your-domain>/terminal/linux` |
   | Variable | `VENDAPOS_API_URL` | the platform's address, e.g. `https://api.<your-domain>` |

The R2 key exists only in GitHub. Tills download from the public address and
never hold a key; the build also strips every `AWS_*` and `GITHUB_*` setting from
the app it packages.

## 2. Making a release

- **Try a build:** GitHub → **Actions** → **Terminal release** → **Run workflow**.
  It builds without publishing; download the AppImage from the run's
  **Artifacts** (kept 30 days). Its version is `0.0.0-test.<run>`, so it never
  replaces a real release on a till.
- **Release:** tag the commit and push the tag:

  ```bash
  git tag terminal-v0.1.0
  git push origin terminal-v0.1.0
  ```

  The build uploads the AppImage and `latest-linux.yml` to
  `terminal/linux/` in the bucket. Versions only ever go up: a till installs a
  version newer than its own.

A till checks for an update each time it starts, downloads it in the
background and installs it the next time it starts. Restart tills after
closing time (or reboot them nightly, see 3.8) so updates are not left waiting.

## 3. Setting up a pilot till (Ubuntu 24.04 LTS)

You need: an x64 touch PC, a USB 80 mm ESC/POS receipt printer (the cash drawer
plugs into the printer), and internet for pairing.

### 3.1 Install Ubuntu

Install **Ubuntu 24.04 LTS Desktop** with two accounts:

- an **administrator** for you (with a password, used for maintenance);
- a standard account **`till`** for the counter.

Then Settings → **Users** → `till` → turn on **Automatic Login**.

### 3.2 Packages

```bash
sudo apt update
sudo apt install -y libfuse2t64 gnome-keyring
```

`libfuse2t64` lets AppImages run on 24.04. `gnome-keyring` keeps the till's
device token encrypted; the till refuses to store it without a keyring.

### 3.3 The keyring at automatic login

With automatic login nobody types a password, so the login keyring stays locked
and the till would wait on an "unlock keyring" prompt. Sign in as `till` once,
open **Passwords and Keys**, right-click **Login** → **Change Password**, enter
the current password and leave the new one empty.

This stores the keyring unencrypted on the disk. If a till is lost or stolen,
**revoke it at once** in the Owner panel → **Tills**: its token stops working
everywhere. (Full-disk encryption would protect it at rest, but someone would
have to type the disk passphrase every time the till starts.)

### 3.4 The printer

```bash
ls /dev/usb/lp*                    # the printer shows as /dev/usb/lp0
sudo usermod -aG lp till           # let the till account write to it
```

Sign `till` out and in again. In VendaPOS → **Printer**, choose
**Device file**, `/dev/usb/lp0`, 80 mm, and print a test page; the drawer opens
on the first cash sale.

### 3.5 Install VendaPOS

As `till`:

```bash
mkdir -p ~/Applications
mv ~/Downloads/VendaPOS-*.AppImage ~/Applications/VendaPOS.AppImage
chmod +x ~/Applications/VendaPOS.AppImage
```

Keep the name `VendaPOS.AppImage`: updates replace that file in place.

### 3.6 Start it at login

Create `~/.config/autostart/vendapos.desktop`:

```ini
[Desktop Entry]
Type=Application
Name=VendaPOS
Exec=/home/till/Applications/VendaPOS.AppImage --password-store=gnome-libsecret
X-GNOME-Autostart-enabled=true
```

`--password-store=gnome-libsecret` makes the app use the keyring from 3.3.
The till opens full screen with nothing to close.

### 3.7 Keep the screen on, add the screen keyboard

As `till`:

```bash
gsettings set org.gnome.desktop.session idle-delay 0
gsettings set org.gnome.desktop.screensaver lock-enabled false
gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-type 'nothing'
gsettings set org.gnome.desktop.notifications show-banners false
gsettings set org.gnome.desktop.a11y.applications screen-keyboard-enabled true
```

### 3.8 Restart after closing (optional)

So updates install overnight, as the administrator run `sudo crontab -e` and add
a reboot after the business closes, e.g. 04:30 for a bar open late:

```
30 4 * * * /sbin/shutdown -r now
```

### 3.9 Pair

Owner panel → **Tills** → **Pair a till**, then type the 8-character code on the
till. Staff sign in with their PINs.

### Maintenance

The kiosk window cannot be closed. To reach the desktop, press the **Super**
(Windows) key, or switch to the administrator account.
