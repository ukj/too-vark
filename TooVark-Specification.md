# PROJECT SPECIFICATION
<!-- FORMAT: Human-facing spec. Numbered chapters (1–6). Sections: # N. Title, ## N.M Subtitle, ### N.M.P Detail. Tables for structured data. Code blocks for examples. Narrative prose explaining rationale. Update version in header when features change. -->

---
**Töö Värk — Lightweight Work Scheduler**
Author: Uku-Kaarel Jõesaar \<ukj@ukj.ee\>
Date: March 2026

**Version 3.1.1** — Code Consolidation
---

# 1. Project Overview

## 1.1 Purpose

"Töö Värk" is a lightweight work scheduling application for small teams (up to approximately 40 workers). It generates monthly work schedules from text-based repeating rules, lets workers track task progress in real time, and gives managers a unified view of the entire team.

The application solves a specific problem: small service companies (cleaning, maintenance, delivery) where a manager needs to assign recurring tasks across a team, workers need to know what to do today, and the schedule needs to be flexible enough for manual corrections.

## 1.2 Design Philosophy

**Software minimalism.** The entire application compiles into a single PHP file plus a single SQLite database and icon.png. It requires no framework, no package manager, no build step, no external services. It runs on any device with PHP 8.3+ and a web browser.

This philosophy is not accidental. It is the core architectural constraint that shapes every decision in the project:

- **Vanilla everything:** raw PHP, raw JavaScript, raw CSS. No abstraction layers -- **Zero external dependencies**.
- **Single-file deployment:** copy `index_release.php` + `app.sqlite` (or uncompiled 'index.php', './src', './plugins') to any PHP-capable server.
- **Database as the engine:** SQLite constraints (UNIQUE, ON CONFLICT, CASCADE) handle business logic that other projects push into application code.
- **API-first:** all data flows through a single REST API layer (`?api=`). The HTML frontend is a thin shell. Mobile or desktop clients can use the same endpoints without touching PHP.

## 1.3 Target Users

| **Role** | **Description** | **Key Actions** |
|----------|----------------|----------------|
| Worker | Field employee who receives daily task assignments | View today's tasks, start/finish tasks, add notes, print work sheet, change own password |
| Manager / Admin | Team lead who creates schedules and manages workers | Define rules, generate multi-month schedules, assign ad-hoc tasks, manage users, view team overview, export CSV, manage location details |

## 1.4 Scale Assumptions

The application is designed for a team of up to 40 workers performing approximately 14,000 tasks per year. SQLite handles this volume comfortably with indexed queries and WAL mode for concurrent access. If the team outgrows this, the yearly archival feature keeps the active table small.


---

# 2. Functional Requirements

## 2.1 Authentication

The system uses session-based authentication with bcrypt-hashed passwords.

- **Login:** AJAX POST to `?api=login` with JSON body. On success, session stores user ID and username; response includes `uid`, `username`, and `is_admin` flag.
- **Logout:** AJAX POST to `?api=logout`. Destroys the PHP session.
- **Admin account:** A single protected admin user (configurable username, default "admin") is seeded on first run with `force_password_change=1`. This account cannot be deleted through the UI.
- **Forced password change:** Admin is seeded with the flag set. On every non-API page request, `index.php` checks the flag and redirects to `?view=change_password` until the password is changed. Prevents use of the application with default credentials.
- **No registration:** Only the admin can create new user accounts via `?api=users`.
- **Password change:** Workers can change their own password via AJAX POST to `?api=users/password`. Requires both `old_password` (verified against current hash) and `new_password`. Clears `force_password_change` flag on success.
- **Password security:** Password change requires verification of old password via `password_verify()` before accepting the new one. Session ID is regenerated on login via `session_regenerate_id(true)` to prevent session fixation.
- **Login rate limiting:** Failed login attempts are tracked per IP in the `rl` table. After 5 failures within a 5-minute window, further attempts return HTTP 429. Successful login clears the counter. Expired entries are auto-cleaned on each check. IP detection supports Cloudflare (`CF-Connecting-IP`) and reverse proxies (`X-Forwarded-For`).


## 2.2 Rule-Based Schedule Generation

This is the core feature. The admin (or a worker viewing their own rules) defines repeating rules via a visual editor, stored as a JSON array, and the system expands them into individual task records for a selected month.

### 2.2.1 Rule Format

