# BKDS Weekly Report Dashboard — Full Plan

A web dashboard for Blue Karma Dijiwa Seminyak's Sales & Marketing team. It collects each week's numbers (imported from VHP or uploaded), gathers each department's written updates, uses AI to draft the commentary, and exports one polished weekly report as Word, Excel or PDF in the same layout as the current spreadsheet.

> **Stack decided (Oct 2026):** Self-hosted on a **Bluehost VPS/Dedicated** server. Built with **Laravel (PHP 8.3) + MySQL**, UI in **Blade + Livewire + Alpine.js + Tailwind CSS**, charts with **Chart.js**. Exports with **PhpSpreadsheet** (Excel), **PHPWord** (Word), **mPDF** (PDF). AI via **Groq** called server-side. Playwright VHP robot stays a separate Node script run on the hotel network. _(This replaces the earlier "Lovable Cloud" idea.)_

---

## 1. What the sample report contains (structure we will reproduce)

| # | Section | Source of data |
|---|---|---|
| Cover | Weekly Report – property name, period (e.g. 25 Sep – 1 Oct 2026) | Auto |
| A | Sales & Marketing Overview: 1 Financial summary, 2 Market overview (pace, countries, booking window, Booking.com ranking), 3 Building hotel image, 4 Promotion analysis, 5 ROAS, 6 Learning & growth / trainings, executed activities | AI draft from numbers + manual |
| B | Year to Date Actual & On-Hand Forecast: per month RN sold, Occupancy, ARR, Revenue – Actual vs Budget vs Last Year | VHP + budget table (yearly upload) |
| C | Weekly Production by Market Segment (last 7 days bookings), cancellations, complimentary stays, gross revenue & ADR | VHP |
| D | Rate Code / Promotion: RN, ADR, % share, revenue per rate code | VHP |
| E/F | Channel Inside (RN): per source (Booking.com, Expedia, Agoda, Traveloka, WEB-ALARIC…) by month, YTD and % – current year and prior years | VHP |
| G | Sales Activity: date, type (sales call / telemarketing), company, PIC, market, update notes | Manual – Sales |
| G2 | E-commerce Activities: date, task, remarks | Manual – E-commerce |
| H | Social Media Insight: followers, reach, impressions, profile/website visits vs last week + growth %, strengths/notes, design & content task list | Manual – Social media / Graphic designer |
| – | Marketing activities (digital marketing, content, "marketing outsider") daily logs | Manual – Marketing |
| I | Training (date, topic, duration, trainer, participants) | Manual |
| J | Next Week Action Plan | Manual |
| Owner Overview | Repeater guest performance (12 months), channel mix, acquisition cost/ROAS, in-house outlet usage | VHP + manual + AI |

Problems in today's file the app removes: `#REF!` / `#DIV/0!` errors, copied hidden sheets (SM.5 (1)…(9), SM.7 (1)…(17)), stale headings ("Forecast 2023", "January 2024" text).

## 2. Main workflow (each week)

```text
Monday             Tue–Wed                      Thursday              Friday
Import VHP data -> Departments fill their  ->   AI drafts summaries -> Review, approve,
(auto or upload)   sections (status tracked)    (Groq), user edits     export Word/Excel/PDF
```

Each weekly report has a status: Draft -> In progress -> Ready for review -> Approved (locked) -> Exported. A past week can be opened, compared and re-exported anytime.

## 3. Screens (UI plan)

Layout: left sidebar navigation, top bar with property switcher (BKDS now; BKDU, BKV etc. later) and week picker. Calm, editorial hospitality look – warm off-white background, deep teal/ink text, one accent color, serif headings for the report feel, clean sans-serif for numbers; numbers right-aligned in tabular figures, IDR formatted as `1,984,774`.

