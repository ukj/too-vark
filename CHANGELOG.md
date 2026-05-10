# CHANGELOG
<!-- FORMAT: ## vX.Y.Z — Short Title, then ### Category heading, then prose or - bullet items. Newest version first. -->

## v3.3.0 — Audit Plugin: Access Control + Hardening

### Access Control
Workers can only access audit views if an admin has granted them a row in the new `audit_access` table. Admin is always allowed. The gate (`_aud_can()`) is checked in `plugin_audit_run`, `plugin_audit_run_create`, and `plugin_audit_due`. A new `plugin_audit_access` handler (GET + POST) lets admin list all users with their `has_access` flag and toggle it with a single checkbox — no page reload. The client-side access tab (`refreshAuditAccess`) renders checkboxes that call `audit_access` on change.

### Bug Fixes
- `audit2.php`: Fixed missing spaces in SQL schema column definitions (`template_id INTEGER` and `sort_order INTEGER`) which caused SQLite `no such column` migration errors.
- `api.php`: typo `csrf_ token` (space) → `csrf_token` — **every POST was returning 403**.
- `audit2.js`: `window.CSRF_TOKEN || ''` → `CSRF_TOKEN` — silent empty-token bypass removed.
- `audit_run` list query: `"r.user_id=$uid"` string interpolation → prepared statement `?`.
- `audit_check_task`: `"WHERE id=$tid"` inline query → prepared statement.

### Plugin Improvements
- Full `__aud()` / `$_aud_i18n` i18n — all strings bilingual (EN/ET), no hardcoded English.
- Method guards (`if ($method !== 'GET') return [405, ...]`) on all handlers.
- Proper helpers ported from audit v1: `_aud_parse_subtasks`, `_aud_week_bounds`, `_aud_month_bounds`, `_aud_sync_subtasks`, `_aud_template_by_id`.
- `plugin_audit_template_delete` (soft-disable) added — was missing in audit2.
- `plugin_audit_report` (admin aggregate) added — was missing in audit2.
- `schema_version` bumped to 2 (adds `audit_access` table + second index).
- JS now uses core `apiCall()` / `apiGet()` — custom `audApi()` removed; CSRF, loading state, retry all handled by core.
- Report tab and Access tab wired in JS; draft abstraction (`_audLoadDraft` / `_audSaveDraft` / `_audClearDraft`) uses typed helpers with `{results, ts}` shape.

### Tests (`tests/run.php`)
- `create_audit_schema()` creates `audit_access` table.
- Registration: `schema_version` 1 → 2, `plugin_audit_access` existence, `audit_access` route.
- All worker-facing test calls seed an `audit_access` row beforehand (pdo, pdo2, pdo3, pdoA).
- Access gate: worker without row → 403 on list/create/due; admin always 200.
- `issues_count` removed from commit response — assertions dropped.
- `status=draft` filter removed from audit2 run list — test replaced with access gate tests.
- New `plugin_audit_access` test block: GET list, grant, revoke, idempotency, id≤1 guard, 405 on DELETE.

### Tests (`tests/audit_plugin.html`)
- Loads `audit2.js` (was `audit.js`).
- i18n mock extended with `aud_runs`, `aud_no_runs`, `aud_no_access`, `aud_access_title`, `aud_templates`, `aud_access`.
- Function existence checks updated: `_audSubtaskRowHtml` → `_audSubRowHtml`; added `refreshAuditAccess`.
- Fixture HTML gains `#audit-panel-access`; `show(templates)` test verifies it stays hidden.
- Group 4 renamed and all calls updated to `_audSubRowHtml`.
- New Group 7: access tab checkbox HTML — two users, has_access flag, real_name/username fallback.

### Files Changed
| File | Change |
|------|--------|
| `plugins/audit2.php` | Access gate, `_aud_can()`, `plugin_audit_access`, full helpers, i18n, method guards, report, schema_version=2 |
| `plugins/audit2.js` | `apiCall()`, `__()` i18n, access tab, report tab, typed draft utils, `_audSubRowHtml` |
| `src/api.php` | Fix `csrf_ token` typo → `csrf_token` |
| `tests/run.php` | Schema, registration, access grants, access gate tests, `plugin_audit_access` group |
| `tests/audit_plugin.html` | audit2.js, i18n, function names, access panel fixture, Group 7 |
| `README_FIRST.md` | STATE v3.1.1 → v3.3.0 |
| `TooVark-Specification.md` | Added audit2.php + audit2.js rows; test count 29 → 31 |

### Overview

Printable record for committed audit runs. Matches the visual style of the core task print page (`?view=print`), reusing `#print-page-template`, `.print-org-header`, `.signature-row`, and `.signature-line` for consistency. Uncommitted drafts are not printable.

### Added

- **Route `audit_run/print`** (`plugins/audit.php`) — `plugin_audit_print()` GET handler. Returns `{run, template, auditor:{username,real_name,contact}, org, today}`. Ownership gate (worker sees own, admin sees any) + committed-only gate (`400 not_committed` for drafts).
- **View `audit_print`** (`plugins/audit.php`) — Empty shell `<div id="audit-print-container"></div>` populated by `initAuditPrintView()` in JS.
- **`initAuditPrintView()`** (`plugins/audit.js`) — Fetches `audit_run/print`, clones `#print-page-template` for the org header + page-break shell, appends an audit-specific meta block (auditor name + contact, date, committed-at, summary `passed/total` with pass/fail badge), a checklist table (✓/✗ per subtask with comments), and a `.signature-row` with the auditor signature line on the left and a blank witness/contact signature line on the right. Calls `window.print()`.
- **Print link on committed run detail** (`plugins/audit.js`) — `<a href="?view=audit_print&run_id=…" target="_blank">` in the footer of `openAuditRun()` when `committed_at !== null`. Marked `.no_Print` so it doesn't leak into the printed output if the user prints the runs list.
- **i18n keys** — `aud_print`, `aud_print_auditor`, `aud_print_date`, `aud_print_committed`, `aud_print_result`, `aud_print_pass`, `aud_print_fail`, `aud_print_comment`, `aud_print_summary`, `aud_print_signature`. All in both English and Estonian in the plugin's local `$_aud_i18n` map. The signature line underline label reuses the core `print_signature_line` key so the audit print signature block is visually identical to the task print signature block.
- **CSS** (`plugins/audit.css`) — `.aud-print-meta`, `.aud-print-list`, `.aud-print-check`, `.aud-print-name`, `.aud-print-comment` using design tokens only (`--sp-*`, `--radius`, `--c-border`, `--c-muted`, `--font-b`).
- **Test group 31 extension** (`tests/run.php`) — ~18 new assertions: route/view registration, `400 missing run_id`, `404 unknown run`, `403 non-owner non-admin`, `400 not_committed` on draft, `200` owner reads own committed run with full payload shape (auditor.real_name/contact/username, template.subtasks_ordered, run.results with comments preserved, has_issues flag, committed_at stamped), org config filtering (only `org_*` keys), admin can print any worker's run, `405` on POST.

### Modified Files

| File | Changes |
|---|---|
| `plugins/audit.php` | Added `plugin_audit_print()` handler, `view_audit_print()`, registered route + view + ETag entry, added 10 new i18n keys. |
| `plugins/audit.js` | Added Print link on committed-run footer, `initAuditPrintView()`, extended `DOMContentLoaded` dispatch. |
| `plugins/audit.css` | Added `.aud-print-*` print-view styles. |
| `tests/run.php` | Extended group 31 with print handler assertions. |

### Notes

- The `print_signature_line` i18n key is reused from the core translation set, keeping the printed audit signature line visually identical to the task print signature line. A dedicated `aud_print_signature` key is also defined for future divergence if needed.
- The print payload shape (`{print_data, today, org}` → `{run, template, auditor, org, today}`) is intentionally similar but not identical to `api_tasks_print` — audit prints are always single-page (one run per URL), so the outer `print_data` keyed-by-worker shape wasn't needed.

## v3.2.1 — Audit Plugin Test Fix

### Changed

- **Test group 31** (`tests/run.php`) — Introduced local `$dc31 = new DateContext('2025-03-12', '09:00')` and substituted all 49 handler-call references from `$dc` to `$dc31`. The outer global `$dc` is reassigned by group 26 to `2025-06-15`, so group 31's `run_date` assertions were matching the wrong date. Matches the pattern already used by groups 11 (`$dc11`), 16 (`$dc16`), 25 (`$dc25`).

## v3.2.0 — Audit Plugin

### Overview

New optional plugin `plugins/audit.php` adds quality-audit checklists with templates, draft runs, localStorage draft backup, commit snapshots, and admin reporting. Zero core changes — follows P15 exactly. Schema version 1 migrates three tables and two indexes on first boot; removing the plugin leaves the tables intact but dormant.

### Added

