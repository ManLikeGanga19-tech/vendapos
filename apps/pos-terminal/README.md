# VendaPOS Terminal

The till app: Laravel + Livewire packaged with NativePHP Desktop (Electron), with
its own SQLite database so it keeps selling offline. See `docs/ARCHITECTURE.md`
sections 3.1 and 12.

## Developing on Windows

The terminal runs natively on Windows. The platform it talks to (Octane,
Postgres, Redis, Horizon, Reverb) stays in Docker: Horizon needs the `pcntl` and
`posix` extensions, which Windows does not have.

Live updates (a menu or staff change, a revoked till) reach the till through
Reverb on port 8080: run `php artisan reverb:start --host=0.0.0.0 --port=8080`
in the platform container. Without it the till still catches up through its
60-second sync.

Needs PHP 8.4 (NTS x64 from php.net, with `curl fileinfo intl mbstring openssl
pdo_sqlite sodium sqlite3 zip` enabled), Composer and Node 22.

```powershell
cd apps\pos-terminal
composer install
npm install
npm run build
php artisan native:install -n   # first time: downloads Electron for this OS

# The till has no field for the server address; set it per environment.
# A process variable wins over .env, so nothing in .env needs changing.
$env:VENDAPOS_API_URL = 'http://localhost:8000'
php artisan native:migrate --force   # native:run does not apply new migrations by itself
php artisan native:run
```

`native:install` adds `native:install --force` to `post-update-cmd` in
`composer.json`. Don't commit that: `--force` would overwrite our
`NativeAppServiceProvider` and `config/nativephp.php` on every `composer update`.
Run `git checkout -- composer.json` after installing.

`node_modules` and Electron are built for one operating system. After switching
between Windows and Linux, delete `node_modules` and
`vendor/nativephp/desktop/resources/electron/node_modules`, then repeat the
install steps.

To pair the till, open the Owner panel (`http://danamo.localhost:8000`), go to
**Tills**, press **Pair a till** and type the code into the terminal window.

## Tests and checks

Run in the Docker app container, as CI does:

```bash
make terminal-test      # Pest
make terminal-analyse   # Larastan level 8
make terminal-lint      # Pint
```

## Linux pilot builds

The pilot till is a Linux touch PC. Production builds open in kiosk mode and
refuse to store the device token unless a secret service (gnome-keyring) is
running; launch the app with `--password-store=gnome-libsecret` so Electron
uses it.

Builds, releases, self-updates from Cloudflare R2 and the Ubuntu 24.04 kiosk
setup are in [docs/TERMINAL-LINUX.md](../../docs/TERMINAL-LINUX.md).