Rules are stored as a JSON array in `user_rules.rules_text`. Each object has five fields:

```json
[{"title":"Cleaning","days":"ER","weeks":"1234","start":"08:00","end":"16:00"}]
```

| **Field** | **Format** | **Example** | **Notes** |
|-----------|-----------|------------|----------|
| title | Text (no spaces) | `Cleaning` | Underscores for multi-word titles |
| days | Digits 1–7 or letters ETKN RLP | `135` or `EKR` | 1=Monday ... 7=Sunday. Estonian letters map: E=1, T=2, K=3, N=4, R=5, L=6, P=7 |
| weeks | Digits 1–4 concatenated | `1234` | Relative full-week index within month. Only ISO weeks where all 7 days fall within the month are numbered. `1`=first full week, `1234`=all full weeks, `13`=bi-weekly |
| start | HH:MM | `08:00` | 24-hour format |
| end | HH:MM | `16:00` | 24-hour format |

**Visual Editor Abstraction:** 
To improve user experience, the raw JSON format is abstracted by a Visual Editor UI. The UI provides checkboxes for Weekdays (1-7) and Weeks (W1-W4), along with native time pickers. JavaScript handles strict two-way synchronization (`syncTextToVisual` and `syncVisualToText`) between the visual DOM nodes and a JSON textarea (used for debugging). The backend `?api=rules/generate` endpoint receives the JSON array string via `rules_txt`, completely decoupled from the visual UI implementation.

The engine computes ISO week numbers (`date('W')`) for each day in the target month, counts how many days each ISO week has within the month, and assigns relative indices (1, 2, 3, 4) only to weeks with all 7 days present. Days falling in partial weeks at the start or end of the month are skipped — the manager or worker adds those manually via the schedule editor. ISO week numbers are displayed as separators in the month view to assist rule authoring.

### 2.2.2 Multi-Month Generation

Rules can be generated for any month, not just the current one. The `?ym=YYYY-MM` URL parameter controls which month the Rules view targets. Navigation arrows allow moving between months. The admin can select a specific worker from a dropdown to manage their rules and generate their schedule.

### 2.2.3 The Smart Merge Guarantee

The rule engine uses `INSERT OR IGNORE` with a `UNIQUE(user_id, task_date, title)` constraint. This means:

- **If a task already exists** (same user, date, and title), the rule engine silently skips it.
- **Manually edited tasks are never overwritten.** A worker who changed their start time or started a task will not lose those changes when rules are regenerated.
- **Rules can be re-run safely** at any time. The database itself enforces the protection — no PHP-level checks needed.

*This is the single most important architectural decision in the project. It allows the rule engine to be simple (no diffing, no conflict detection) while guaranteeing data safety.*

### 2.2.4 True Sync (Auto-Cleanup)

When the admin saves and generates rules for a month via `?api=rules/generate`, the system first auto-deletes all **unstarted, rule-generated** tasks (`status = 0 AND source = 'rule'`) for the target user and month before generating new ones. Manually added tasks (`source = 'manual'`) are never touched by regeneration, regardless of status. This prevents ghost tasks and duplicates if the manager changes the rules, while preserving ad-hoc assignments. Tasks that have already been started (`status = 1`) or completed (`status = 2`) are strictly preserved.

## 2.3 Interactive Schedule Editor

The month's generated schedule is displayed as clickable UI cards grouped by date. Each card shows the task title, times, status, and notes. Clicking a card populates an edit form where the user can modify any field or delete the task.

New tasks can also be added manually through the same form. The save operation uses an `ON CONFLICT DO UPDATE` upsert via the `?api=tasks/save` endpoint, so the UNIQUE constraint is respected.

## 2.4 Today View (Worker)

Each worker sees their tasks for today as cards, fetched via `?api=tasks/today`. Also shown are yesterday's unfinished tasks to support over-midnight work. Each card shows the task title, planned start/end times, a notes field, and a status button.

### 2.4.1 Status Progression

| **Status** | **Label** | **Button Action** | **Time Behaviour** |
|-----------|----------|------------------|-------------------|
| 0 | Pending (Ootel) | Click → Start (status becomes 1) | `start_time` set to current clock time; `end_time` shifted to preserve planned duration (timeShift) |
| 1 | In Progress (Töös) | Click → Finish (status becomes 2) | `end_time` set to current clock time |
| 2 | Done (Valmis) | Button disabled | Times are final; card gets green styling |

