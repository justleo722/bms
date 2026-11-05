# Production deployment guide

This checklist takes the BMS from a local XAMPP install to a production server.
It covers the required environment, the build/migration steps, and the
security settings that must be enabled before going live.

## 1. Server requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`,
  `xml`, `ctype`, `json`, `bcmath`, `zip` and `gd` (PhpSpreadsheet).
- MySQL 8.0+ / MariaDB 10.6+.
- Composer 2.x.
- Node.js 20+ (only needed to build assets).
- Web server document root must point at `public/`, **not** the project root.

Example Apache virtual host:

```apache
<VirtualHost *:443>
    ServerName bms.example.com
    DocumentRoot /var/www/bms/public

    <Directory /var/www/bms/public>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/bms.example.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/bms.example.com/privkey.pem
</VirtualHost>
```

## 2. Install the application

```bash
# From the project root
composer install --no-dev --optimize-autoloader

cp .env.production.example .env
php artisan key:generate --force
```

Edit `.env` and set at minimum:

| Variable | Production value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://bms.example.com` |
| `APP_FORCE_HTTPS` | `true` (when TLS is enabled) |
| `DB_*` | real database credentials |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` (requires HTTPS) |
| `MAIL_*` | real SMTP credentials |
| `BMS_REGISTRATION_NOTIFICATION_EMAIL` | owner address |
| `LOG_LEVEL` | `warning` or `error` |

> `SESSION_SECURE_COOKIE=true` only works over HTTPS. If you must run plain
> HTTP on a private network, set it to `false` **and** keep the app off the
> public internet.

## 3. Build assets and prepare storage

```bash
npm ci
npm run build

php artisan storage:link
```

Ensure these are writable by the web server user (`www-data` on Debian/Ubuntu):

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

## 4. Database

```bash
php artisan migrate --force
```

Do **not** run `migrate:fresh`, `migrate:refresh`, or `db:wipe` in production —
the application now blocks those commands when `APP_ENV=production`.

Create the first administrator from the public `/register` screen using the
registration code emailed to the owner address, or by running the site's normal
onboarding flow. Do not use `create_demo.php`; it deletes all users and now
refuses to run outside the `local` environment.

## 5. Cache configuration for performance

```bash
php artisan optimize
```

This caches config, routes, views and events. Re-run `php artisan optimize`
after every deployment that changes `.env`, routes, or views.

To roll back the caches:

```bash
php artisan optimize:clear
```

## 6. Background workers and scheduler

If any feature dispatches queued jobs, keep a worker running under a supervisor:

```ini
[program:bms-worker]
command=php /var/www/bms/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

Add the scheduler to cron:

```cron
* * * * * cd /var/www/bms && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Security checklist

- [ ] `APP_DEBUG=false` (a debug page can leak credentials and source code).
- [ ] `APP_KEY` generated and backed up securely.
- [ ] HTTPS enabled; `APP_FORCE_HTTPS=true`; `SESSION_SECURE_COOKIE=true`.
- [ ] `public/` is the only web-accessible directory.
- [ ] `.env`, `storage/`, `vendor/`, `database/` are not web-accessible.
- [ ] Directory listing disabled (the shipped `public/.htaccess` does this).
- [ ] Database user has only the privileges the app needs.
- [ ] Nightly database + `storage/app` backups are configured and restored once.
- [ ] Log rotation/monitoring is configured (`storage/logs`).
- [ ] The `/up` health endpoint is monitored by your uptime tool.
- [ ] Registration is completed and the shared registration code is kept safe.

The app sends these security headers automatically:
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`, `X-Permitted-Cross-Domain-Policies`, and (over HTTPS in
production) `Strict-Transport-Security`.

## 8. Sessions and the former "419 Page Expired" logout error

The logout POST is CSRF-protected. Previously, if the session had already
expired (or the browser restored a cached page), the stale token produced a
bare `419 Page Expired` screen. The application now:

1. Clears the leftover session and redirects to `/login` with a
   "Your session expired" notice, and
2. Returns a JSON `419` for AJAX requests so clients can react.

Keep `SESSION_DRIVER=database` and make sure the `sessions` table exists
(it is created by the default migrations). If you switch to Redis or files,
verify sessions survive a deploy. For shared POS/tablet terminals that can sit
idle for a long shift, raise `SESSION_LIFETIME` (for example to `480`); stale
tokens are still handled gracefully by the redirect above.

## 9. Deployment smoke test

After deploying:

1. Open `https://bms.example.com/up` → should return `200 OK`.
2. Sign in with a valid staff code.
3. Open each module from the sidebar and refresh once.
4. Sign out → should return to the login screen (no 419 page).
5. Let a test session expire or clear cookies, then press Sign out on the old
   page → should land on the login screen with the expiry notice.
6. Submit a form (e.g. add an expense) to confirm CSRF still works.
7. Check `storage/logs/laravel.log` for unexpected errors.
