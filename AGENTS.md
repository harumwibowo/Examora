# AGENTS.md

## What this is

- **Examora** — PHP 8.3 + MySQL CBT (school exam) app. No framework, **no Composer, no package manager, no tests, no linter, no CI**. Don't hunt for them; the only mechanical check is `php -l <file>` (PHP 8.3 CLI is on PATH).
- Everything is **Indonesian**: identifiers, comments, UI strings, commit messages. Write new code and commits in Indonesian to match.

## Run locally

- Dev server: `php -S 127.0.0.1:8080 -t .` from the repo root (or Laragon, docroot = this folder). Needs MySQL reachable per `config/config.php`.
- The app only boots normally when **both** `config/config.php` and `install.lock` exist; otherwise every URL redirects to the setup wizard. Both are gitignored and contain secrets — never delete, never commit.
- `php -S` ignores `.htaccess`, so direct-access protection for `app/ config/ database/ storage/ views/` is **off in dev** (see docs/DEPLOY.md §7).

## Architecture — adding a page or action

- `index.php` is the front controller: `?r=<route>` is sanitized, then dispatched in one big `switch`. New URL = new `case` there + method on a controller.
- Controllers live in `app/ctrl_*.php` and are `require`d **explicitly** at the top of `index.php` — there is no autoloader; add a require for new controller files.
- Views: `View::render('path', $data, 'layout')` renders `views/path.php` inside `views/layouts/<layout>.php`. Layouts: `layout` (staff), `layout_siswa`, `layout_ujian`; `null` layout = raw output (JSON/errors).
- The sidebar menu is hardcoded `$nav(...)` calls in `views/layouts/layout.php` — new menu items go there.
- Shared helpers (`url`, `e`, `redirect`, `post`, `setting`, `audit`, `flash_*`) in `app/helpers.php`; static classes `DB`, `Auth`, `Csrf`, `View`, `Validator` in `app/core.php`.

## Schema changes (easy to get wrong)

Every DB change needs **two** places, or fresh installs and existing installs diverge:

1. `database/schema.sql` — for fresh installs.
2. An idempotent migration block in `app/bootstrap.php` — runs on request for existing installs, guarded by a marker row in the `pengaturan` table (a `schema_*` key set with `set_setting()` after success). Copy an existing block: check `information_schema`, `try/catch`, log failures, never fail the request.

## Request conventions

- Every mutating (POST) action starts with `Auth::requireStaff()` / `requireSuperadmin()` / `requireSiswa()`, then `Csrf::check()`; forms include `<?= Csrf::field() ?>`. This holds at ~44 call sites — it's the norm, not optional.
- DB access only through `DB::` (prepared statements); escape output with `e()`.
- After state changes: `audit(...)` + `flash_success()`/`flash_error()` + `redirect(...)`.

## Releases / self-update (matters for shipped changes)

- The app self-updates from GitHub `harumwibowo/Examora` `main` via `git pull` or ZIP (`app/ctrl_update.php`). It updates only when the repo's `VERSION` > the installed `VERSION` — **bump `VERSION`** for a release or existing installs never receive the change.
- The updater must never overwrite `config/config.php`, `install.lock`, `storage/`, `assets/uploads/`, `.git`. Put runtime files under one of those paths or they'll be clobbered by updates.
- Gitignored, never commit: `config/config.php`, `install.lock`, everything under `storage/`, `assets/uploads/*`.

## Other gotchas

- Timezone comes from the `timezone` setting (default `Asia/Jakarta`). Bootstrap applies it to PHP **and** re-syncs PDO's `SET time_zone` right after reading it — that re-sync is needed because reading the setting is what opens the connection; don't drop it. Changing the setting shifts **all** DATETIME columns via `timezone_shift_datetimes()` so stored instants stay identical (TIMESTAMP columns are left alone — MySQL stores those in UTC).
- Deployment docs: `README.md` (features/install), `docs/DEPLOY.md` (per-server guides), `docs/nginx-example.conf` (mandatory on Nginx — it doesn't read `.htaccess`).
