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
sudo mkdir -p /opt/aphost
sudo chown -R $USER:$USER /opt/aphost
# copy project files here
```

3. Create runtime directories and permissions:

```bash
mkdir -p /opt/aphost/storage/logs /opt/aphost/storage/data
chmod -R 0750 /opt/aphost/storage
```

4. Configure settings in SQLite:

- App defaults are seeded automatically into `storage/data/settings.sqlite` on first run.
- On first load, you must complete the Setup Wizard (admin username/password, app URL, docroot defaults, proxy mode).
- Update values later from the Settings pages.

5. Deploy helper script and Apache template as root-owned files:

```bash
sudo mkdir -p /etc/aphost
sudo cp /opt/aphost/config/vhost.conf.tpl /etc/aphost/vhost.conf.tpl
sudo cp /opt/aphost/bin/vhost-admin-helper.sh /usr/local/sbin/vhost-admin-helper
sudo chown root:root /usr/local/sbin/vhost-admin-helper /etc/aphost/vhost.conf.tpl
sudo chmod 0750 /usr/local/sbin/vhost-admin-helper
sudo chmod 0644 /etc/aphost/vhost.conf.tpl
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
    ServerName aphost.local
    DocumentRoot /opt/aphost/public

    <Directory /opt/aphost/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/aphost_error.log
    CustomLog ${APACHE_LOG_DIR}/aphost_access.log combined
</VirtualHost>
```

Enable and reload:

```bash
sudo a2enmod rewrite
sudo a2ensite aphost.conf
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
- `APP_URL` should be the external URL users access APHost on (used for URL/HTTPS behavior in the app).
- If you want extra document-root bases, add them in compose (`APHOST_ALLOWED_DOCROOT_BASES`) and mount matching host paths.
- APHost detects newly added docroot bases on login and can prompt to change the default.

Example:

```yaml
services:
    aphost:
        environment:
            APHOST_ALLOWED_DOCROOT_BASES: "/var/www,/srv/sites"
            APHOST_DEFAULT_DOCROOT_BASE: "/var/www"
        volumes:
            - /opt/aphost/storage:/opt/aphost/storage
            - /opt/aphost/sites:/srv/sites
```

### Standalone Docker Mode (Built-in Proxy)

- APHost UI binds to `8080` so it is always reachable directly.
- Built-in NPM binds to `80` and `443` for proxy traffic (and `81` for NPM admin UI).
- During Setup Wizard:
    - Choose `Built-in NPM` for standalone installs.
    - Choose `External NPM` to configure an external NPM instance on the next setup step.

### External NPM Mode

- If your deployment does not include the built-in `npm` service, the Setup Wizard shows an `External Proxy Provider` dropdown.
- Currently supported provider: `Nginx Proxy Manager (NPM)`.
- The next setup step asks for:
    - NPM Base URL
    - NPM Username / Email
    - NPM Password
    - Forward Host and Forward Port
- The default Docker compose sets `APHOST_BUILTIN_NPM_AVAILABLE=true` for the APHost container.
- If you remove the built-in `npm` service from compose, also remove that env var or set it to `false` so the wizard switches to external-proxy setup.

## Optional HTTPS Bonus (Let's Encrypt)

For production domains:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.example
```

For local testing, use self-signed cert and an Apache SSL vhost, then set `APP_HTTPS=true` in SQLite settings.