- **`plugins/audit.php`** — Plugin PHP. Handlers: `plugin_audit_template` (GET list / POST upsert), `plugin_audit_template_delete` (soft-disable via `active=0`), `plugin_audit_run` (list with filters: `template_id`, `user_id`, `from`, `to`, `status`; detail via `?id=`), `plugin_audit_run_create` (manual draft), `plugin_audit_check_task` (post-task-done trigger, idempotent per day), `plugin_audit_due` (week_end/month_end missing-run check), `plugin_audit_commit` (writes results JSON + computes `has_issues` + stamps `committed_at`), `plugin_audit_report` (admin-only per-template subtask pass rates + per-worker compliance). All follow P11 signatures.
- **`plugins/audit.js`** — Client UI. Tabs: Runs (default), Templates (admin), Report (admin), Run detail (overlay). localStorage drafts keyed `audit_draft_${run_id}` autosave on every checkbox/comment change, restored on reopen, cleared after successful commit. Reorderable subtask editor with ↑/↓ buttons. Admin-only panels gated by `.aud-admin-only` class toggled on `initAuditView()`. Exposes `window.auditOnTaskDone(task_id)` for optional integration with the core today-view.
- **`plugins/audit.css`** — Design-token-only styling. No hardcoded colors or spacing.
- **Plugin i18n pattern** — `$_aud_i18n` PHP map keyed by `$langi` avoids edits to `src/i18n.php`. JS strings are emitted into the global `i18n` object via a small PHP block at the top of `audit.js` (plugin JS files are include'd from within the `<script>` block in `index.php`, so PHP executes). `Object.assign(i18n, {...})` merges without replacing core keys. Portable pattern for future plugins.
- **Test group 31** (`tests/run.php`) — ~60 assertions covering:
  - Registration contract (9 handler functions + view + plugin/routes/views array keys)
  - Template CRUD: create, update, subtask resync (3→2), soft-disable, worker vs admin visibility (active-only vs all), admin gate on writes, id-validation, interval/subtasks validation
  - Draft run creation: ownership, unknown template → 404, missing id → 400
  - Commit: all-pass → `has_issues=0`, some-fail → `has_issues=1` + `issues_count` matches, already-committed → 400, non-array results → 400, ownership gate (non-owner non-admin → 403), admin bypass, unknown run → 404, `committed_at` persistence
  - Run list/detail: worker sees own only, admin `user_id` filter, `status=draft` filter, detail ownership gate, template + subtasks_ordered included in detail response
  - `task_done` trigger: target-match creates run, idempotent same-day call returns `existing`, no-match returns `run_id=null`, `status<2` returns `not_done`, non-owner → 403, unknown task → 404
  - Due check: returns `week_end`+`month_end` only (excludes `task_done`), weekly removed from due after commit in current ISO week
  - Admin report: 403 for workers, 200 for admin, `by_template` + `by_worker` keys present, run/issue counts correct

### Changed

- None. Audit plugin is purely additive. No core files modified.

### Modified Files

| File | Changes |
|---|---|
| `plugins/audit.php` | **NEW** — 400+ lines. Schema v1 migration, 8 handlers, view shell, local i18n map. |
| `plugins/audit.js` | **NEW** — ~470 lines. 4-tab UI, localStorage drafts, admin template editor, report. |
| `plugins/audit.css` | **NEW** — Design-token styling. |
| `tests/run.php` | Group 31 added. |

### Notes

- The `task_done` trigger is plugin-only: per P15 "no changes to core files needed", the client calls `audit_run/check_task` with the completed task id. The plugin looks up an active `task_done` template matching the task's title (exact match preferred, `NULL` target as global fallback) and creates a draft if one doesn't already exist for that worker/template today. Idempotent.
- `has_issues` is computed once at commit time — `WHERE has_issues=1` gives flagged runs with zero JSON parsing per the spec's intent.
- Admin UI for attaching the `auditOnTaskDone` hook to the core today-view is deliberately not wired up — preserving "no core changes". Can be added as a one-line edit in `app.js` later if automatic trigger-on-completion is desired.
- Plugin `compile.php` support assumed to preserve PHP tags inside `plugins/*.js` verbatim — the i18n emission in `audit.js` depends on it. Verify at next compile.

## v3.1.2 — db_try Custom Messages, $cfg Documented, Test Coverage

### Overview

Fixes the UNIQUE error message regression from v3.1.1, documents the `global $cfg` pattern as an explicit P11 exception, and adds test coverage for the three new helpers introduced in v3.1.1.

### Changed

- **`db_try()` gains `$unique_msg` parameter** (`helpers.php`) — New optional third parameter `string $unique_msg = 'duplicate_entry'` lets callers pass context-specific error strings on UNIQUE violations. Existing callers that don't pass the parameter keep the generic default.
- **`api_tasks_save`** — Passes `'Worker already has a task with this title on this date.'` to `db_try()`, restoring the human-readable error shown to users (was `'duplicate_entry'` in v3.1.1).
- **`api_users_update`** — Passes `'username_exists'` to `db_try()`, restoring the original error key (was `'duplicate_entry'` in v3.1.1).
- **P11 exception documented** (`README_FIRST.md`) — `$cfg` is now an explicit allowed exception to P11's "no globals" rule. It's a read-only boot constant loaded once in `database.php`, never mutated by handlers. Three handlers listed: `api_tasks_print`, `api_details_get`, `plugin_config` GET.
- **Test group 28 fixed** (`tests/run.php`) — Plugin config CRUD tests now refresh `global $cfg` after each write operation, simulating the production request lifecycle where each GET is a fresh PHP process with `$cfg` reloaded from DB.

### Added

- **Test group 30** (`tests/run.php`) — 13 assertions covering:
  - `db_try()`: success passthrough, generic Exception → 500, UNIQUE → 400 with default message, UNIQUE with custom `$unique_msg`, Integrity constraint variant
  - `workers_list()`: returns all users, has `id`+`username` columns only, sorted by username
  - `users_full_list()`: returns all users, has `id`+`username`+`real_name`+`contact` columns, sorted by username

### Modified Files

| File | Changes |
|---|---|
| `src/helpers.php` | `db_try()`: added `$unique_msg` parameter. |
| `src/api_handlers.php` | `api_tasks_save`, `api_users_update`: pass custom UNIQUE messages to `db_try()`. |
| `tests/run.php` | Group 28: `$cfg` refresh after writes. Group 30: new helper coverage. |
| `README_FIRST.md` | P11 updated with `$cfg` exception. `helpers.php` description updated. |

## v3.1.1 — Code Consolidation

### Overview

Structural refactoring focused on eliminating duplicate code across PHP handlers, JS lazy-loading, CSS button styles, and SQL queries. No new features. All safety patterns (CSRF, prepared statements, escHtml, auth centralisation) preserved. Net reduction: 153 lines removed, 119 added.

### Added

- **`db_try()` helper** (`helpers.php`) — Wraps DB operations in try/catch with UNIQUE-violation detection (returns 400) and generic error logging (returns 500). Single point of error handling replaces 10 identical try/catch blocks across handlers. No `$e->getMessage()` ever reaches the client.
- **`lazyRender()` helper** (`init.js`) — Unified `IntersectionObserver` lazy-loading function. Takes container ID, items, key attribute, shell builder, and fill function. Handles empty-state message, observer creation, teardown, and `data-lazy` lifecycle.
- **`workers_list()` / `users_full_list()` helpers** (`helpers.php`) — Canonical query functions for the two common user-table SELECT patterns. Called from `api_tasks_month`, `api_tasks_team`, `api_details_get`, and `api_users_list`.
- **`_applyDetailsData()` helper** (`app.js`) — Shared details-cache population used by both `refreshTeamMgmt()` and `refreshObjLocMgmt()`.
- **`$_admin_routes` gate** (`api.php`) — Array of admin-only endpoint names checked once before the `match()` block. Replaces 8 inline `!$is_admin ? [403,…]` guards.

### Changed

- **10 PHP handlers refactored to `db_try()`** — `api_tasks_save`, `api_tasks_batch`, `api_users_create`, `api_users_update`, `api_users_delete`, `api_users_password`, `api_details_save`, `api_details_delete`, `plugin_config_save`, `plugin_config_delete`, `plugin_archive_year`. Transaction-aware: `api_tasks_batch` checks `$pdo->inTransaction()` before rollback.
- **`renderUserRows()` and `renderDetailCards()` rewritten** — Now 5–8 lines each, delegating to `lazyRender()`. Three separate `IntersectionObserver` implementations (with `_userObs`, `_detailObs`, `_lazyObs` teardown) collapsed to one reusable function.
- **`refreshObjLocMgmt()` simplified** — Shared `_applyDetailsData()` populates `detailsCache` and datalists; also called by `refreshTeamMgmt()`. Prevents the two admin views from drifting when the API response changes.
- **Config queries eliminated** — `plugin_config` GET and `api_details_get` now emit from the boot-time `$cfg` variable via `global $cfg` + `array_map()`. Startup query (`line 244`) given `ORDER BY key` to match the sorted output the UI expects. Two fewer SQLite round-trips per admin page load.
- **User queries deduplicated** — Four inline `SELECT … FROM users ORDER BY username` replaced with `workers_list($pdo)` (2 call sites) and `users_full_list($pdo)` (2 call sites). Each SQL string exists in exactly one place.
- **CSS button system** — Five `.btn-*` color classes refactored from 3 declarations each to CSS custom property assignments (`--_btn-c`, `--_btn-bg`, `--_btn-bc`). `.btn-sm` base rule consumes the properties with fallbacks.
- **Dead CSS removed** — Empty `.d-contact,.d-address {}` rule, invalid `::odd`/`::even` pseudo-elements (not valid CSS, did nothing), duplicate `min-width` on `select[name="status"]`, duplicate `input[type="time/date"]` block.

### Modified Files

| File | Changes |
|---|---|
| `src/helpers.php` | Added `db_try()`, `workers_list()`, `users_full_list()`. |
| `src/api_handlers.php` | 8 handlers refactored to `db_try()`. User queries replaced with helpers. |
| `src/api.php` | Added `$_admin_routes` gate, removed 8 inline admin guards. |
| `src/init.js` | Added `lazyRender()`. |
| `src/app.js` | `renderUserRows`/`renderDetailCards` use `lazyRender()`. Added `_applyDetailsData()`. `refreshObjLocMgmt` simplified. |
| `src/style.css` | Button refactor, dead rule removal, duplicate merges. |
| `plugins/config.php` | 3 handlers refactored to `db_try()`. Config GET emits from `$cfg`. |

### Notes

- `global $cfg` is used read-only in `plugin_config` GET and `api_details_get`. The pre-existing `api_tasks_print` already used this pattern. `$cfg` is loaded once at boot and never mutated by request handlers — config writes take effect on the next request.
- `db_try` returns generic `duplicate_entry` for UNIQUE violations by default. The old `api_tasks_save` returned `'Worker already has a task with this title on this date.'` — this specific message was lost. Fixed in v3.1.2 by adding a `$unique_msg` parameter to `db_try()`.

## v3.1.0 — Plugin System

### Overview

Convention-based plugin architecture. Drop a PHP file into `plugins/`, it registers API routes, views, nav items, and schema migrations via a returned array. The plugin loader (`src/plugins.php`) scans the directory once at boot. Handlers follow the same P11 testability contract as core: `(PDO, $d, $uid, $is_admin, $dc, $method) → [code, body]`.

Two optional plugins extracted from core: `debug.php` (JS error logging, performance timing) and `config.php` (config key-value CRUD, yearly archive). Both are removable without breaking the app — core guards all plugin function calls with `function_exists()`.

### Added

- **`src/plugins.php`** — Plugin loader. Glob-based discovery of `plugins/*.php`. Collects routes, views, nav items, ETag route lists, write route lists. Runs schema migrations using the `config` table for version tracking (`plugin_schema_{id}` keys).
- **`plugins/debug.php`** — Extracted from `helpers.php` and `api.php`: `app_log()`, `timer_log()`, `debug_log` route, `debug_log/jstimer` route. Defines `plugin_debug_log()` and `plugin_debug_timer()` with P11 signatures plus `$method` parameter.
- **`plugins/config.php`** — Extracted from `api_handlers.php` and `api.php`: `api_can_archive()`, config GET/POST (`plugin_config()` handles both methods), `plugin_config_delete()`, `plugin_archive_year()`. All with P11 signatures plus `$method` parameter.
- **Plugin JS/CSS auto-inclusion** — `index.php` globs `plugins/*.js` and `plugins/*.css` after `app.js` and `style.css` respectively.
- **Plugin view/nav merge** — `views.php` merges `$plugin_nav` into the nav bar and falls back to `$plugin_views` in the match dispatch. Plugins can set `admin_only => true` for nav visibility.
- **Plugin-aware compiler** — `compile.php` inlines `plugins/*.php` after the `plugins.php` EOF marker (rewrites `return [...]` to feed loader variables), appends `*.js`, injects `*.css` before `</style>`. Single-file deploy preserved.
- **P15 pattern** — documented in README_FIRST.md: plugin contract, handler signature, `function_exists` guards.
- **Test groups 27–29** — Plugin registration contract (function existence), config CRUD via plugin handlers (12 assertions), debug handler signatures (3 assertions).

### Changed

- **`api.php` match block** — Removed 6 hardcoded routes (config/×1, config/delete, archive_year, debug_log, debug_log/jstimer). Default case now dispatches to `$plugin_routes[$api]` with `$method` passthrough. Match block shrunk from ~70 to ~50 lines.
- **`api.php` ETag/write lists** — Now merged with `$plugin_etag_routes` and `$plugin_write_routes` via `array_merge()`.
- **`api.php` details GET** — `api_can_archive()` call guarded with `function_exists()`, falls back to `false`.
- **`api_handlers.php`** — Removed 5 functions: `api_config_get`, `api_config_save`, `api_config_delete`, `api_archive_year`, `api_can_archive` (~70 lines).
- **`helpers.php`** — Removed `app_log()` and `timer_log()` (~15 lines). `json_exit()` timer call guarded with `function_exists('timer_log')`.
- **`index.php`** — Added `include_once 'src/plugins.php'` after `database.php`. Added plugin CSS/JS glob loops.
- **Test group 23** — Updated from `api_archive_year($pdo)` to `plugin_archive_year($pdo, [], $admin_id, true, $dc)` with P11 signature. Added non-admin → 403 assertion.

### Plugin Contract

A plugin file returns an associative array:

```php
<?php return [
    'id'             => 'my_plugin',        // required, unique
    'routes'         => ['endpoint' => 'handler_function_name'],
    'views'          => ['view_name' => function(): void { echo '<div>...</div>'; }],
    'nav'            => ['view_name' => 'Nav Label'],
    'admin_only'     => false,
    'schema_version' => 1,
    'schema'         => ["CREATE TABLE IF NOT EXISTS ..."],
    'etag_routes'    => ['endpoint'],
    'write_routes'   => ['endpoint'],
];
```

Handler signature: `function(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method): array`

### Modified Files

| File | Changes |
|---|---|
| `src/plugins.php` | New. Plugin loader with glob discovery and schema migration. |
| `plugins/debug.php` | New. Extracted debug_log, timer_log, app_log. |
| `plugins/config.php` | New. Extracted config CRUD, archive_year, api_can_archive. |
| `index.php` | Added plugins.php include, plugin CSS/JS glob loops. |
| `src/api.php` | Removed 6 routes, added plugin fallback dispatch with $method. |
| `src/api_handlers.php` | Removed 5 functions (~70 lines). |
| `src/helpers.php` | Removed app_log + timer_log (~15 lines), function_exists guards. |
| `src/views.php` | Plugin nav merge + plugin view fallback in dispatch. |
| `compile.php` | Plugin file embedding: PHP after plugins.php marker, JS appended, CSS injected. |
| `tests/run.php` | Plugin bootstrap, updated test 23, added test groups 27–29. |
| `README_FIRST.md` | P15 pattern, PLUGINS section, updated FILES/STATE/COMPILE. |
| `TooVark-Specification.md` | Updated version, file table, request flow. |

### Notes

- Core is ~85 lines smaller. Plugin code (123 lines) lives outside `src/`.
- Removing `plugins/debug.php` disables performance logging silently. Removing `plugins/config.php` disables admin config/archive — details endpoint returns `can_archive: false`.

## v3.0.4 — Lazy Loading & Performance Instrumentation

### Overview

All list renderers (month tasks, user cards, detail cards) now use skeleton-first lazy loading via `IntersectionObserver`. Group shells render immediately with `data-date`/`data-id`/`data-title` attributes; row content fills when the shell scrolls within 200px of the viewport. This preserves `scrollToToday()` (shells always exist in DOM) while cutting JS render times to 7–23ms on mobile.

### Added

- **Lazy loading for month views** — `renderTeamTasks()` renders empty group shells with `data-date` + `data-lazy="1"` in month mode. `IntersectionObserver` fills rows as groups approach viewport. Today scope renders eagerly (small dataset).
- **Lazy loading for user cards** — `renderUserRows()` renders empty `.user-row` shells. Observer fills via `_fillUserCard()`.
- **Lazy loading for detail cards** — `renderDetailCards()` renders empty `.card.loc_det` shells. Observer fills via `_fillDetailCard()`.
- **`_buildRows()` helper** — Extracted row HTML builder shared by observer, `scrollToToday()` eager fill, and today-scope eager render.
- **`_fillUserCard()` / `_fillDetailCard()` helpers** — Fill individual lazy shells from cached data. Used by observers and by `saveUser()` insert path.
- **Performance instrumentation** — Four-column timing: `php` (server total), `db` (SQLite init), `fetch` (JS network wait), `render` (JS DOM work). API timer in `api.php` exit point. JS timer splits `apiGet()` wait from render via `_fetchMs` accumulator.
- **`$time_db_ms` global** — Database init duration computed in `database.php`, reported by API timer.

### Changed

- **`scrollToToday()` eagerly fills lazy targets** — If the scroll target has `data-lazy`, populates rows from `teamData`/`rulesData` before scrolling. No empty flash.
- **`saveUser()` insert path** — Eagerly fills new user card before `flashRow()` when card is still lazy.
- **User/detail renderers switched to innerHTML** — Replaced `<template>` cloning + `appendChild` loops with HTML string concatenation + single `innerHTML` set. Faster even without lazy loading.
- **`await` on all `init*()` calls** — DOMContentLoaded handler now awaits init functions so jstimer captures full fetch+render cycle.
- **`timer_log()` simplified** — Handles JS view timing only (distinct format from API timer). No dead PHP branches.

### Performance

| View | Render before | Render after |
|------|--------------|-------------|
| team/month | 15–18ms | 7–8ms |
| team/baastegijad | 15ms | 6–7ms |
| team/wobjects | 7ms | 7–8ms |
| rules (month) | 10–23ms | 9–15ms |

### Modified Files

| File | Changes |
|---|---|
| `src/app.js` | Lazy loading in `renderTeamTasks()`, `renderUserRows()`, `renderDetailCards()`. `_buildRows()`, `_fillUserCard()`, `_fillDetailCard()` helpers. `scrollToToday()` eager fill. `_fetchMs` timing. `await` on init calls. |
| `src/helpers.php` | `timer_log()` simplified to JS-only format. |
| `src/api.php` | Server-side timer at exit point (php + db columns, skips debug_log). |
| `src/database.php` | `$time_db_ms` global for DB init duration. |

### Notes

- `user-row-template` and `detail-card-template` in views.php are now unused but harmless to keep.

## v3.0.3 — Source Column & Today Button Fix

### Changed
- Rules regeneration no longer deletes manually added tasks. New `source` column (`'rule'` vs `'manual'`) on the tasks table lets the DELETE target only rule-generated not-started tasks. Manually added future tasks survive regeneration.

### Bugfix
- `scrollToToday()` now navigates to today's month before scrolling. Previously it only searched `data-date` elements within the already-rendered DOM, so the Today button did nothing when viewing a different month.
- Month navigation uses `#today` URL hash to trigger scroll after page reload; hash is cleaned from the URL via `replaceState` after use.
- Removed automatic scroll-to-today from `refreshRulesView()` and `refreshTeamView()` — scroll only fires when the user clicks the Today button, or once on load if arriving via `#today` hash.

### Tests

**run_js.html** — added test group 9 with 5 assertions:
- 9a: exact today match → highlight-flash added
- 9b: today missing → nearest future date highlighted, past date not
- 9c: all past dates → nothing highlighted, no error
- 9d: missing container → no throw
- 9e: redirect URL construction includes #today hash and correct ym=

## v3.0.2 — Compiled Templates

### Overview

Replaced regex-per-row `tpl()` rendering and DOM `cloneNode()` with compiled string templates. Templates remain in `views.php` (clean HTML with PHP i18n), but are parsed once at load time into fast functions. Zero regex per row.

**JS render times:** 112–208ms → 6–18ms.

---

### Added

- **`compileTpl()` in init.js** — Splits a `{{tag}}` template string once via regex into alternating literal/key parts. Returns a plain function that concatenates values — no regex on each call. Missing keys return `''`, extra keys are ignored, malformed tags like `{{}}` or `{{foo bar}}` pass through unchanged.
- **`_tplTask` template** — Today view tasks now rendered via compiled string template (was DOM `cloneNode` + `querySelector` per task + `appendChild` per task).
- **Test group 2b** — 10 `compileTpl` tests: basic substitution, missing keys, extra keys, no-tag passthrough, empty template, duplicate keys, malformed tags, function reuse.

### Changed

- **`_tplRow` / `_tplGroup` in views.php** — Wrapped in `compileTpl()` instead of raw string constants. Template HTML unchanged, just compiled at load time.
- **`renderTeamTasks()`** — Calls `_tplRow({...})` / `_tplGroup({...})` (compiled functions) instead of `tpl(_tplRow, {...})` (regex per call).
- **`renderTasks()`** — Rewritten from `cloneNode` + 6× `querySelector` + `appendChild` per task to compiled `_tplTask({...})` + single `innerHTML` set.
- **Month navigation links** — Now preserve `user_id` parameter when clicking prev/next month.

### Removed

- **`tpl()` function** — Regex-per-call template engine replaced by `compileTpl()`.
- **`_tplWeek` constant** — Inlined as a simple string concatenation in `renderTeamTasks`.
- **`<template id="task-card-template">`** — DOM template no longer used; replaced by `_tplTask` compiled string template.

### Performance

| View | JS before | JS after |
|------|-----------|----------|
| today | 112ms | 7ms |
| rules | 112ms | 7–18ms |
| team/today | 192ms | 6ms |
| team/month | 208ms | 7ms |

### Modified Files

| File | Changes |
|---|---|
| `src/init.js` | Added `compileTpl()` function |
| `src/views.php` | `_tplRow`, `_tplGroup` wrapped in `compileTpl()`. Added `_tplTask`. Removed `<template id="task-card-template">` |
| `src/app.js` | `renderTasks()` rewritten. `renderTeamTasks()` uses compiled functions. Removed `tpl()`, `_tplWeek`. Month nav preserves `user_id` |
| `tests/run_js.html` | Added test group 2b: 10 `compileTpl` tests |

---

## v3.0.1 Performance Optimization

### Overview

**Starting point:** 440ms+ page loads with 39,682 tasks, 16 users, 16 task details, 3 years of demo data.  
**End result:** ~40ms real-world Chrome render, 18ms main thread JS, sub-2ms `renderTeamTasks`.

---

### Added

- **Text size toggle** — Users can cycle through text sizes (16px → 18px → 20px) via the new `Aa` button in the navigation. Preference persists in `localStorage`.
- **Scroll to form on detail card click** — Detail form now automatically scrolls into view after selection.
- **i18n support** — Added `g_btn_done`, `g_btn_wait`, and `g_text_size` translation keys for better internationalization.

### Changed

#### Server-side (PHP / SQLite)

- **`known_titles()` optimization** — Eliminated 40K-row full table scan by querying only `task_details` (the canonical title source) instead of both tasks and task_details tables. Biggest single server-side performance win.

- **LIKE → range queries** — Replaced all `task_date LIKE 'YYYY-MM-%'` with indexed range queries (`task_date >= ? AND task_date < ?`). Lets SQLite use `idx_tasks_date` index as a clean range scan.
  - Affected endpoints: `api_tasks_month`, `api_tasks_team`, `api_rules_generate`, `coworkers_map`

- **Merged month queries** — `api_tasks_month` now runs a single range query spanning both current and previous months, split in PHP, eliminating one SQLite round-trip.

- **Added composite index** — `CREATE INDEX idx_tasks_date_title ON tasks(task_date, title, user_id)` covers the `coworkers_map` JOIN pattern.

- **WAL checkpoint mode** — Changed from `PRAGMA wal_checkpoint(FULL)` (blocking I/O) to `PASSIVE` (non-blocking) in `api_details_get`.

- **Conditional WAL initialization** — `PRAGMA journal_mode=WAL` now only sets if not already active, avoiding redundant operations.

- **ETag hashing** — Switched from `md5()` to `crc32()` for file-based ETags, reducing computational cost while maintaining uniqueness.

- **Write-side cache clearing** — Added `clearstatcache(true, DB_FILE)` on all write endpoints (`tasks/save`, `tasks/delete`, `tasks/status`, `tasks/batch`, `rules/generate`, `users/*`, `details/*`, `config/*`, `archive_year`).

- **Merged admin data fetches** — Combined details, users, and config into a single API round-trip for initial admin page load.

- **Known addresses/contacts derivation** — Now derived from PHP array instead of separate SQL queries.

#### Network & Loading

- **Inlined i18n data** — Translation data now outputs directly in `<head>` as `const _i18n_data = {...}` via `json_encode`, eliminating the separate 17ms `?api=i18n` fetch.

- **Preloaded data fetch** — Main data fetch (`tasks/today`, `tasks/month`, or `tasks/team`) starts as a `fetch()` promise in the `<head>` script while HTML is still parsing. Response resolves before `DOMContentLoaded` fires.
  - **Before:** HTML → DOMContentLoaded → i18n fetch (17ms) → data fetch (57ms) → render
  - **After:** HTML → data fetch starts in `<head>` → DOMContentLoaded → resolve in-flight fetch → render

#### Client-side (JavaScript)

- **`renderTeamTasks` rewrite** — Replaced per-row `cloneNode()` + DOM queries with string-based template rendering:
  - Before: 10,000+ DOM lookups and 2,000+ closures for 1000+ tasks
  - After: Single regex pass per template + string join + one `innerHTML` set = 1.8ms total render time

- **Templates via `json_encode()`** — Template strings now defined as JS constants in `<script>` block via PHP, bypassing browser HTML parser issues with auto-closing `<tr>` tags.

- **Event delegation** — Replaced 1000+ per-row click handlers with single delegated `onclick` on container. Task data stored in `_taskMap` object keyed by ID.

- **DOM shorthand** — Introduced `const $ = i => document.getElementById(i)` to reduce repetitive `document.getElementById` calls (~1500 characters saved).

- **Form ID collision fix** — Fixed click-to-edit silently discarding task IDs due to DOM property collision. Applied `querySelector('[name=id]')` pattern consistently.

- **User form data caching** — Fixed `usersCache` not being populated after details endpoint merge, which broke form lookups for edit/delete operations.

- **Task ID assignment in updates** — Fixed `$new_id` undefined error in API `tasks/save` UPDATE branch by assigning `(int)$d['id']`.

- **Print org header** — Fixed missing `innerHTML` assignment for organization header in print view.

#### CSS & Layout

- **CLS prevention** — Added `min-height` to JS-filled containers to prevent layout shift:
  - `#worker-month-container`, `#team-tasks-container`: `min-height: 50vh`
  - `#visual-rules-container`: `min-height: 3em`

- **Removed hardcoded emoji from code** — Moved 12 emoji prefixes (`👥`, `📍`, `⏰`, `👤`, `📞`, `✎`) to CSS `::before` rules on respective classes. Also removed them from templates in views.php.

- **Line break handling** — Replaced `tNl()` HTML-escape function with `textContent` + CSS `white-space: pre-line` for safer DOM manipulation.

### Removed

- **`tNl()` function** — HTML-escape and newline converter replaced by `textContent` + CSS `white-space: pre-line`.
- **`h()` function** — Mini HTML tag builder removed; all 5 call sites replaced with inline strings or template literals.
- **Hardcoded emoji from JavaScript** — 12 emoji prefixes moved to CSS, reducing payload and improving maintainability.
- **Hardcoded `✓` and `...` symbols** — Replaced with i18n calls `__('g_btn_done')` and `__('g_btn_wait')`.
- **Redundant `-ms-user-select` CSS prefix** — Removed for modern browser support.
- **`t()` alias** — Removed redundant function alias.

### Fixed

- **Form ID DOM property collision** — Click-to-edit handler now uses `querySelector('[name=id]')` instead of relying on `form.id` which returns the DOM id string, not the hidden input value.
- **SQL variable initialization** — Fixed `$new_id` undefined error in `api_tasks_save` UPDATE branch.
- **JavaScript syntax error** — Fixed stray closing brace after `scrollToToday` refactor that broke all code below it.
- **User form population** — Fixed `usersCache` not being populated after details endpoint consolidation.
- **Print view header rendering** — Fixed missing `innerHTML` assignment in print organization header.

---

### Modified Files

| File | Changes |
|---|---|
| `index.php` | Inlined i18n, preload data fetch in `<head>` |
| `src/init.js` | Uses inlined i18n, captures `_prefetch` promise, text size toggle IIFE |
| `src/app.js` | Prefetch promise handling, `renderTeamTasks` rewrite, `tpl()` function, `_taskMap` + delegation, form ID fixes |
| `src/views.php` | Templates as `json_encode()`'d JS strings, removed inline emoji, added text size button |
| `src/api_handlers.php` | Range queries, merged month query, PASSIVE checkpoint, merged admin fetches |
| `src/helpers.php` | Simplified `known_titles()`, derived addresses/contacts from PHP |
| `src/database.php` | Composite index, conditional WAL, split PRAGMAs |
| `src/api.php` | Write-side `clearstatcache()`, `crc32()` ETag, fixed `$new_id` assignment |
| `src/style.css` | CLS prevention `min-height`, `white-space: pre-line`, emoji `::before` rules, text size variables |
| `i18n.php` | Added translation keys: `g_btn_done`, `g_btn_wait`, `g_text_size` |

---

### Performance Metrics

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Page load time | 440ms+ | ~40ms | **91% faster** |
| Chrome render time | — | ~40ms | — |
| Main thread JS | — | 18ms | — |
| `renderTeamTasks` | ~10,000+ DOM ops | <2ms | **5000x faster** |
| i18n fetch | 17ms | 0ms (inlined) | Eliminated |
| DOM queries | ~10,000 per render | Single regex pass | **99% reduction** |

---

### Known Limitations & Future Opportunities

**Server-side data inlining:** The remaining optimization opportunity is the ~650ms "element render delay" — time from first byte until content paints. This is the network round-trip for API fetches, even with preloading. 

Inlining month data directly in the HTML response (similar to i18n) would eliminate this delay, reducing total wall clock to ~80–100ms. Tradeoff: HTML would grow by ~20KB and TTFB would increase by query time.

---

### Testing Notes

- Verify P12 surgical DOM updates now work correctly for task edits (previously masked by form ID collision bug)
- Test text size toggle persistence across page reloads
- Confirm detail form scrolls into view on card selection
- Validate form edit operations properly capture task IDs

### Documentation Updates

- [ ] Update TESTING documentation with P12 surgical update validation
- [ ] Update API documentation if endpoint behavior changed (details consolidation)

## v3.0.0 — Security Hardening

### Forced Initial Password Change
**database.php:** Admin user now seeded with `force_password_change=1`. New column `force_password_change INTEGER DEFAULT 0` added to users table.
**index.php:** Non-API requests redirect to `?view=change_password` when flag is set. Prevents any use of the app with default credentials.
**views.php:** New `view_change_password()` — dedicated full-page form with localized warning message.
**api_handlers.php:** `api_users_password()` clears the flag on successful change (`SET force_password_change = 0`).
**i18n.php:** Added `msg_force_change_required` translation key.

### Login Rate Limiting
**database.php:** New `rl` table (`ip TEXT PK, fails INTEGER, expire INTEGER`) for tracking failed login attempts.
**helpers.php:** Three new functions — `get_ip()` (CF/proxy-aware), `rl_ok()` (check < 5 fails + auto-clean expired), `rl_fail()` (UPSERT increment with 300s expiry), `rl_clear()` (wipe on success).
**api.php:** Login endpoint now calls `rl_ok()` before auth and `rl_fail()`/`rl_clear()` after. Returns HTTP 429 when limit exceeded.

### Build Version Stamp
**compile.php:** Extracts version from first `## vX.Y.Z` heading in CHANGELOG.md, injects `define('APP_VERSION', 'vX.Y.Z — YYYY-MM-DD HH:MM')` into compiled output.
**index.php:** Fallback `define('APP_VERSION', 'dev')` for uncompiled dev mode.

### Demo Database — 3-Year Stress Test
**tests/demo-db.php:** Rewritten. 15 workers, two-pass generation (rule engine + daily fill), ~50k tasks across 3 years. Configurable via `YEARS` env var. Realistic statuses, time jitter, Estonian notes.

### JSDoc Comments
**app.js:** All 30 functions now have one-line JSDoc comments.

### Files Changed
| File | Change |
|------|--------|
| `src/database.php` | `force_password_change` column, `rl` table, admin seed with flag=1 |
| `src/helpers.php` | `get_ip()`, `rl_ok()`, `rl_fail()`, `rl_clear()`, `known_titles()` LIMIT 100 |
| `src/api.php` | Rate limiting on login endpoint |
| `src/api_handlers.php` | `api_users_password()` clears force flag |
| `src/views.php` | `view_change_password()`, added to dispatch |
| `src/i18n.php` | `msg_force_change_required` key |
| `src/app.js` | JSDoc on all functions |
| `index.php` | Force-change redirect, `APP_VERSION` fallback |
| `compile.php` | Version extraction from CHANGELOG.md |
| `tests/demo-db.php` | 3-year dataset, 15 workers, ~50k tasks |

## v2.9.2 — Archival Guard & UX Tweaks

### Yearly Archival Protection
**api_handlers.php:** `api_archive_year()` now explicitly queries `sqlite_master` to verify if the `tasks_YYYY` archive table already exists before attempting to rename the active table. It safely returns a `400 archive_already_exists` error instead of throwing a raw `PDOException` if an admin inadvertently double-clicks or runs the archival process twice in the same year.

### Visual Editor Scroll
**app.js & views.php:** Adding a new rule in the Visual Rules Editor now smoothly scrolls the view to the bottom of the list. Added `<div id="visual_rules_bottom"></div>` to the DOM as a scroll anchor.

### Inline Style Extraction
**views.php & style.css:** Removed the remaining inline styles (`margin-top` and `display:inline`) from the Config Key-Value store editor in `view_team_mgmt()`. Introduced the `.conf_add` utility class to handle this layout.

### Minor Improvements
- **ETag Hashing:** Changed the ETag generation algorithm in `api.php` from `crc32()` to `md5()` for database and WAL file statistics, providing better collision resistance.
- **Test Suite:** Added strict assertions to the `api_tasks_save` endpoint integration test in `tests/run.php` to verify that the `id` property is reliably returned and valid (`> 0`) on both initial creation and duplicate upserts.

### Files Changed
| File | Change |
|------|--------|
| `src/api_handlers.php` | `api_archive_year()` checks `sqlite_master` for existing archive. |
| `src/api.php` | ETag hash switched from `crc32()` to `md5()`. |
| `src/views.php` | Config editor inline styles replaced with `.conf_add`. Added `#visual_rules_bottom` anchor. |
| `src/app.js` | `addVisualRule()` scrolls to `#visual_rules_bottom`. |
| `src/style.css` | Added `.conf_add` class. |
| `tests/run.php` | Enhanced `api_tasks_save` endpoint tests. |

## v2.9.1 — Demo Database Seed

### tests/demo-db.php (new)

A development-only seed script that builds a fully populated `app-demo.sqlite` in the project root. Running it produces a ready-to-browse database with no manual setup:

- **1 admin** (`admin` / `adm1Np`) + **10 named workers** with Estonian names, real names, and contact numbers.
- **org_\* config** — all five print-header keys (`org_name`, `org_address`, `org_phone`, `org_email`, `org_person`) seeded via `ON CONFLICT DO UPDATE`.
- **10 task_details** rows covering the full range of task types used in rules (office cleaning, stairwells, windows, floor treatment, carpet, sanitary, waste, snow, garden, post-event).
- **30 tasks** spread across yesterday / today / tomorrow — yesterday mostly `status=2` with one leftover `status=1`; today a mix of all three statuses; tomorrow all `status=0`. Uses `upsert_task()` so the script is idempotent.
- **10 user_rules rows** — each worker gets a distinct repeating schedule (1–3 rules) using the full `days`/`weeks` encoding (`ETKNR` day letters, `1234`/`13`/`24` week patterns). Covers daily, bi-weekly, and alternating patterns across different task types.

All inserts use `INSERT OR IGNORE` / `ON CONFLICT DO UPDATE` — safe to re-run against an existing database.

### compile.php — GenDemoDB link

A **GenDemoDB** link has been added to the `compile.php` admin page. Clicking it runs `tests/demo-db.php` in-browser and displays the seed summary (user list, counts per table). Intended for development only; not included in the compiled release build.

### Files Changed

| File | Change |
|------|--------|
| `tests/demo-db.php` | NEW. Full seed: org config, 10 workers, task_details, tasks, user_rules. |
| `compile.php` | GenDemoDB link added to dev admin page. |



### User CRUD Form with Surgical DOM Updates

The user management section in the "Users & Mgmt" tab has been rebuilt from a combined add/remove widget (username+password inputs plus a "remove" dropdown) into a full add/edit/delete form with a scrollable user list, matching the pattern used by Location Details and Month Task editing.

**views.php:** `view_team_mgmt()` rebuilt. The old `<form onsubmit="manageUser(event)">` with its add-fields + remove-dropdown is replaced by a proper add/edit form (`<form onsubmit="saveUser(event)">`) with hidden `id` field, username, password, real_name, contact inputs, and Save/Clear buttons via `html_save_cancel()`. A `#users-list-container` div holds rendered user cards. The remove-user `<select>` is eliminated. New `<template id="user-row-template">` added to `view_templates()` — uses the same `loc_det` card pattern as detail cards (username bold, real_name/contact below, delete ✕ button top-right).

**app.js:** Complete rewrite of user management JS:
- `initTeamMgmt()` now binds delegated click on `#users-list-container` (click row → `populateUserForm()`, click ✕ → `deleteUser()`), plus form reset listener to switch back to add mode.
- `renderUserRows()` clones `#user-row-template`, filters out admin, shows user cards with username/real_name/contact.
- `populateUserForm()` fills the form from `usersCache`, scrolls up to the form, shows password hint ("Leave blank to keep"), switches header to "Edit User: username".
- `saveUser()` creates via `?api=users` POST or updates via `?api=users/update` POST. On edit: surgical `Object.assign()` on `usersCache` + direct DOM text node updates + `flashRow()`. On create: pushes to cache, sorts alphabetically, full re-render, flashes new card.
- `deleteUser()` surgical cache filter + DOM `.remove()`. If editing the deleted user, resets form.
- New `usersCache` array stores fetched user data for click-to-fill and surgical updates.
- Old `manageUser()` function removed.

**api_handlers.php:**
- `api_users_list()` now returns `real_name` and `contact` fields (was id+username only).
- `api_users_create()` now returns `{msg, id, username, real_name, contact}` for surgical insertion without re-fetch.
- New `api_users_update()` handler: updates username/real_name/contact, optionally password (only if non-empty), with UNIQUE protection and admin-protected `id<=1` guard. Returns updated user data.

**api.php:** Added `users/update` route. Added `users` to ETag caching list.

**i18n.php:** Added keys: `um_edit_user`, `um_add_new`, `um_pass_hint`, `um_no_users`, `um_del_confirm`.

**style.css:** Added `.u-realname, .u-contact` styling for user cards.

### UX Behaviour
- Clicking a user row fills the form and scrolls up (same as clicking a task card or location card).
- Saving scrolls down to the entry and flashes it.
- Delete shows confirmation with username, removes the card without page reload.
- Form "Clear" button resets to add mode, re-enables password as required.
- In edit mode, password field is optional with hint text shown.
- No full page reloads — all operations update `usersCache` array and DOM surgically.

### Files Changed
| File | Change |
|------|--------|
| `src/views.php` | `view_team_mgmt()` rebuilt with add/edit form + user list container. New `#user-row-template`. |
| `src/app.js` | New `initTeamMgmt()` with delegation, `renderUserRows()`, `populateUserForm()`, `saveUser()`, `deleteUser()`, `usersCache`. Old `manageUser()` removed. |
| `src/api_handlers.php` | `api_users_list()` returns real_name/contact. `api_users_create()` returns new id. New `api_users_update()`. |
| `src/api.php` | Added `users/update` route. `users` added to ETag cache list. |
| `src/i18n.php` | 5 new translation keys for user management. |
| `src/style.css` | `.u-realname, .u-contact` styling. |


## v2.8 — Team View Split & Navigation Improvements

### Team Sub-Navigation Restructured
The admin Team view previously had three sub-tabs: Today, Month, Users & Management (which also contained the location details editor). Location/object management has been separated into its own sub-tab, giving four tabs: **Today** · **Month** · **Objects** · **Users & Mgmt**.

**views.php:** `view_objloc_mgmt()` extracted from `view_team_mgmt()`. Contains the details form (`#details-form`), datalists, and `#details-list-container`. The team management view now contains only user CRUD and database backup/archive. Sub-nav updated with the `wobjects` scope link.

**app.js:** New `initObjLocMgmt()` and `refreshObjLocMgmt()` functions handle the Objects tab. `initObjLocMgmt()` owns the `details-list-container` click delegation. `refreshObjLocMgmt()` fetches details, fills datalists, and renders cards. `initTeamMgmt()` and `refreshTeamMgmt()` stripped of all details-related code (which no longer exists in their DOM). `saveDetails()` and `deleteDetails()` are scope-aware — they call `refreshObjLocMgmt()` on `wobjects`, `refreshTeamMgmt()` on `baastegijad`.

**Bug fix:** The dispatch block had `if (scope === 'baastegijad') initTeamMgmt(); if (scope === 'wobjects') initTeamMgmt(); else initTeamView();` — the `else` only gated the second `if`, so `baastegijad` always also ran `initTeamView()`. Fixed to proper `if / else if / else` chain.

### Scroll to Today in Month Views
Month task lists (worker rules view and admin team month view) now support scrolling to today's date group. Each date group rendered by `renderTeamTasks()` gets a `data-date` attribute. `scrollToToday(containerId)` finds the matching group (or nearest future date) and smooth-scrolls to it with a flash highlight. A 📅 button in the month navigation bar triggers it on demand.

### Code Consolidation

**Extracted `flashRow(row)`** — the scroll + highlight-flash + setTimeout pattern was duplicated in both the edit and insert paths of `saveTaskUI()`. Now a single 5-line helper.

**Extracted `coworkers_map()`** to helpers.php — the identical 4-line prepare/execute/group pattern for fetching coworkers was in both `api_tasks_today()` and `api_tasks_month()`. Now each calls one line.

**Merged state update loops** in `saveTaskUI()` — two identical `for (key in *.grouped)` blocks for `teamData` and `rulesData` → one `forEach()` over both.

**Removed dead code:** `refreshCurrentView()` (defined, never called). `$updated` counter in `api_tasks_batch()` replaced with `count($d['worker_ids'])`.

### Styleguide.html Merged
The separate `Styleguide.html` and `width-research.html` files have been merged into a single style guide. All sections auto-discover CSS variables. Buttons are derived from `--btn-{name}-bg` vars. Status classes are detected from `.status-N` stylesheet rules. Width research section with current/proposed toggle included inline.

### Files Changed
| File | Change |
|------|--------|
| `src/views.php` | `view_objloc_mgmt()` extracted. `view_team_mgmt()` stripped of details. Sub-nav updated. Scroll-to-today button. Minor cleanup (dead empty div, invalid textarea type attr). |
| `src/app.js` | New `initObjLocMgmt()` + `refreshObjLocMgmt()`. Stripped `initTeamMgmt()` + `refreshTeamMgmt()`. Fixed dispatch. `flashRow()` + `scrollToToday()` helpers. Scope-aware `saveDetails`/`deleteDetails`. |
| `src/helpers.php` | Added `coworkers_map()`. |
| `src/api_handlers.php` | Uses `coworkers_map()`. Shortened yesterday calc. Simplified batch counter. |
| `Styleguide.html` | Merged with width-research.html. Fully dynamic discovery of vars, buttons, status classes. |


## v2.7 — CSS Variable Consolidation & Test Coverage

### style.css — 4-Step Unified Scale
The CSS variable system has been simplified from 15 sizing variables (6 px spacers + 4 rem spacers + 3 border widths + 2 radii) down to 5 (4 spacers + 1 radius). Borders, gaps, margins, and paddings all draw from the same `--sp-*` scale:

| Var | Value | Role |
|-----|-------|------|
| `--sp-1` | 1px | Hairlines, thin borders, tight gaps |
| `--sp-2` | 2px | Emphasis borders, small element spacing |
| `--sp-3` | 0.2rem | Card accent borders, inner padding |
| `--sp-4` | 0.5rem | Body padding, section margins, card gaps |
| `--radius` | 3px | Single border-radius for all elements |

Removed: `--sp-5` (16px), `--sp-6` (24px), `--sp-11`–`--sp-18` (rem test set), `--bw-1`, `--bw-2`, `--bw-card`, `--rad-sm`, `--rad-md`. On a 600px mobile app, the old 24px gaps were excessive; `--sp-4` (8px) absorbs all medium-to-large spacing.

Form element overrides (`margin`, `padding`, `border` on `input, button, select, textarea`) reduced to `font-size` and `border-radius` only — browser defaults handle the rest.

Color fixes: `--btn-green-text` changed from `#886666` (brownish, wrong) to `#1a5632` (dark green). Hardcoded `#555` and `#95a5a6` replaced with `var(--c-muted)` and `var(--c-muted-light)`. Status `select[name="status"]` border-left syntax fixed (was missing width/style in per-status rules, now uses `border-left-color` on a base rule with full shorthand).

### init.js — Dead Code Removal
Removed duplicate `renderTasks()` (26 lines). This function was defined in both init.js and app.js. The init.js version used `task.status == 2` to disable buttons but never applied status color classes — a leftover from before inline styles were removed in v2.6.2. The app.js version (which calls `setStatusBtn()` for proper CSS class management) is the one actually used at runtime.

### tests/run_js.html — CSS Class Operation Coverage
Added 3 test groups (6, 7, 8) covering the JS functions that manage CSS classes for status display — these were introduced during inline style removal but had no test coverage:
- Group 6: `STATUS_BTN_CLASSES` array mapping (red/orange/green, 3 entries).
- Group 7: `setStatusBtn()` — verifies class toggling between btn-red/orange/green, disabled state on status 2, btn-inactive class addition/removal.
- Group 8: `syncStatusColor()` — verifies status select gets correct status-0/1/2 classes, old class removed on change.

### Styleguide.html (new, not deployed)
Developer reference page at project root. Loads `./src/style.css` and dynamically reads all `:root` variables via JS. Shows: spacing scale with px equivalents, border lines, margin/padding box diagrams (red solid = margin, blue dashed = padding), border×spacing matrix, font sizes, color swatches, buttons, status text vs button comparison, live card components. Auto-updates when CSS values change.

### Files Changed
| File | Change |
|------|--------|
| `src/style.css` | 15 sizing vars → 5. Browser defaults for form elements. Color fixes. 558 → 525 lines. |
| `src/init.js` | Removed dead `renderTasks()`. 79 → 53 lines. |
| `tests/run_js.html` | 3 new test groups (6–8) for CSS class operations. 204 → 265 lines. |
| `Styleguide.html` | NEW. Developer CSS reference. Not included in compile. |


## v2.6.2 — CSS Utility Classes & Inline Style Extraction

### style.css
- Added 21 utility classes

### views.php
- All 18 `style="..."` attributes replaced with the corresponding CSS classes above.
- `#reassign-wrap` and `#team-toolbar` initial `display:none` replaced with `.hidden` class.

### app.js
- All 10 `.style.*` direct property writes replaced with `classList.add/remove/toggle`.

## v2.6.1 — Surgical DOM State Sync Fix

### app.js
- `saveTaskUI()` now mutates the underlying JavaScript state (`teamData` and `rulesData`) in place via `Object.assign()`. Subsequent clicks on the same row load fresh data from JS memory — no network fetch needed.
- Fixed DOM querying for notes to correctly target the sibling `<tr>` the browser generates for nested table rows.

## v2.6 — Surgical DOM Updates & ETag Caching

### api.php
- Added `PRAGMA data_version` ETag caching to all GET endpoints. Returns `304 Not Modified` instantly when the DB is unchanged, bypassing SQL queries and JSON encoding entirely.

### app.js
- Rewrote `saveTaskUI()` to surgically update specific DOM text nodes on task edits instead of wiping and re-cloning the entire month container.
- Added smooth scroll + color-flash UX after save to guide the eye to the affected row.

### api_handlers.php
- `api_tasks_save()` now fetches and returns the task `id` via the UNIQUE constraint after an upsert, enabling the surgical DOM update in the client.

### tests/run.php
- Enforced strict JSON payload assertions in the Endpoint Integration group to guarantee `id` is always returned by `tasks/save`.

## v2.5 — Architecture Cleanup & Compiler Rewrite

### File Split: helpers.php → helpers.php + api_handlers.php
The 500-line helpers.php that mixed utilities with API handlers has been split into two files with clear responsibilities:
- `helpers.php` (113 lines) — pure utilities: json_exit, json_input, validate_task, upsert_task, insert_user, timeShift, statusLabels, btnLabels, app_log, known_titles. No API logic.
- `api_handlers.php` (396 lines, new) — all 18 api_*() handler functions. Each takes explicit parameters (PDO, data, uid), returns [code, body]. No globals, no $_SESSION. Called by api.php router AND tests directly.

index.php include order: i18n → helpers → database → api_handlers → api → views.

### Inline Closures Extracted from api.php
Six inline closures that were untestable inside match() have been extracted to named functions in api_handlers.php.

api.php is now a thin routing table. Three inline closures remain (logout one-liner, details GET glue, debug_log gate) — each trivial enough to not warrant extraction.

### $um_astmt Global Eliminated
The global prepared statement `$um_astmt` created in database.php has been replaced by `insert_user(PDO $pdo, string $username, string $password): int` in helpers.php. password_hash() is centralized inside the function. Callers can't forget it. api_users_create() signature simplified from ($d, $um_astmt) to ($pdo, $d).

### Session Security
- session_regenerate_id(true) added after successful login in api.php. Prevents session fixation.

### Old Password Verification
- users/password endpoint now requires old_password field. Verifies via password_verify() before accepting change.
- views.php: old_password input field added to view_user_info() form.
- app.js: changePassword() sends old_password.
- i18n.php: added old_password key (EN: 'Old Password', ET: 'Vana salasõna').

### SQLite UTC Date Fix
api_tasks_today() previously used SQLite's date('now', '-1 day') which operates in UTC. With Europe/Tallinn timezone (UTC+2/3), this caused a mismatch between midnight and 2-3am. Yesterday is now computed in PHP using DateTime, matching the timezone set in index.php.

### Month Navigation — View/Data Separation
view_rules() no longer calculates $prev_ym/$next_ym in PHP. The nav links render as empty anchors with ids (nav-prev-ym, nav-next-ym). JavaScript fills both href and text from API response data (rulesData.prev_ym/next_ym) in refreshRulesView(). Removes date() calls from views.php.

### ORG_NAME Constant
New define in index.php for organization branding. Used in HTML title: `<?= htmlspecialchars(ORG_NAME) ?> – <?= __('app_title') ?>`. Escaped for safety.

### $db_status Moved
The WAL checkpoint status block moved from database.php (ran on every request) into api_details_get() in api_handlers.php (runs only when admin loads management page).

### Compiler Rewrite
compile.php has been rewritten:
- REMOVED: js_compress() — was broken (ate ===, !==, ternaries, URLs in strings). Was already commented out.
- REMOVED: removecomments() running on final output — destroyed file markers.
- REMOVED: hex \x3f\x3e hack for closing PHP tags around debug.js.
- REMOVED: double file_put_contents write.
- ADDED: php_compress() — per-file php_strip_whitespace() via temp file (tokenizer-based, safe), then line split at top-level } boundaries via token_get_all(). Each function = one compact line. Inner braces (try/catch/foreach) stay on same line.
- ADDED: css_compress() — strip comments, collapse whitespace, split at } and ;
- JS files left as-is (gzip handles compression).
- Compression runs per-file BEFORE wrapping in /* included from */ markers — markers survive.
- Filename extraction regex fixed: include_once\s+['"]([^'"]+)['"] — extracts after include_once, not just first quoted string on line (was grabbing 'APP_DEBUG' from conditional debug.js line).
- Debug.js handling simplified: index.php uses standard conditional include_once on its own <?php ?> line. Compiler includes or skips based on APP_DEBUG flag. No PHP tag gymnastics.
- Per-file compression ratio shown in compile output.

