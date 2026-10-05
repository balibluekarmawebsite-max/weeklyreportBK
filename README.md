# BKDS Weekly Report Dashboard

A web dashboard for Blue Karma Dijiwa Seminyak's Sales & Marketing team to collect
each week's numbers, gather department updates, draft commentary with AI, and export
one polished weekly report as **Word, Excel, or PDF**.

- **Stack:** Laravel (PHP 8.3) + MySQL, Blade + Livewire + Alpine.js + Tailwind CSS, Chart.js.
- **Hosting:** self-hosted on a Bluehost VPS/Dedicated server.
- **Full plan & roadmap:** see [`docs/PLAN.md`](docs/PLAN.md).

> **Status:** Phase 1 (Foundation) — data model, roles, design system, and app shell.
> See the build phases in `docs/PLAN.md` section 8.

---

## Local development

Requirements: PHP 8.3+, Composer, Node.js 20+.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (local uses SQLite — zero setup)
touch database/database.sqlite
php artisan migrate --seed

# 4. Build assets & serve
npm run build          # or: npm run dev  (for hot reload)
php artisan serve
```

Then open http://localhost:8000.

> **Importing real reports locally?** The weekly-report files are ~8–10 MB, over
> PHP's default upload limit, so the import will reject them until you raise it.
> Find your php.ini with `php --ini`, then set (Homebrew Mac path shown):
> ```sh
> # edit /opt/homebrew/etc/php/8.3/php.ini
> upload_max_filesize = 32M
> post_max_size = 32M
> memory_limit = 512M
> ```
> Save, then restart `php artisan serve`. (Editing php.ini is required —
> passing `-d` flags to `artisan serve` does not affect its request server.)
> See the deploy section for the production setting.

**Seeded admin login** (change the password after first login):

- Email: `ota@bluekarmasecrets.com`
- Password: `password`

Run the tests:

```bash
php artisan test
```

---

## Production deploy (Bluehost VPS)

1. Clone the repo, then:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   cp .env.example .env     # then edit for production (see below)
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force   # first deploy only
   ```
2. In `.env`, switch the database block to **MySQL** and set `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL=https://reports.yourdomain.com`, plus `GROQ_API_KEY`
   and `VHP_IMPORT_SECRET`.
3. Point the web root (or subdomain docroot) at the `public/` folder.
4. Ensure `storage/` and `bootstrap/cache/` are writable by the web user.
5. Cron for the Laravel scheduler:
   ```
   * * * * * php /path/to/app/artisan schedule:run >> /dev/null 2>&1
   ```
6. Supervisor for background jobs (exports, AI, imports):
   ```
   php artisan queue:work --tries=3
   ```
7. Enable HTTPS (Let's Encrypt).
8. **Raise PHP upload limits** — the weekly-report workbooks are ~8–10 MB
   (they embed screenshots), which exceeds PHP's 2 MB/8 MB defaults. In
   `php.ini` (or a `conf.d/` override) set:
   ```ini
   upload_max_filesize = 32M
   post_max_size = 32M
   memory_limit = 512M
   max_execution_time = 120
   ```
   Then reload PHP-FPM. Without this, Data Import rejects the real files.

---

## Project layout (Phase 1)

- `app/Enums/` — `ReportStatus`, `RoleType`, `Department`.
- `app/Models/` — `Property`, `ReportWeek`, `Budget`, `Channel`, `RateCode`,
  `MarketSegment`, `Role`, `Setting`, `ActivityLog`, `User`.
- `app/Support/Format.php` — IDR / percentage / variance formatting (no error cells).
- `database/migrations/` — foundation schema.
- `database/seeders/` — roles, BKDS property, default lists, admin user, sample weeks.
- `resources/views/` — sidebar shell, dashboard, reports, settings, phase placeholders.
- `tools/vhp-robot/` — placeholder for the Playwright VHP import robot (Phase 8).
