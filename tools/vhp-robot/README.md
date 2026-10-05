# VHP robot

A small Node script that fetches the weekly report from VHP and uploads it to
the dashboard's secure import endpoint. It runs **outside** the dashboard — on
any machine or cloud runner with network access to both VHP and the dashboard —
so the VHP password never touches the dashboard server.

```
VHP  ──login & export──►  robot  ──HMAC-signed upload──►  dashboard /api/vhp-import
```

## How authentication works

Every upload is signed with a shared secret (`VHP_IMPORT_SECRET`) that must be
identical here and in the dashboard's `.env`. The signature is:

```
HMAC_SHA256( "{unix_timestamp}\n{PROPERTY_CODE}\n{sha256(file bytes)}", secret )
```

sent in the `X-Vhp-Signature` header with `X-Vhp-Timestamp`. The dashboard
recomputes it and rejects anything that doesn't match, or whose timestamp is
older than the allowed window (default 5 minutes). No login/session is used.

## Setup

Requires **Node 20.6+** (for `--env-file`).

```bash
cd tools/vhp-robot
npm install
npx playwright install chromium   # only needed for the full VHP scrape
cp .env.example .env               # then edit .env
```

Set at least these in `.env`:

| Variable | What |
|---|---|
| `DASHBOARD_IMPORT_URL` | `https://<your-dashboard>/api/vhp-import` |
| `VHP_IMPORT_SECRET` | must match the dashboard's `.env` exactly |
| `PROPERTY_CODE` | `BKDS`, `BKDU` or `BKV` |

## Test the upload now (no VHP needed)

If you already have a weekly-report `.xlsx` exported, upload it directly to
prove the endpoint + signature work end to end:

```bash
npm run test-upload -- /path/to/BKDS_Weekly_Report_SM_25_Sep_-_1_Oct_2026.xlsx
```

A success prints `✓ Import applied` with the per-section counts. The data then
shows up on the dashboard for that week, and the import is logged (source: VHP)
in **Data Import → history**.

## The full VHP scrape (needs your VHP pages)

`downloadFromVhp()` in `index.mjs` has the login + export flow scaffolded but
the VHP-specific selectors/navigation are marked `TODO` — they can't be guessed
without seeing VHP's real pages. To finish it, share (no credentials):

1. The VHP login URL and the field names on the login form.
2. The menu path to the weekly report and the name of the Excel export button.
3. One real exported file, so the parser is confirmed against VHP's format.

Fill in `VHP_URL`, `VHP_USERNAME`, `VHP_PASSWORD` and the selectors in `.env`,
then run:

```bash
npm run upload
```

## Schedule it (weekly)

Run it once a week after VHP's numbers are final. On Linux/macOS with cron —
every Monday 06:30:

```cron
30 6 * * 1 cd /opt/vhp-robot && /usr/bin/node --env-file=.env index.mjs >> robot.log 2>&1
```

On Windows use Task Scheduler with the same command. On a cloud runner (since
VHP is reachable from the web) a scheduled GitHub Action or any cron host works
too — store the secret and VHP login as that runner's secrets, never in git.

## Exit codes

`0` success · `1` upload rejected (see the printed JSON) · `2` missing config.