Status is capped at 2 (`min(status + 1, 2)`). A worker can only update their own tasks (the SQL query includes `AND user_id = ?`).

### 2.4.2 Worker Notes

Each task card includes a text input for notes. Workers can type context (e.g. "Client was late", "Door locked") before clicking Start or Finish. Notes are stored in the `notes` column and visible to the admin in the team view.

## 2.5 Team View (Admin)

The admin sees all workers' tasks in a unified list, fetched via `?api=tasks/team`. Four sub-tabs:

- **Today's Overview:** Tasks grouped by `start_time`. Clickable rows populate the assignment form for quick edits.
- **Month Overview:** Tasks grouped by `task_date`, navigable via `?ym=YYYY-MM`. A scroll-to-today button in the toolbar jumps to the current date group.
- **Objects:** Location/object detail management — add/edit/delete address, contact person, and description attached to task titles. Data fetched via `?api=details`.
- **Users & Management:** User CRUD (add/edit/delete workers with username, password, real name, contact info), download database backup, DB health status, yearly archival (available December 21–31 only), key-value configuration store. User list displays clickable cards — clicking a row fills the edit form and scrolls up; saving scrolls down and flashes the updated row. All operations update without full page reload via surgical DOM and state updates. Data fetched via `?api=users` and `?api=details`.

The admin can assign ad-hoc tasks to one or more workers via a form (multi-select). The task title field provides autocomplete from known `task_details` entries. The admin can also delete unfinished tasks (`status < 2`). Completed tasks are protected from deletion.

## 2.6 Location / Object Details

The admin can attach metadata to task titles: address, contact person, and description. These details are stored in a separate table (`task_details`) keyed by title, so all tasks with the same title share the same location data. Details appear on printable work sheets and are managed via the `?api=details` endpoint.

## 2.7 Print View

Printable work sheets for paper-based field work. Print data is fetched via `?api=tasks/print` and rendered client-side. Two modes:

- **Single task:** Any user can print their own task by clicking the printer icon on a task card.
- **Batch print (admin):** All of today's tasks, grouped by worker, with page breaks between workers.

Each sheet includes: time, title, address, description, worker name, signature lines (worker + contact person if applicable). The page auto-triggers the browser print dialog after rendering.

## 2.8 CSV Export

The admin can download the current team view as a semicolon-separated CSV file with UTF-8 BOM for Excel compatibility. Fields: username, title, task_date, start_time, end_time, status. Export is generated client-side from the fetched team data.

## 2.9 Internationalisation (i18n)

Bilingual: Estonian (et) and English (en). Language is stored in the PHP session and toggled via a link in the navigation bar. All user-facing strings come from a dictionary array in `i18n.php`. The full dictionary is inlined as `const _i18n_data = {...}` in the `<head>` script block via `json_encode()`, eliminating a separate fetch. JavaScript accesses strings via `__('key')`. External clients can fetch the full translation dictionary via `?api=i18n`.

## 2.10 Progressive Web App (PWA)

The application includes a web app manifest (served via `?api=manifest`) and mobile meta tags, allowing workers to "Add to Home Screen" on their phones. It opens in standalone mode with a custom app icon, looking and feeling like a native app without requiring any app store distribution.

## 2.11 Yearly Archival

Available only in late December (21st–31st) via `?api=archive_year`. The admin clicks a button that runs `ALTER TABLE tasks RENAME TO tasks_YYYY`. The tasks table is recreated empty on the next page load. Archived data remains queryable in the renamed table.


---

# 3. Non-Functional Requirements

## 3.1 Technology Stack

| **Layer** | **Technology** |
|----------|---------------|
| Server language | PHP 8.3+ with `declare(strict_types=1)` |
| Database | SQLite 3 via PDO (`pdo_sqlite` extension) |
| Client language | Vanilla JavaScript (ES2017+: async/await, fetch, template literals) |
| Styling | Vanilla CSS, mobile-first (max-width: 600px), monospace font, 4-step spacing scale via CSS variables |
| Dependencies | None. Zero external packages. |

## 3.2 Performance

