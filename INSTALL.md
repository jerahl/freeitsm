# Installing FreeITSM on a Server

This guide covers deploying FreeITSM to a production server. Two paths are
described:

- **[Option A — Docker](#option-a--docker-recommended)** (recommended): one
  command brings up the app + MySQL.
- **[Option B — Manual LAMP](#option-b--manual-lamp-ubuntudebian)**: install
  onto an existing Apache/PHP/MySQL server.

It also covers the **[Zabbix dark dashboard integration](#zabbix-integration)**,
**[scheduled jobs](#scheduled-jobs-cron)**, **[HTTPS](#put-it-behind-https)**,
and **[production hardening](#production-hardening)**.

> The application ships with a **Zabbix 7.4 dark theme applied across the whole
> UI** — no action is needed to enable it, it loads on every page.

---

## Requirements

| Component   | Version / Notes                                              |
|-------------|-------------------------------------------------------------|
| PHP         | 7.4–8.4, with `pdo_mysql`, `curl`, `openssl`, `mbstring`    |
| MySQL       | 8.0+                                                         |
| Web server  | Apache (with `mod_rewrite`) or Nginx + PHP-FPM              |
| Zabbix      | *(optional)* 6.0+ for the dashboard integration; 7.4 tested |

A small VM (2 vCPU / 2 GB RAM / 20 GB disk) is enough for most teams.

---

## Option A — Docker (recommended)

Prerequisites: Docker Engine + the Compose plugin.

```bash
# 1. Clone
git clone https://github.com/jerahl/freeitsm.git
cd freeitsm

# 2. (Optional) set strong DB passwords and Zabbix details — see below
#    Compose reads these from the shell environment or a .env file.

# 3. Build and start
docker compose up -d --build
```

The stack defined in `docker-compose.yml`:

- **app** — PHP 8.4 + Apache, published on host port **8080**. An encryption
  key is auto-generated on first boot by `docker/entrypoint.sh`.
- **db** — MySQL 8.0; the schema in `database/freeitsm.sql` is loaded
  automatically on the first run. Data persists in the `db-data` volume.

Then open **`http://<server>:8080/setup/`** to verify the install, and
**`http://<server>:8080/login.php`** to sign in.

### Set passwords and Zabbix via a `.env` file

Create a `.env` next to `docker-compose.yml` (Compose loads it automatically):

```ini
# Database (override the insecure defaults!)
DB_PASSWORD=change-me-strong
MYSQL_PASSWORD=change-me-strong
MYSQL_ROOT_PASSWORD=change-me-too

# Zabbix integration (optional — leave ZABBIX_API_URL empty to disable)
ZABBIX_API_URL=https://zabbix.example.com
ZABBIX_API_TOKEN=your-zabbix-api-token
ZABBIX_REFRESH_SECONDS=30
ZABBIX_MIN_SEVERITY=0
```

> The default compose file uses `freeitsm/freeitsm` for the DB credentials and
> binds MySQL to host port `3307`. **Change these before exposing the server.**

Apply changes with `docker compose up -d` (recreates the app container so new
env vars take effect).

### Persisting data

These named volumes hold state — back them up:

| Volume            | Contents                          |
|-------------------|-----------------------------------|
| `db-data`         | MySQL database                    |
| `attachments`     | Ticket attachments                |
| `cm-attachments`  | Change-management attachments     |
| `encryption-keys` | The encryption key (do **not** lose this — encrypted settings become unrecoverable) |

---

## Option B — Manual LAMP (Ubuntu/Debian)

### 1. Install packages

```bash
sudo apt update
sudo apt install -y apache2 mysql-server \
  php php-mysql php-curl php-mbstring php-xml libapache2-mod-php
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Deploy the code

```bash
sudo git clone https://github.com/jerahl/freeitsm.git /var/www/freeitsm
sudo chown -R www-data:www-data /var/www/freeitsm
```

Point an Apache vhost at `/var/www/freeitsm` with `AllowOverride All`, e.g.
`/etc/apache2/sites-available/freeitsm.conf`:

```apache
<VirtualHost *:80>
    ServerName itsm.example.com
    DocumentRoot /var/www/freeitsm
    <Directory /var/www/freeitsm>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog  ${APACHE_LOG_DIR}/freeitsm-error.log
    CustomLog ${APACHE_LOG_DIR}/freeitsm-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite freeitsm && sudo systemctl reload apache2
```

### 3. Create the database

```bash
sudo mysql <<'SQL'
CREATE DATABASE freeitsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'freeitsm'@'localhost' IDENTIFIED BY 'change-me-strong';
GRANT ALL PRIVILEGES ON freeitsm.* TO 'freeitsm'@'localhost';
FLUSH PRIVILEGES;
SQL

sudo mysql freeitsm < /var/www/freeitsm/database/freeitsm.sql
```

### 4. Database credentials (outside the web root)

`config.php` loads credentials from a file **outside** the document root. On
Linux, create one and update the path in `config.php`.

```bash
sudo tee /etc/freeitsm/db_config.php >/dev/null <<'PHP'
<?php
define('DB_SERVER', 'localhost');
define('DB_NAME', 'freeitsm');
define('DB_USERNAME', 'freeitsm');
define('DB_PASSWORD', 'change-me-strong');
PHP
sudo chown root:www-data /etc/freeitsm/db_config.php
sudo chmod 640 /etc/freeitsm/db_config.php
```

Edit `config.php` and set the path (it ships with a Windows default):

```php
// config.php
$db_config_path = '/etc/freeitsm/db_config.php';
```

While you're in `config.php`, set your timezone and turn off error display for
production:

```php
date_default_timezone_set('America/New_York');
error_reporting(0);
ini_set('display_errors', 0);
```

### 5. Encryption key (for sensitive settings)

```bash
sudo mkdir -p /var/www/encryption_keys
php -r "echo bin2hex(random_bytes(32));" | sudo tee /var/www/encryption_keys/freeitsm.key >/dev/null
sudo chown -R www-data:www-data /var/www/encryption_keys
sudo chmod 700 /var/www/encryption_keys
sudo chmod 600 /var/www/encryption_keys/freeitsm.key
```

Set its path via the `ENCRYPTION_KEY_PATH` environment variable (e.g. in the
vhost: `SetEnv ENCRYPTION_KEY_PATH /var/www/encryption_keys/freeitsm.key`).

### 6. Writable upload directories

```bash
sudo mkdir -p /var/www/freeitsm/tickets/attachments \
              /var/www/freeitsm/change-management/attachments
sudo chown -R www-data:www-data /var/www/freeitsm/tickets/attachments \
              /var/www/freeitsm/change-management/attachments
```

### 7. Verify and log in

- Visit **`http://itsm.example.com/setup/`** — it checks config files, DB
  connectivity, PHP extensions, and security settings.
- Log in at **`/login.php`** with `admin` / `freeitsm` and **change the
  password immediately**.

---

## Zabbix integration

The **Service Status → Zabbix** page renders live problems, per-severity
counts, and host up/down totals pulled from the Zabbix JSON-RPC API. It
requires **Zabbix 6.0+** (7.4 tested) and an **API token**.

1. In Zabbix: **Users → API tokens → Create API token**, assigned to a user
   with read access to the hosts/problems you want surfaced. Copy the token.
2. Configure FreeITSM — **preferred: in the UI under System → Zabbix**. Enter
   the frontend URL and API token, set the refresh interval / minimum severity,
   then use **Test connection** to verify. The token is encrypted at rest in
   the database. This works the same for Docker and manual installs.
3. *(Optional fallback)* You can instead seed the connection before first login
   via environment/constants — handy for automated deploys. These are only used
   until you save in System → Zabbix:
   - **Docker:** set `ZABBIX_API_URL` and `ZABBIX_API_TOKEN` (see the
     [`.env` example](#set-passwords-and-zabbix-via-a-env-file)).
   - **Manual:** edit the `ZABBIX_*` constants near the top of `config.php`.
4. Open **Service Status → Zabbix**. If nothing is configured (UI or fallback),
   the page shows a friendly "not configured" message instead of erroring.

> The FreeITSM server must be able to reach the Zabbix frontend over HTTPS. If
> Zabbix uses a self-signed certificate, note that certificate verification is
> governed by the `SSL_VERIFY_PEER` constant in `config.php`.

---

## Scheduled jobs (cron)

Some features run from background workers. On Linux, add them to the
`www-data` crontab (`sudo crontab -u www-data -e`):

```cron
# SLA breach/warning notifications — every 5 minutes
*/5 * * * * php /var/www/freeitsm/cron/sla_breach_check.php >/dev/null 2>&1
```

If you use the Microsoft Intune asset sync, also schedule the Intune workers
(`intune_worker.php`, `intune_app_worker.php`) at a cadence that suits you.
See [`docs/sla-cron-setup.md`](docs/sla-cron-setup.md) for details and the
Windows Task Scheduler equivalent.

---

## Put it behind HTTPS

Use a reverse proxy or Apache TLS. With the Docker setup, the simplest route is
to terminate TLS at a proxy (Caddy, Nginx, or Apache) on the host and forward
to `127.0.0.1:8080`. For a direct Apache install, Let's Encrypt via certbot:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d itsm.example.com
```

---

## Production hardening

- [ ] **Delete the `/setup` folder** once verified (`rm -rf setup/`).
- [ ] **Change the default `admin` password** (and the DB passwords from the
      compose/`.env` defaults).
- [ ] Set `error_reporting(0)` / `display_errors = 0` (already off in the
      Docker config).
- [ ] Serve over **HTTPS** and set `SSL_VERIFY_PEER` to `true` once your CA
      bundle is configured.
- [ ] Keep `db_config.php` and the encryption key **outside the web root** with
      restrictive permissions (done above).
- [ ] **Back up** the database and the four Docker volumes (or the equivalent
      directories) regularly — especially the encryption key.
- [ ] Restrict the MySQL port (`3307` in the default compose) to localhost or a
      private network; don't expose it publicly.

---

## Upgrading

```bash
cd freeitsm           # or /var/www/freeitsm
git pull
# Docker:
docker compose up -d --build
# Manual: re-apply any new SQL migrations, then reload Apache
```

Check **`/setup/`** after upgrading to confirm the database schema is current.