### Test Suite Expanded
5 new test groups (19–23) covering all 8 extracted handler functions:
- 19: api_tasks_batch — validation, multi-worker insert, missing worker_ids
- 20: api_users_list, api_users_create, api_users_delete — CRUD, duplicates, admin protection
- 21: api_users_password — old password verify, wrong password, missing fields
- 22: api_details_save, api_details_delete — upsert, delete, not_found
- 23: api_archive_year — date guard
Total: 23 test groups, tests require both helpers.php and api_handlers.php.

### New Pattern
P11 Handler Testability — All api_*() functions live in api_handlers.php, take explicit parameters, return [code, body]. No globals inside handlers.

### Files Changed
| File | Change |
|------|--------|
| index.php | Added ORG_NAME, include api_handlers.php, clean debug.js conditional, htmlspecialchars(ORG_NAME) in title |
| src/helpers.php | Utilities only (113 lines). Added insert_user(), known_titles(). Removed all api_*() functions. |
| src/api_handlers.php | NEW. All 18 api_*() handler functions (396 lines). |
| src/api.php | Thin router. Inline closures replaced with handler calls. session_regenerate_id on login. |
| src/database.php | Removed $um_astmt. Removed insert_user (moved to helpers). Removed $db_status block (moved to api_details_get). Schema + seed only. |
| src/views.php | Removed $prev_ym/$next_ym date math. Nav links get JS ids. Added old_password field. |
| src/app.js | Fills month nav from API data. Sends old_password in changePassword(). |
| src/i18n.php | Added old_password key. |
| compile.php | Rewritten. Per-file compression, fixed regex, no hacks. |
| tests/run.php | 5 new test groups (19–23). Requires api_handlers.php. |


