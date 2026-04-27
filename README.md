# Apache VHost Manager (PHP 8+)

Secure, minimal PHP web application to manage Apache virtual hosts on Ubuntu with strict privilege separation.

## Features

- Session-based authentication for one admin user
- Password verification using `password_hash` / `password_verify`
- CSRF tokens on all state-changing forms
- Basic brute-force protection via login rate limiting
- Create and delete Apache virtual hosts safely
- Strict domain and path validation
- Action logging to filesystem
- SQLite-backed internal settings storage
- Privileged operations via a locked-down root helper script and sudoers policy

## Project Structure

- `public/index.php`: Entry point and router
- `src/`: MVC-like app logic
- `templates/`: PHP views
- `storage/logs/`: App logs
- `storage/data/`: JSON data files and SQLite settings DB
- `config/vhost.conf.tpl`: Default Apache vhost template
- `bin/vhost-admin-helper.sh`: Root-only helper script

## Ubuntu Setup (Step-by-Step)

1. Install packages:

```bash
sudo apt update
sudo apt install -y apache2 php php-cli libapache2-mod-php
```

2. Place project on server:

```bash
sudo mkdir -p /opt/vhost-manager
sudo chown -R $USER:$USER /opt/vhost-manager
# copy project files here
```

3. Create runtime directories and permissions:

```bash
mkdir -p /opt/vhost-manager/storage/logs /opt/vhost-manager/storage/data
chmod -R 0750 /opt/vhost-manager/storage
```

4. Configure settings in SQLite:

- App defaults are seeded automatically into `storage/data/settings.sqlite` on first run.
- On first load, you must complete the Setup Wizard (admin email/password, app URL, docroot defaults, proxy mode).
- Update values later from the Settings pages.

5. Deploy helper script and Apache template as root-owned files:

```bash
sudo mkdir -p /etc/vhost-manager
sudo cp /opt/vhost-manager/config/vhost.conf.tpl /etc/vhost-manager/vhost.conf.tpl
sudo cp /opt/vhost-manager/bin/vhost-admin-helper.sh /usr/local/sbin/vhost-admin-helper
sudo chown root:root /usr/local/sbin/vhost-admin-helper /etc/vhost-manager/vhost.conf.tpl
sudo chmod 0750 /usr/local/sbin/vhost-admin-helper
sudo chmod 0644 /etc/vhost-manager/vhost.conf.tpl
```

6. Configure sudoers safely (VERY IMPORTANT):

Use `visudo` and add:

```sudoers
# Allow only web server user to run the specific helper as root, no shell access.
www-data ALL=(root) NOPASSWD: /usr/local/sbin/vhost-admin-helper
Defaults!/usr/local/sbin/vhost-admin-helper !requiretty
```

7. Configure Apache site for this app (document root must be `public/`):

```apache
<VirtualHost *:80>
    ServerName vhost-manager.local
    DocumentRoot /opt/vhost-manager/public

    <Directory /opt/vhost-manager/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/vhost-manager_error.log
    CustomLog ${APACHE_LOG_DIR}/vhost-manager_access.log combined
</VirtualHost>
```

Enable and reload:

```bash
sudo a2enmod rewrite
sudo a2ensite vhost-manager.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

8. Ensure app files are not web-accessible:

- Web root should only point to `public/`.
- Keep `storage/` permissions restricted to trusted users/services.

## Security Notes

- App process runs as non-root (`www-data`) and cannot execute arbitrary commands.
- Privileged operations are constrained to one helper binary in sudoers.
- Domain names are strictly regex-validated before any command call.
- Paths are normalized and restricted to allowed base dirs (default: `/var/www`).
- Shell arguments are always quoted with `escapeshellarg`.
- `apache2ctl configtest` is executed before enabling new sites and before reload.
- CSRF token is required for login, create, and delete forms.
- Session hardening includes strict mode, regeneration on login, HttpOnly cookies, SameSite, and idle timeout.
- CSP and security headers are set in the front controller.
- All key actions and errors are logged to `storage/logs/app.log`.
- Mutable settings and changed admin password are stored in `storage/data/settings.sqlite`.

## Usage

- Login at `/` with admin credentials from SQLite settings.
- Create vhost via dashboard.
- Delete vhost from dashboard; optional checkbox removes the default `/var/www/{domain}` docroot.

## Docker Direction

- Mount `storage/` as a persistent volume so `storage/data/settings.sqlite` survives container recreation.
- Runtime settings changes in the UI are persisted to SQLite.
- The UI version label follows image build metadata (from Docker build), not compose env.
- `APP_URL` should be the external URL users access Vhost Manager on (used for URL/HTTPS behavior in the app).
- If you want extra document-root bases, add them in compose (`VHM_ALLOWED_DOCROOT_BASES`) and mount matching host paths.
- Vhost Manager detects newly added docroot bases on login and can prompt to change the default.

Copy environment template first:

```bash
cp .env.example .env
```

Internal NPM mode (default compose):

```yaml
services:
    vhost-manager:
        image: jadgames/vhost-manager:${VHM_IMAGE_TAG}
        environment:
            VHM_NPM_BOOTSTRAP_IDENTITY: "${VHM_SHARED_EMAIL}"
            VHM_NPM_BOOTSTRAP_SECRET: "${VHM_SHARED_PASSWORD}"
            VHM_ALLOWED_DOCROOT_BASES: "/var/www,/srv/sites"
        volumes:
            - /opt/vhost-manager/storage:/opt/vhost-manager/storage
            - /opt/vhost-manager/sites:/srv/sites
```

External NPM mode:

```bash
docker compose -f docker-compose.external-npm.yml up -d
```

### Standalone Docker Mode (Built-in Proxy)

- Vhost Manager UI binds to `8080` so it is always reachable directly.
- Built-in NPM binds to `80` and `443` for proxy traffic.
- Port `81` is commented out by default to reduce exposure; uncomment only when you explicitly need direct NPM admin UI access.
- During Setup Wizard:
    - Choose `Built-in NPM` for standalone installs.
    - Choose `External NPM` to configure an external NPM instance on the next setup step.

### External NPM Mode

- If your deployment does not include the built-in `npm` service, the Setup Wizard shows an `External Proxy Provider` dropdown.
- Currently supported provider: `Nginx Proxy Manager (NPM)`.
- The next setup step asks for:
    - NPM Base URL
    - NPM Admin Email
    - NPM Password
    - Forward Host and Forward Port
- `docker-compose.external-npm.yml` sets `VHM_BUILTIN_NPM_AVAILABLE=false` and omits the built-in `npm` service.

## Optional HTTPS Bonus (Let's Encrypt)

For production domains:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.example
```

For local testing, use self-signed cert and an Apache SSL vhost, then set `APP_HTTPS=true` in SQLite settings.
