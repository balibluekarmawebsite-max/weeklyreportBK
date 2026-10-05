#!/usr/bin/env node
// VHP robot — downloads the weekly report from VHP and uploads it to the
// dashboard's secure /api/vhp-import endpoint, signed with the shared secret.
//
// Two modes:
//   node index.mjs --file <path.xlsx>   Skip VHP; just sign + upload a file you
//                                        already have (use this to test the
//                                        endpoint end-to-end right now).
//   node index.mjs                       Full run: log in to VHP, download the
//                                        report, then upload it.
//
// Config comes from environment variables (see .env.example). Never hard-code
// the VHP password or the import secret. See README.md.

import { createHmac, createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import process from 'node:process';

function env(name, fallback = undefined) {
  const v = process.env[name];
  return v === undefined || v === '' ? fallback : v;
}

function requireEnv(name) {
  const v = env(name);
  if (!v) {
    console.error(`Missing required env var: ${name}`);
    process.exit(2);
  }
  return v;
}

/** HMAC-SHA256 over "{timestamp}\n{property}\n{sha256(file)}" — matches the server. */
function sign({ timestamp, property, fileBuffer, secret }) {
  const sha = createHash('sha256').update(fileBuffer).digest('hex');
  const canonical = `${timestamp}\n${property}\n${sha}`;
  return createHmac('sha256', secret).update(canonical).digest('hex');
}

async function uploadFile({ url, secret, property, startDate, filePath }) {
  const fileBuffer = await readFile(filePath);
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = sign({ timestamp, property, fileBuffer, secret });

  const form = new FormData();
  form.append('property', property);
  if (startDate) form.append('start_date', startDate);
  form.append('report', new Blob([fileBuffer]), basename(filePath));

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'X-Vhp-Timestamp': timestamp,
      'X-Vhp-Signature': signature,
      Accept: 'application/json',
    },
    body: form,
  });

  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    json = { raw: text };
  }
  return { status: res.status, json };
}

/**
 * Log in to VHP and download the weekly-report workbook.
 *
 * ⚠️ TODO (needs VHP's real pages): fill in the selectors and the navigation to
 * the report export. The structure below is ready — only the marked steps need
 * VHP-specific values. Until then, use `--file <path>` to test the upload.
 *
 * @returns {Promise<string>} path to the downloaded .xlsx
 */
async function downloadFromVhp() {
  const { chromium } = await import('playwright');

  const vhpUrl = requireEnv('VHP_URL');
  const username = requireEnv('VHP_USERNAME');
  const password = requireEnv('VHP_PASSWORD');

  const browser = await chromium.launch({ headless: env('HEADLESS', 'true') !== 'false' });
  try {
    const page = await browser.newPage();
    await page.goto(vhpUrl, { waitUntil: 'domcontentloaded' });

    // --- STEP 1: log in -------------------------------------------------
    // TODO: confirm these selectors against VHP's real login form.
    await page.fill(env('VHP_USER_SELECTOR', 'input[name="username"]'), username);
    await page.fill(env('VHP_PASS_SELECTOR', 'input[name="password"]'), password);
    await page.click(env('VHP_SUBMIT_SELECTOR', 'button[type="submit"]'));
    await page.waitForLoadState('networkidle');

    // --- STEP 2: open the weekly report and export ----------------------
    // TODO: navigate to the report page and trigger the Excel export. Example:
    //   await page.goto(`${vhpUrl}/reports/weekly`);
    //   const [download] = await Promise.all([
    //     page.waitForEvent('download'),
    //     page.click('text=Export to Excel'),
    //   ]);
    //   const filePath = `/tmp/vhp_${Date.now()}.xlsx`;
    //   await download.saveAs(filePath);
    //   return filePath;

    throw new Error(
      'VHP navigation is not configured yet. Fill in the selectors/steps in downloadFromVhp(), ' +
        'or run with `--file <path>` to upload a file you exported manually.',
    );
  } finally {
    await browser.close();
  }
}

function parseArgs(argv) {
  const args = { file: null, startDate: env('REPORT_START_DATE') ?? null };
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--file') args.file = argv[++i];
    else if (argv[i] === '--start') args.startDate = argv[++i];
  }
  return args;
}

async function main() {
  const args = parseArgs(process.argv);

  const config = {
    url: requireEnv('DASHBOARD_IMPORT_URL'), // e.g. https://reports.example.com/api/vhp-import
    secret: requireEnv('VHP_IMPORT_SECRET'), // must equal the dashboard's .env value
    property: requireEnv('PROPERTY_CODE'), // e.g. BKDS
    startDate: args.startDate,
  };

  const filePath = args.file ?? (await downloadFromVhp());
  console.log(`Uploading ${filePath} for ${config.property}…`);

  const { status, json } = await uploadFile({ ...config, filePath });

  if (status >= 200 && status < 300) {
    console.log('✓ Import applied:', JSON.stringify(json, null, 2));
    process.exit(0);
  }
  console.error(`✗ Import failed [HTTP ${status}]:`, JSON.stringify(json, null, 2));
  process.exit(1);
}

main().catch((err) => {
  console.error('Robot error:', err.message);
  process.exit(1);
});