## v2.4 — Security & Validation Hardening

### CSRF Protection
All POST requests are now protected by a session-based CSRF token.
- `index.php` generates `$_SESSION['csrf_token']` via `bin2hex(random_bytes(16))` on session start.
- Token is injected directly as `const CSRF_TOKEN='...'` in the `<script>` block before all JS files load.
- `app.js` sends `X-CSRF-Token` header on every POST via `apiCall()`.
- `debug.js` also sends the header via `sendLog()`.
- `api.php` validates the header on every POST before any routing — covers login, debug_log, and all authenticated endpoints.

### Input Validation on All Write Endpoints
`validate_task()` is now called by every write endpoint:
- `tasks/save` — full validation (title + date + times + status).
- `tasks/batch` — full validation (already existed, unchanged).
- `tasks/status` — `empty($d['id'])` check + `validate_task($d, false, false)` for time/status fields.
- `tasks/delete` — `empty($d['id'])` check.

### Centralized Auth Context
Auth variables (`$logged_in`, `$uid`, `$u_name`, `$is_admin`, `$today_date`, `$current_time`, `$view`, `$scope`, `$ym`, `$current_month`) are now set once in `index.php` after the database include. Removed duplicate declarations from `api.php` and `views.php`.

### Shared `upsert_task()` Function
The `$upsert` prepared statement (previously created in `database.php` and passed as a parameter through the router) has been replaced by `upsert_task(PDO $pdo, int $user_id, ...)` in `helpers.php`. Both `api_tasks_save()` and the `tasks/batch` inline handler now call it directly. `api_tasks_save()` no longer takes a `PDOStatement` parameter.

