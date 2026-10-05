# VHP Robot (Phase 8)

A small, separate **Node + Playwright** script that automatically logs into VHP,
downloads the weekly reports (Statistic / Segment / Rate code / Source / Repeater),
and POSTs them to the dashboard's secure import endpoint.

This is **not** part of the Laravel app and does not run inside it. It runs on an
always-on machine inside the hotel network (or a scheduled cloud runner if VHP is
reachable from the internet), on a weekly schedule (Windows Task Scheduler / cron).

- VHP login credentials stay **only** on the robot machine — never in the dashboard.
- Uploads are signed with an HMAC using the shared `VHP_IMPORT_SECRET`, which the
  dashboard verifies before accepting the files.

> Placeholder — the robot is implemented in **Phase 8** (see `docs/PLAN.md`).
> Phase 3 delivers the matching manual file-upload + mapping flow first.