1. **Dashboard (home)** – KPI cards for the selected week: Occupancy, ADR, Room revenue vs Budget and Last Year (green/red variance), weekly pick-up, cancellations, ROAS. Charts: month-by-month Occupancy/ADR/Revenue (Actual vs Budget vs LY), channel mix donut, pick-up by month bars. "Report progress" panel showing each section's status and who still has to submit.
2. **Weekly Reports list** – table of weeks with status, owner, last edited, export buttons.
3. **Report Editor** (the core screen) – left: section outline (A–J + Owner Overview) with completion ticks; center: the section form/table; right: live preview of that section as it will appear in the export. Tables are editable grids (Livewire); text sections have a rich text editor plus "Generate with AI" / "Rewrite" buttons.
4. **Data Import** – drag-and-drop for VHP exports (Excel/CSV) with a column-mapping step the first time, a preview with validation warnings (missing months, totals that don't match), then "Apply to week". Shows import history and the automatic-sync status.
5. **Department Inputs** – each department (Sales, E-commerce, Social Media, Graphic Design, Digital Marketing, Training) sees only its own forms: add activity rows, paste notes, attach screenshots (e.g. Booking.com ranking, Meta insights).
6. **Export Center** – choose format(s), sections to include, language; preview pages; download history.
7. **Settings** – budgets per month (yearly upload), rate codes and channel lists, market segments, users & roles, AI settings, VHP connection settings, report branding (logo, colors, footer).

Mobile: dashboard and department input forms are fully usable on phone; the editor is desktop-first.

## 4. Data import & VHP automation

- **Phase 1 (reliable start): file upload.** VHP reports (Statistic / Segment / Rate code / Source / Repeater) exported to Excel and dropped in. Mapping templates are saved, so next week is one drop.
- **Phase 2: automatic grab with Playwright.** Playwright runs as a small "robot" Node script on an always-on PC/server in the hotel network (or a scheduled cloud runner if VHP is reachable from the internet). Each week it logs into VHP, downloads the needed reports, and POSTs them to the dashboard's secure import endpoint, protected by an HMAC secret. The dashboard processes them exactly like a manual upload, and logs success or failure.
- VHP login details stay only on the robot machine, never in the dashboard.

## 5. AI with Groq

- Groq API key stored in the Laravel `.env` (`GROQ_API_KEY`); all calls made server-side from a Laravel service using the OpenAI-compatible endpoint via Laravel's HTTP client.
- Uses: draft "1. Financial" paragraph from Section B numbers (e.g. "As of 1 Oct 2026, occupancy is 15.41% below budget…"), pick-up summary, promotion analysis, ROAS headline/highlights, social media strengths, channel-mix commentary, rewrite/shorten/translate (EN/ID) of department notes, and "check for anomalies" (big variances, missing sections).
- AI never invents numbers: it receives the calculated figures and must quote them; every AI text is editable and marked "AI draft" until a person approves it.

## 6. Exports (Word, Excel, PDF)

One shared report template definition drives all three formats, so they match:
- Cover page with logo, property name, report period; table of contents; section headings A–J with numbering identical to today; header with property + week, footer with page numbers and "Confidential".
- Tables: brand-colored header rows, zebra rows, right-aligned numbers, bold totals, red/green variance, consistent IDR and % formatting, no error cells (shows "–" when no data).
- Charts (occupancy, ADR, revenue, channel mix) rendered to PNG server-side and embedded as images in Word/PDF.
- **Excel** (PhpSpreadsheet): one tab per section named like today (Cover, SM, SM.1…, Owner Overview), with live formulas for totals/percentages, frozen headers, print area set to A4.
- **Word** (PHPWord): editable .docx, A4, proper heading styles (so TOC works).
- **PDF** (mPDF): print-ready A4, consistent page breaks (no table split mid-row).
- Optional separate "Owner Overview" short export.

## 7. Users & roles

Admin (DoS/Revenue manager): everything, approve & lock. Editor (marketing lead): edit all sections. Department contributor: only their section(s). Viewer (GM/Owner): read and download. Activity log of who changed what.

Roles via a `roles`/`user_roles` table and Laravel policies/gates (or the `spatie/laravel-permission` package).

## 8. Build phases

1. **Foundation:** login, roles, property & week setup, settings (budgets, channel/rate-code lists), design system. _(done)_
2. Data model + manual data entry for Sections B, C, D, E/F, Owner Overview, with automatic calculations (occupancy, ADR, variances, %). _(done)_
3. File import with mapping + validation for VHP exports. _(done for the SM-format weekly-report workbook: upload → parse → preview/clean → apply, with import history; a VHP-native parser + column mapping plugs into the same pipeline once a raw VHP sample is provided. Note: report files are ~8–10 MB, so PHP upload limits must be raised — see README.)_
4. Department input forms (G, G2, H, I, J, activity logs) + progress tracking.
5. Dashboard KPIs and charts.
6. Exports: Excel, Word, PDF using the shared template; compare against the sample file.
7. Groq AI drafting and rewriting.
8. Playwright VHP robot + secure import endpoint + scheduling.
9. History, week-to-week comparison, multi-property rollout.

## 9. Technical details (Bluehost VPS + Laravel)

- **Server:** Bluehost VPS/Dedicated (root/SSH). PHP 8.3, MySQL/MariaDB, Nginx or Apache, Composer. Laravel scheduler via a single cron entry; queue worker via `supervisor` for background export/AI jobs.
- **Framework:** Laravel 11. UI with Blade + Livewire 3 + Alpine.js + Tailwind CSS. Charts with Chart.js. Local file storage on the server disk (`storage/app`) for uploads/screenshots/exports, served through Laravel.
- **Auth & roles:** Laravel Breeze (or Fortify) for login; roles via policies/gates (optionally `spatie/laravel-permission`).
- **Main tables:** `properties`, `report_weeks` (status, period), `budgets` (property, year, month, occupancy/arr/revenue), `monthly_stats` (actual/LY per month), `weekly_segment_production`, `rate_code_production`, `channel_room_nights` (source, year, month, RN), `repeater_guests`, `social_media_metrics`, `activities` (type, department, date, company, PIC, notes), `trainings`, `action_plans`, `ai_drafts`, `imports` (file, mapping, log), `users`, `roles`/`role_user`, `settings` (branding, lists), `activity_log`.
- **Calculations:** Laravel services (occupancy, ADR, variances, %); never store derived errors — compute on read or on save.
- **AI:** Laravel service calling Groq's OpenAI-compatible endpoint with `GROQ_API_KEY` from `.env`.
- **Exports:** `phpoffice/phpspreadsheet` (Excel), `phpoffice/phpword` (Word), `mpdf/mpdf` (PDF). Charts pre-rendered to PNG.
- **Import endpoint for the robot:** a public API route (`/api/vhp-import`) verifying an HMAC signature (`VHP_IMPORT_SECRET`) before accepting files.
- **Playwright robot:** separate Node script in `tools/vhp-robot`, run by Windows Task Scheduler/cron on the hotel machine.
- **Excel parsing on upload:** PhpSpreadsheet server-side, validated before saving (Laravel form requests / value objects).

## 10. Deployment on Bluehost VPS

- Clone repo to the server, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate`, set up `.env` (DB, `GROQ_API_KEY`, `VHP_IMPORT_SECRET`, `APP_KEY`).
- Point the web root at `public/`. Set file permissions on `storage/` and `bootstrap/cache/`.
- Cron: `* * * * * php /path/artisan schedule:run` for scheduled tasks.
- Supervisor: `php artisan queue:work` for background jobs (exports, AI, imports).
- HTTPS via Let's Encrypt.

---

## Open points to confirm before building

These are the decisions still needed. None block Phase 1, but they shape later phases:

1. **The sample spreadsheet.** To reproduce the exact export layout (tab names, headings, column order, formulas) I need the current Excel file. Can you upload it to the repo (e.g. `docs/samples/`) or share it? Phases 2 and 6 depend on it.
2. **VHP export format.** Which exact reports does VHP produce (Statistic / Segment / Rate code / Source / Repeater), and in what file format (xlsx, xls, csv)? One real sample of each lets me build the import mapping (Phase 3).
3. **Is VHP reachable from the internet, or only inside the hotel network?** This decides whether the Playwright robot runs on a hotel PC or a cloud runner (Phase 8).
4. **Properties.** Start with BKDS only, or set up BKDU / BKV from day one? (Data model already supports multi-property.)
5. **Users & emails.** List of initial users, their departments, and roles (Admin / Editor / Contributor / Viewer).
6. **Branding assets.** Logo file, exact brand colors (hex), and the footer text for exports.
7. **Report language.** English only, or English + Indonesian (affects AI prompts and export labels).
8. **Groq model.** Which Groq model to default to (e.g. `llama-3.3-70b-versatile`), and do you already have a Groq API key?
9. **Domain / subdomain** on Bluehost for the dashboard (e.g. `reports.yourdomain.com`).
10. **Fiscal/report calendar.** Does the "week" run Fri–Thu as in the sample, and does the fiscal year start in January?

---

_Build approach: phase by phase. Phase 1 (Foundation) is being scaffolded now._