### Shared `app_log()` Function
The inline `file_put_contents` + filesize cap logic from the `debug_log` endpoint has been extracted into `app_log(string $line)` in `helpers.php`. `JS_ERR_LOGFILE` is now defined once in `index.php` (moved from `api.php` inline). Available for any future server-side logging.

### Error Sanitization
All catch blocks that previously returned `$e->getMessage()` to the client now use `error_log()` for server-side logging and return a generic `'Database error'` message to the client. Prevents leaking SQLite internals (table structure, constraint names, file paths).

### Bug Fix: `api_tasks_save()` Broken Edit Path
The `if (!empty($d['id']))` branch that distinguishes update vs insert was missing — the `else` had no matching `if`. The dead `is_date_Ynd()` / `is_time_Hi()` calls (referencing non-existent functions) and `E_USER_ERRO` typo have been removed. The function now correctly routes to UPDATE for existing tasks and INSERT for new ones.

### Files Changed

| File | Change |
|------|--------|
| `index.php` | CSRF token generation, `JS_ERR_LOGFILE` define, centralized auth context block, injects `CSRF_TOKEN` as JS const |
| `src/helpers.php` | Added `upsert_task()`, `app_log()`. Fixed `api_tasks_save()`. Added validation to `api_tasks_status()`, `api_tasks_delete()`. Added rowCount guards. Sanitized error messages. |
| `src/api.php` | CSRF gate before routing. Removed duplicated auth vars. Updated `tasks/save` call (no `$upsert` param). `tasks/batch` uses `upsert_task()`. Simplified `debug_log` to use `app_log()`. Sanitized all inline catch blocks. |
| `src/database.php` | Removed `$upsert` prepared statement. |
| `src/views.php` | Removed duplicated auth variable declarations. |
| `src/init.js` | Removed `CSRF_TOKEN` (now injected by PHP in index.php). |
| `src/app.js` | `apiCall()` sends `X-CSRF-Token` header on POST. |
| `src/debug.js` | `sendLog()` sends `X-CSRF-Token` header using `CSRF_TOKEN` const. |