- SQLite WAL (Write-Ahead Logging) mode enables dozens of concurrent readers without "database locked" errors.
- Indexed queries on `task_date` for daily lookups.
- All client-server communication via async JSON through the REST API (no full page reloads for any data operation).
- Single SQL round-trip for most operations; transactions for batch operations.
- Compiled string templates (`compileTpl`) — template parsed once at load time, zero regex per render call. Combined with single `innerHTML` set per container, JS render times are 6–18ms for all views.
- Lazy loading via `IntersectionObserver` — month task groups, user cards, and detail cards use a unified `lazyRender()` helper that renders empty shells with `data-date`/`data-id`/`data-title` attributes. Row content fills when the shell scrolls within 200px of viewport. Reduces initial DOM work to 5–8ms for large month views. `scrollToToday()` works because shells always exist in DOM; targets are eagerly filled before scrolling.
- Implemented Surgical DOM updates and ETag caching on GET endpoints via DB/WAL file stats.
- Performance instrumentation — four-column timing (php, db, fetch, render) logged per request for profiling.

## 3.3 Security

- **CSRF protection:** All POST requests require an `X-CSRF-Token` header. The token is generated from `random_bytes(16)` on session start, stored in `$_SESSION['csrf_token']`, and injected as `const CSRF_TOKEN='...'` in the HTML `<script>` block. JavaScript sends it with every `apiCall()` POST. The check runs before any routing in `api.php`, covering login, debug logging, and all authenticated endpoints.
- **Input validation:** All write endpoints call `validate_task()` before database operations. The function checks title presence, date format, time format, and status range. Endpoints that only need a subset (e.g. `tasks/status`) pass `need_title=false, need_date=false`.
- **Error sanitisation:** Handler DB operations are wrapped in `db_try()`, which catches exceptions, detects UNIQUE violations (returns 400), logs details server-side via `error_log()`, and returns generic messages (`'Database error'`) to clients. No SQLite internals (table names, constraint names, file paths) are ever exposed in API responses.
- **Passwords:** bcrypt via `password_hash()` / `password_verify()`.
- **Output escaping:** Client-side via `escHtml()` applied to all template values before compiled template rendering. Template functions are pure concatenation — escaping is the caller's responsibility. Detail/user management views still use DOM `.textContent` assignment on `<template>` clones (inherently safe).
- **SQL injection:** All queries use prepared statements with parameter binding. The shared `upsert_task()` function encapsulates the INSERT ON CONFLICT pattern.
- **Authorisation:** Admin-only API endpoints are listed in `$_admin_routes` and rejected with HTTP 403 before dispatch. Plugin handlers check `$is_admin` internally per the P15 contract.
- **Centralised auth context:** Auth variables (`$uid`, `$is_admin`, etc.) are set once in `index.php`. Downstream files (`api.php`, `views.php`) use them directly — no re-reading from `$_SESSION`.
- **API boundary enforcement:** `api.php` handles all SQL and data transformation, always returns JSON via `json_exit()`. View functions (`view_*()`) are pure HTML shells with no database access — they cannot run queries or inject data.
- **Database file protection:** `.htaccess` blocks direct download of `.sqlite` files.
- **Session fixation prevention:** `session_regenerate_id(true)` is called immediately after successful login, before setting session variables.
- **Old password verification:** The `?api=users/password` endpoint requires the current password before accepting a change. Prevents unauthorized password changes from an unattended logged-in session.
- **Forced initial password change:** Admin seeded with `force_password_change=1`. All non-API page requests redirect to `?view=change_password` until the flag is cleared by a successful password change.
- **Login rate limiting:** Per-IP tracking in the `rl` table. 5 failed attempts within 300 seconds triggers HTTP 429 rejection. Auto-cleaned on each check. Supports Cloudflare and reverse proxy IP headers.


## 3.4 Deployment

Two deployment modes:

- **Development:** `php -S localhost:8000` with the multi-file `src/` structure.
- **Production:** Run `php compile.php` to merge everything into `index_release.php`. PHP files are compressed via `php_strip_whitespace()` per-file with tokenizer-safe line splitting. CSS is minified. JS is included as-is. Copy the output file + `app.sqlite` + `icon.png` to the server.

The build tool (`compile.php`) merges all `include_once` references from `index.php` into a single output file.