### Patterns Preserved
All 8 patterns (P1–P8) intact. Added P9 (Centralized Auth) and P10 (CSRF on All POSTs).

---

## v2.3

Added try/catch to all inline database mutations and rowCount() checks to verify actual modifications.

## v2.2 — Visual Rules Editor

### Two-Way Synced GUI for Rule Engine
Added a user-friendly Visual Editor for generating monthly rules, sitting alongside the legacy raw text editor. Managers can now create and edit rules using checkboxes for days/weeks and native HTML time pickers. 

**Architectural Win:** This was achieved with **zero changes to the backend or database**.
- The visual editor (cloned from `#visual-rule-template`) acts as a reactive frontend to the raw `<textarea>`.
- `syncVisualToText()` automatically translates UI changes (like checking "Mon", "Wed", "Fri") into the required text syntax (`135`) and updates the textarea.
- `syncTextToVisual()` parses the raw text and populates the UI, seamlessly handling legacy Estonian day characters (ETKN RLP) by mapping them to numeric equivalents in the UI.
- The `?api=rules/generate` endpoint still consumes the exact same raw text payload, completely unaware of the visual UI layer.

### Files Changed
- `src/views.php`: Added `#visual-rules-container` and `#visual-rule-template`.
- `src/app.js`: Added `syncTextToVisual()`, `syncVisualToText()`, and `addVisualRule()` with smooth scrolling (`scrollIntoView`). Event listeners hooked to `initRulesView()`.

## v2.1 — ISO Week Engine + Template Consistency

### Rule Engine Rewrite

The week-matching logic in `api_rules_generate()` has been replaced. The old system used `floor(($day-1)/7)` to split the month into 7-day chunks from day 1 — this did not align with real Monday–Sunday calendar weeks.

**Before (v2.0):** Weeks field uses 0–4 indices relative to month start. Legacy shorthands: `4`=all, `2`=bi-weekly, `1`=once. Three constants (`ONCE_A_MONTH_WEEK_IDX`, `BI_WEEKLY_WEEK1`, `BI_WEEKLY_WEEK2`) configured the shorthands.

**After (v2.1):** Weeks field uses 1–4 as relative indices of *full ISO weeks* within the month. A full week is one where all 7 days (Mon–Sun) fall within the target month. Partial weeks at month boundaries are skipped by the generator — the manager or worker adds those days manually via the schedule editor. The three legacy constants are removed from `index.php`.

| Old | New | Meaning |
|-----|-----|---------|
| `01234` or `4` | `1234` | Every full week |
| `13` or `2` | `13` | Bi-weekly (1st + 3rd full week) |
| `2` or `1` | `2` | Second full week only |

### ISO Week Headers in Month Views

Month views (rules + team) now display ISO week number separators (`Wk 10`, `Näd 10`) above each week's date groups. This helps the manager write rules — the week numbers are visible right next to the tasks they generate. Implemented as a `div.week-header` inserted by `renderTeamTasks()` when the ISO week changes between date groups. New `isoWeek()` JS helper computes ISO 8601 week from a date string.