**Processing by file type:**
- **PHP:** `php_strip_whitespace()` per file (PHP's built-in tokenizer — strips comments and whitespace safely). Then `token_get_all()` splits the result at top-level `}` boundaries (function/class ends). Each function becomes one compact line. Inner control structures stay inline. Result: no 50KB single-line monsters, no broken code.
- **CSS:** Comment removal, whitespace collapse, line split at `}` and `;`.
- **JS:** Included as-is. ~46+21KB uncompressed is small enough for gzip.
- **debug.js:** Included only when `APP_DEBUG=true` in index.php. Skipped entirely in release builds.

File inclusion markers (`/* included from src/file.php [[[*/` and `/* EOF src/file.php */`) survive because compression runs per-file BEFORE wrapping.

**Version stamp:** `compile.php` extracts the version from the first `## vX.Y.Z` heading in `CHANGELOG.md` and injects `define('APP_VERSION', 'vX.Y.Z — YYYY-MM-DD HH:MM')` into the compiled output. In dev mode, `index.php` falls back to `define('APP_VERSION', 'dev')`.

**Debug vs Release:**
- `APP_DEBUG=false` → `index_release.php` with compressed PHP/CSS.
- `APP_DEBUG=true` → `index_debug.php` with original formatting + debug.js.

## 3.5 Browser Support

Any modern browser with ES2017 support (async/await, fetch API, template element). Optimised for mobile (600px max-width layout). PWA support enables full-screen standalone mode on mobile.


---

# 4. Data Model

## 4.1 Entity-Relationship

Four tables with two foreign key relationships, plus two standalone tables:

`users` ← 1:N → `tasks` (ON DELETE CASCADE)

`users` ← 1:1 → `user_rules` (ON DELETE CASCADE)

`tasks.title` ← N:1 → `task_details.title` (logical join, no FK constraint)

`config` — standalone key-value store for org details and settings

`rl` — standalone rate limiter, auto-cleaned

## 4.2 Table Definitions

### users

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| id | INTEGER | PRIMARY KEY (auto-increment) |
| username | TEXT | UNIQUE |
| password | TEXT | bcrypt hash |
| real_name | TEXT | Optional. Worker's display name |
| contact | TEXT | Optional. Phone, email, or other contact info |
| force_password_change | INTEGER | DEFAULT 0. Set to 1 on admin seed to force initial change |

### tasks

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| id | INTEGER | PRIMARY KEY |
| user_id | INTEGER | REFERENCES users(id) ON DELETE CASCADE |
| task_date | TEXT | Indexed (idx_tasks_date). Format: YYYY-MM-DD |
| title | TEXT | Part of UNIQUE(user_id, task_date, title) |
| start_time | TEXT | HH:MM |
| end_time | TEXT | HH:MM |
| status | INTEGER | DEFAULT 0. Values: 0=pending, 1=in-progress, 2=done |
| notes | TEXT | DEFAULT ''. Free-text worker notes |
| source | TEXT | DEFAULT 'manual'. Values: 'rule' (generated), 'manual' (hand-added). Used by True Sync cleanup. |

### user_rules

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| user_id | INTEGER | PRIMARY KEY, REFERENCES users(id) ON DELETE CASCADE |
| rules_text | TEXT | JSON array of rule objects `[{"title","days","weeks","start","end"}]` |

### task_details

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| title | TEXT | PRIMARY KEY NOT NULL |
| address | TEXT | Optional |
| description | TEXT | Optional |
| related_person | TEXT | Optional |
| checklist | TEXT | Optional |



### rl (rate limiter)

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| ip | TEXT | PRIMARY KEY |
| fails | INTEGER | Counter, incremented on each failed login |
| expire | INTEGER | Unix timestamp. Rows with `expire < time()` are auto-cleaned |

### config

| **Column** | **Type** | **Constraint** |
|-----------|---------|---------------|
| key | TEXT | PRIMARY KEY |
| val | TEXT | Arbitrary value. Used for org details (org_name, org_address, etc.) and other settings |

## 4.3 Critical Constraints

**UNIQUE(user_id, task_date, title)** on the tasks table is the foundation of the Smart Merge. Without this constraint, the rule engine would create duplicates and the `INSERT OR IGNORE` pattern would not work.

**ON DELETE CASCADE** on both `user_id` foreign keys means deleting a user automatically cleans up all their tasks and rules. No orphan records, no manual cleanup.

**PRAGMA foreign_keys = ON** must be set on every connection. SQLite disables foreign keys by default.

**PRIMARY KEY NOT NULL** on `task_details.title` explicitly overrides SQLite's legacy quirk of allowing NULL primary keys.


---

# 5. Architecture

## 5.1 File Structure

The application follows MVC via a simple include-based architecture with a REST API layer. **Model**: database.php (schema) + helpers.php (shared data functions). **Controller**: api_handlers.php (business logic) + api.php (thin router). **View**: views.php (HTML shells) + app.js (client-side fetch→template render). Data never flows from Model to View directly — always through Controller → JSON → client. `index.php` loads source modules in dependency order, routes API requests to `api.php`, and falls through to the HTML shell for page requests.

| **File** | **Responsibility** |
|---------|-------------------|
| `index.php` | Entry point. Constants (ORG_NAME, DB_FILE, USER1, APP_DEBUG), session, CSRF token, include chain, centralised auth context, HTML shell. |
| `src/i18n.php` | Translation dictionary. Pure data, no logic. |
| `src/helpers.php` | Pure utility functions: JSON I/O, validation, shared DB writes (upsert_task, insert_user), coworkers_map, time helpers, label arrays, known_titles. `db_try()` wraps handler DB operations in try/catch with UNIQUE detection. `workers_list()` and `users_full_list()` provide canonical user-table queries. No API handler logic. |
| `src/api_handlers.php` | Core `api_*()` handler functions. Each takes explicit params (PDO, data, uid), returns `[int $code, array $body]`. No globals, no $_SESSION. Called by api.php router AND tests/run.php directly. |
| `src/database.php` | PDO connection, PRAGMA, schema creation, admin seed. No handler logic, no prepared statements passed around. |
| `src/api.php` | REST router. CSRF gate → pre-auth exits → auth gate → admin-route gate (`$_admin_routes` array) → `match($api)` dispatch to api_handlers.php functions → plugin fallback for unmatched routes → `json_exit()`. Thin — no business logic. |
| `src/views.php` | HTML shell functions. Pure static markup. No SQL, no `$pdo`, no data arrays, no `json_encode`, no date calculations. Month nav populated by JS. |
| `src/init.js` | URL state, HTML helpers (escHtml, optionsHtml), `compileTpl()` template compiler, `lazyRender()` unified IntersectionObserver helper. |
| `src/app.js` | REST client, view init/refresh, compiled template rendering, visual rules editor. |
| `src/debug.js` | JS error logging. Included only when APP_DEBUG=true. |
| `src/style.css` | All styles including print. 4-step spacing scale (`--sp-1`…`--sp-4`), single `--radius`, CSS color variables. Button colors via scoped custom properties (`--_btn-c/bg/bc`). No inline styles — JS uses classList only. |
| `compile.php` | Build tool. Per-file php_strip_whitespace + css_compress. Tokenizer-safe line splitting at top-level boundaries. Inlines `plugins/` into single-file output. |
| `src/plugins.php` | Plugin loader. Glob-based discovery of `plugins/*.php`, registers routes/views/nav/schema/etag/write lists. Schema migrations via config table. |
| `plugins/debug.php` | Optional plugin: JS error logging (`debug_log`), performance timing (`timer_log`, `app_log`). Removable — core guards with `function_exists()`. |
| `plugins/config.php` | Optional plugin: config key-value CRUD, yearly archive, `api_can_archive()`. Removable — details endpoint falls back to `can_archive: false`. |
| `plugins/audit2.php` | Optional plugin: quality audit checklists. Templates, draft runs, commit/sign-off, weekly/monthly due alerts, admin report, access control (`audit_access` table). Schema v2. |
| `plugins/audit2.js` | Client side for audit2: runs list, checklist form, draft persistence (localStorage), report tab, access management tab. Uses core `apiCall()` and `__()`. |
| `tests/run.php` | 31 PHP test groups. In-memory SQLite. Tests call real api_*() and plugin handlers. |
| `tests/run_js.html` | 9 JS test groups. Covers isoWeek, escHtml, compileTpl, visual rules sync, status CSS class management. |
| `Styleguide.html` | Developer CSS reference. Reads `:root` vars, renders visual comparisons. Not compiled/deployed. |


### 5.1.1 Eliminated Files


## 5.2 Request Flow

Every request hits `index.php`, which runs the include chain. The `?api=` parameter routes REST requests to JSON endpoints that call `exit()` before reaching the HTML phase. If no API route matches, the HTML shell renders.

1. **Session starts**, CSRF token generated if absent, i18n loads, language switch handled.
2. **Database connects**, schema ensured, WAL mode enabled.
3. **Plugins loaded:** `plugins.php` scans `plugins/` directory, registers routes/views/nav items, runs schema migrations for plugins that declare a `schema_version`.
4. **Auth context set**: `$logged_in`, `$uid`, `$u_name`, `$is_admin`, `$today_date`, `$current_time`, `$view`, `$scope`, `$ym`, `$current_month` — computed once in `index.php`, used by all downstream files.
5. **API handlers loaded:** `api_handlers.php` is included, making all core `api_*()` functions available. Plugin handlers are already available from step 3.
6. **API routing:** `api.php` is included. If `?api=` is present: CSRF token validated on POST → pre-auth endpoints (manifest, login, i18n, backup) → auth gate → admin-route gate (early 403 for non-admins on admin-only endpoints) → `match($api)` dispatch → plugin fallback for unmatched routes → `json_exit()`. If `?api=` is absent, `api.php` returns immediately (no-op) and execution continues to the HTML shell.
7. **HTML shell:** `views.php` renders the page skeleton based on `$view`. Plugin nav items are merged into the nav bar. View functions output empty containers; plugin views are dispatched via the match default case. Template strings for compiled rendering are defined as JS constants via `json_encode()` in a `<script>` block. `CSRF_TOKEN` is injected as a JS const in the `<head>`. No data is embedded in the HTML.
8. **Client init:** `app.js` runs on `DOMContentLoaded`. Plugin JS files are included after `app.js`. `CSRF_TOKEN` is already available as a global const. Based on `CURRENT_VIEW`, calls the appropriate `init*()` function which fetches data from the REST API (with CSRF header on POSTs) and renders it into the DOM via compiled template functions.

### 5.2.1 Comparison: Before and After


## 5.3 Client-Side Architecture

JavaScript uses compiled string templates and data fetched from the REST API. No virtual DOM, no reactivity framework. The pattern:

1. PHP serves a static HTML shell with empty container `<div>` elements. Template strings are defined as JS constants in `<script>` blocks via `json_encode()`, with PHP `__()` for i18n.
2. At load time, `compileTpl()` parses each `{{tag}}` template string once (via regex split) into a fast function that concatenates values with zero regex per call.
3. On `DOMContentLoaded`, the appropriate `init*()` function runs (dispatched by `CURRENT_VIEW`).
4. The init function calls `apiGet()` which fetches JSON from the REST API.
5. A renderer function calls the compiled template function per row, joins the results, and sets `innerHTML` once on the container. In month mode, `renderTeamTasks()` uses skeleton-first lazy loading — shells render immediately, row content fills via `IntersectionObserver` as groups approach the viewport.
6. User interactions call `apiCall()` which POSTs JSON to the REST API and either updates the DOM surgically or refreshes the relevant view.

### 5.3.1 View Initialisation Map

| **View** | **Init Function** | **API Endpoint** | **Renderer** |
|---------|------------------|-----------------|-------------|
| Today | `initTodayView()` | `?api=tasks/today` | `renderTasks()` via compiled `_tplTask` |
| Rules/Month | `initRulesView()` | `?api=tasks/month&ym=...` | `renderTeamTasks()` via compiled `_tplGroup` + `_tplRow` + week headers |
| Team Tasks | `initTeamView()` | `?api=tasks/team&scope=...` | `renderTeamTasks()` + select population |
| Team: Objects | `initObjLocMgmt()` | `?api=details` | `renderDetailCards()` via `lazyRender()` + `_fillDetailCard()` |
| Team: Users & Mgmt | `initTeamMgmt()` | `?api=details` | `renderUserRows()` via `lazyRender()` + `_fillUserCard()` + config + archive |
| Print | `initPrintView()` | `?api=tasks/print` | Clones `#print-page-template` + `#print-task-template` |
| User Info | (none) | (none) | Static form, AJAX on submit |
| Change Password | (none) | (none) | Static form. Shown on forced change redirect with warning message |

## 5.4 REST API Endpoints

All endpoints use `?api=` routing. POST endpoints accept JSON body. All responses are JSON via `json_exit()`.

### 5.4.1 Public Endpoints (no authentication required)

| **Endpoint** | **Method** | **Purpose** |
|-------------|-----------|------------|
| `?api=manifest` | GET | PWA web app manifest |
| `?api=login` | POST | Authenticate. Body: `{username, password}`. Rate-limited: 5 attempts/IP/5min (HTTP 429). Returns: `{uid, username, is_admin}` |
| `?api=i18n` | GET | Full translation dictionary for the current language |

### 5.4.2 Authenticated Endpoints (session required)

| **Endpoint** | **Method** | **Access** | **Purpose** |
|-------------|-----------|-----------|------------|
| `?api=me` | GET | Any | Current session info: uid, username, is_admin, today, time |
| `?api=logout` | POST | Any | Destroy session |
| `?api=tasks/today` | GET | Any | Worker's today + yesterday-unfinished tasks |
| `?api=tasks/month` | GET | Any | Month tasks + rules + workers + suggestions. Params: `&ym=`, `&user_id=` |
| `?api=tasks/team` | GET | Admin | Team task overview. Params: `&scope=today\|month`, `&ym=` |
| `?api=tasks/print` | GET | Any (batch=admin) | Print data with joined details. Params: `&task_id=` |
| `?api=tasks/status` | POST | Worker (own) | Progress task status 0→1→2. Body: `{id, start_time, end_time, status, notes}` |
| `?api=tasks/save` | POST | Worker (own) / Admin (any) | Create or update single task |
| `?api=tasks/batch` | POST | Admin | Batch-assign task to multiple workers |
| `?api=tasks/delete` | POST | Worker (own, status\<2) / Admin (any, status\<2) | Delete unfinished task |
| `?api=rules/generate` | POST | Any (admin can target workers) | Save rules text + auto-cleanup + generate schedule |
| `?api=users` | GET | Admin | List all users (id, username, real_name, contact) |
| `?api=users` | POST | Admin | Add new user. Body: `{username, password, real_name, contact}`. Returns: `{msg, id, username, real_name, contact}` |
| `?api=users/update` | POST | Admin | Update existing user. Body: `{id, username, real_name, contact, password?}`. Password optional (omit to keep). Returns: `{msg, id, username, real_name, contact}` |
| `?api=users/delete` | POST | Admin | Remove user (admin protected). Body: `{id}` |
| `?api=users/password` | POST | Any | Change own password. Clears `force_password_change` flag. Body: `{old_password, new_password}` |
| `?api=details` | GET | Admin | All task details + known addresses + known contacts + DB status |
| `?api=details` | POST | Admin | Create/update location detail |
| `?api=details/delete` | POST | Admin | Delete location detail |
| `?api=backup` | GET | Admin | Download SQLite database file |
| `?api=archive_year` | POST | Admin | Year-end table rename (Dec 21–31 only) |
| `?api=config` | GET | Admin | List all config key-value pairs |
| `?api=config` | POST | Admin | Create/update config pair. Body: `{key, val}` |
| `?api=config/delete` | POST | Admin | Delete config pair. Body: `{key}` |

### 5.4.3 Error Responses

All errors return JSON with an `error` key and appropriate HTTP status:

| **Status** | **When** |
|-----------|---------|
| 400 | UNIQUE constraint violation, invalid request |
| 401 | Not logged in, or invalid credentials |
| 403 | Non-admin accessing admin endpoint |
| 404 | Unknown API endpoint |
| 405 | Wrong HTTP method |
| 500 | Database error |

## 5.5 XSS Protection Model

In the REST architecture, XSS responsibility is split between server and client:

- **Server (api.php):** Returns raw data. No `htmlspecialchars()` on API responses. This is deliberate — the API serves multiple potential clients (web, mobile, desktop) and HTML-escaping is a presentation concern.
- **Client (app.js):** All renderers pre-escape values via `escHtml()` before building HTML strings, then set `innerHTML` once on the container. The `escHtml()` function encodes `&`, `<`, `>`, and `"`. Callers are responsible for escaping — compiled template functions and `_fill*()` helpers perform no escaping (they are pure concatenation).
- **Server (views.php):** The few PHP-rendered strings (navigation labels, i18n strings) still use `htmlspecialchars()` via the `__()` function's dictionary lookup, which contains only developer-authored strings.


---

# 6. Future Roadmap

Planned features that are not yet implemented:

-
-
-