### Template-Based Rendering (Print + Details)

Two remaining JS functions that built HTML via string concatenation have been converted to `<template>` cloning, matching the pattern used by all other views.

| Function | Before | After |
|----------|--------|-------|
| `initPrintView()` | Template literal HTML with `dCt`/`dCs` shorthand variables | Clones `#print-page-template` + `#print-task-template` |
| `renderDetailCards()` | `h()` helper string builder with `innerHTML` | Clones `#detail-card-template`, uses `.textContent` |

New templates in `view_templates()`: `#print-page-template`, `#print-task-template`, `#detail-card-template`.

Signature line text (`print_signature_line`) moved from JS constant to PHP `__()` in template — `js_print_sig` removed from `init.js`.

### Files Changed

| File | Change |
|------|--------|
| `src/helpers.php` | `api_rules_generate()`: ISO full-week engine replaces legacy week indices |
| `src/views.php` | 3 new `<template>` elements: print-page, print-task, detail-card |
| `src/app.js` | `isoWeek()` helper, week headers in `renderTeamTasks()`, template-based `initPrintView()` + `renderDetailCards()` |
| `src/init.js` | Added `js_lbl_week`, removed `js_print_sig` |
| `src/i18n.php` | Added `week` key (`Wk`/`Näd`), updated `ph_rules` placeholder to show full-week format |
| `src/style.css` | Added `.week-header` rule |
| `index.php` | Removed `ONCE_A_MONTH_WEEK_IDX`, `BI_WEEKLY_WEEK1`, `BI_WEEKLY_WEEK2` constants |

### Patterns Preserved
All 7 patterns (P1–P7) intact. The UNIQUE constraint, INSERT OR IGNORE, CASCADE, session auth, prepared statements, WAL mode — nothing changed in the data layer. The rule engine still uses the same Smart Merge + True Sync approach.

---

## v2.0 — REST API Layer

### Architecture Change
Single data path: **JS ↔ REST API (`?api=`) ↔ SQLite**

Before: PHP generated HTML with embedded `<script>const data = <?= json_encode(...) ?></script>`,
and JS separately called `?action=` endpoints for writes. Two code paths, two data formats.

After: All reads AND writes go through `?api=endpoint`. Views are HTML shells. JS fetches + renders.

### Files Changed

| File | Before | After | Change |
|------|--------|-------|--------|
| `src/api.php` | — | **NEW** | Unified REST endpoint handler |
| `src/actions.php` | 8.5K | **DELETED** | Merged into api.php |
| `src/viewdata.php` | 9.5K | **DELETED** | Merged into api.php |
| `src/views.php` | 15K | ~7K | Pure HTML shell, no data injection |
| `src/app.js` | 9.5K | ~9K | Fetch-based init, no PHP globals |
| `src/init.js` | 2K | 2K | Added i18n constants for client rendering |
| `src/helpers.php` | — | — | Unchanged |
| `src/database.php` | — | — | Unchanged |
| `src/i18n.php` | — | — | Unchanged |
| `src/style.css` | — | — | Unchanged |
| `index.php` | 3K | 2.5K | Routes `?api=` to api.php, simplified |
| `tests/run.php` | 110+ | 110+ | Updated: API data contract tests replace render helper tests |

### Eliminated Code
- `viewdata.php` (entire file — all `data_*()` functions)
- `render_worker_options()`, `render_detail_card()`, `render_detail_option()`, `render_print_task()`
- `<script>const tasks_for_js = <?= json_encode(...) ?></script>` pattern (×4 instances)
- Form POST handlers (`$_POST['save_rules']`, `$_POST['change_pwd']`, `$_POST['usermanager']`)
- `$view_data = match($view)` dispatch in viewdata.php
- `$body_attributes` global for print view

### Patterns Preserved
All 7 patterns (P1–P7) intact. UNIQUE constraint, INSERT OR IGNORE, CASCADE, session auth,
prepared statements, WAL mode — nothing changed in the data layer.

### Breaking Changes
- Login is now AJAX (`POST ?api=login` with JSON body), not form POST
- Logout is now AJAX (`POST ?api=logout`), not `?logout` GET
- Password change is AJAX, not form POST
- User management is AJAX, not form POST
- Rules save+generate is AJAX, not form POST
- Print view renders client-side via JS (fetches `?api=tasks/print`)
- Worker selects populated by JS (no more PHP `render_worker_options()`)
- Detail cards rendered by JS (no more PHP `render_detail_card()`)
- XSS escaping responsibility shifted to client (`escHtml()` in app.js)
