# TOOAJAD PROJECT CONTEXT
<!-- LLM working document. Human spec: TooVark-Specification.md -->
<!-- FORMAT: Sections: CONSTRAINT, STACK, APP, FILES, SCHEMA, AUTH CONTEXT, PATTERNS (P1..P14), RULE FORMAT, COMPILE, STYLE, PROHIBIT, STATE, ROLE. Terse. No narrative. -->

Today is 2026-05-10

## CONSTRAINT AND STACK
PHP 8.3+ | SQLite3 PDO | Vanilla JS ES2017+ | CSS mobile-first 600px monospace
Zero dependencies.
Single-file deploy: compile.php $\rightarrow$ index_release.php + app.sqlite.
SQLite constraints handle business logic. Don't duplicate in PHP.
strict_types=1. Arrow functions, match, array destructuring, never return type.

## APP
Work scheduler, ~40 workers. Manager defines rules via Visual UI $\rightarrow$ generates monthly tasks.
Workers: see today's/yesterday's tasks, progress 0$\rightarrow$1$\rightarrow$2.
Admin: team overview, assign tasks, print, user/location/config mgmt, CSV.
i18n: Estonian(et)/English(en), session-based. `__('key')` $\rightarrow$ `$i18n['key'][$langi]`.

## FILES
```
index.php	entry: defines(ORG_NAME,DB_FILE,USER1,APP_DEBUG)$\rightarrow$session$\rightarrow$CSRF$\rightarrow$auth context$\rightarrow$HTML shell
compile.php	merges src/ + plugins/ $\rightarrow$ index_release.php. Per-file php_strip_whitespace + css_compress.
src/i18n.php	$i18n=['key'=>['EN','ET']], $i18ni=['en'=>0,'et'=>1]
src/helpers.php	utilities: get_ip(), rl_ok/rl_fail/rl_clear(), location_exit(), json_exit(), json_input(), validate_task(), timeShift(), upsert_task(), insert_user(), known_titles(), db_try($tag,$fn,$unique_msg), workers_list(), users_full_list()
src/plugins.php	plugin loader: glob plugins/*.php, registers routes/views/nav/schema/etag/write lists.
src/api_handlers.php  core api_*() handler functions. [code,body] returns. Called by api.php and tests/run.php.
src/database.php	PDO+PRAGMA+CREATE TABLE (users, rules, tasks, task_details, config, rl) + admin seed.
src/api.php	REST router: CSRF gate $\rightarrow$ pre-auth $\rightarrow$ auth gate $\rightarrow$ admin gate ($_admin_routes) $\rightarrow$ ETag Cache $\rightarrow$ match dispatch $\rightarrow$ plugin fallback.
src/views.php	HTML shell only. NO data, NO $pdo, NO json_encode. Plugin nav/view merge. Uses centralized auth vars.
src/init.js	CURRENT_VIEW/SCOPE/YM, escHtml(), h(), optionsHtml(), lazyRender(). No rendering logic.
src/app.js	apiCall(CSRF header), init*() per view, fetch $\rightarrow$ template render, surgical DOM updates.
src/debug.js	JS error/promise logging via app_log(). Included only if APP_DEBUG=true.
src/style.css	mobile-first, print @media, utility classes. 4-step spacing (--sp-1..4), single --radius.
plugins/debug.php	PLUGIN: debug_log, timer_log, app_log. Removable without breaking core.
plugins/config.php  PLUGIN: config CRUD, archive_year, api_can_archive. Removable without breaking core.
plugins/audit*	PLUGIN: Removable without breaking core.
tests/run.php	23 test groups, in-memory SQLite, zero deps.
tests/run_js.html	8 test groups: isoWeek, escHtml, visual rules sync, setStatusBtn, syncStatusColor.
TooVark-Specification.md	Human-facing spec.
Styleguide.html	Dev reference for :root vars.
```

## SCHEMA
```sql
PRAGMA foreign_keys=ON;

users(id INTEGER PK, username TEXT UNIQUE, password TEXT, real_name TEXT, contact TEXT, force_password_change INTEGER DEFAULT 0)
user_rules(user_id INTEGER PK REFERENCES users(id) ON DELETE CASCADE, rules_text TEXT)
tasks(id INTEGER PK, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  task_date TEXT, title TEXT, start_time TEXT, end_time TEXT, status INTEGER DEFAULT 0, notes TEXT DEFAULT '',
  source TEXT DEFAULT 'manual',
  UNIQUE(user_id,task_date,title))
CREATE INDEX idx_tasks_date ON tasks(task_date)
task_details (title TEXT PRIMARY KEY NOT NULL, address TEXT, description TEXT, related_person TEXT, checklist TEXT)
config(key TEXT PK, val TEXT)
rl(ip TEXT PK, fails INTEGER, expire INTEGER)
```

## AUTH CONTEXT
**Centralized auth variables** — Set once in `index.php`:
`$logged_in, $uid, $u_name, $is_admin, $today_date, $current_time, $view, $scope, $ym, $current_month`. 
Refer to **P9/P10** for security protocol.

## PATTERNS — DO NOT BREAK

**P1 Smart Merge** — Use `INSERT OR IGNORE` + `UNIQUE(user_id,task_date,title)`. Existing/started tasks are never overwritten.

**P2 True Sync** — Rules generation auto-deletes `status=0 AND source='rule'` tasks for the target month *before* generating new ones. `source='manual'` tasks are untouched.

**P3 Status** — `min(status+1, 2)`. Worker updates own only (`AND user_id=?`). Delete guard: `AND status<2`.

**P4 Cascade** — `ON DELETE CASCADE` on both FKs. Admin (USER1, id=1) protected: `if($id<=1)`.

**P5 REST API** — Client: `fetch('?api=endpoint',{method:'POST',body:JSON.stringify(d),headers:{'X-CSRF-Token':CSRF_TOKEN}}).then(r=>r.json())`. Server: `match($api){...}; json_exit([...]);`.

**P6 Routing** — `?api=X` $\rightarrow$ REST JSON. `?view=X` $\rightarrow$ HTML shell. `?scope=X` $\rightarrow$ team sub-tab. `?ym=YYYY-MM` $\rightarrow$ month context.

**P7 View/Data Separation** — `api_handlers.php` (SQL/JSON) $\rightarrow$ `api.php` (Router) $\rightarrow$ Client $\rightarrow$ `app.js` (Render). `views.php` is an empty shell.

**P8 Visual Rules Editor Sync** — Two-way JS sync (`syncTextToVisual` / `syncVisualToText`) on `#rules-textarea`. The JSON in the textarea is the single source of truth.

**P9 Centralized Auth** — Use variables set in `index.php`. Never re-read `$_SESSION` in downstream files.

**P10 CSRF on All POSTs** — Every POST to `?api=` must include `X-CSRF-Token` header. Validated in `api.php`.

**P11 Handler Testability** — `api_*()` functions in `api_handlers.php` take explicit parameters (PDO, data, uid) and return `[int $code, array $body]`. No globals inside handlers. **Exception:** `$cfg` is a read-only boot constant loaded once in `database.php` (line 64), never mutated by handlers. Three handlers use `global $cfg`: `api_tasks_print`, `api_details_get`, `plugin_config` GET. Tests set `$cfg` in setup before calling these handlers.

**P12 Surgical DOM & State Updates** — `saveTaskUI()` updates specific DOM nodes and mutates JS state (`teamData`/`rulesData`) via `Object.assign()`. Avoid heavy DOM thrashing.

**P13 ETag Caching** — `api.php` uses DB/WAL file stats for `304 Not Modified` responses on GET.

**P14 Lazy Loading** — Use `IntersectionObserver` to fill `data-lazy="1"` shells (month tasks, user cards, detail cards).

**P15 Plugin Contract** — Plugins return `['id'=>..., 'routes'=>..., 'views'=>..., 'nav'=>..., 'schema'=>..., 'schema_version'=>...]`. Handlers follow P11: `(PDO,$d,$uid,$is_admin,$dc,$method) → [code,body]`. Core guards plugin functions with `function_exists()`.

## PLUGINS
```
plugins/            drop-in directory, scanned by src/plugins.php via glob()
  debug.php         debug_log + timer_log + app_log (removable)
  config.php        config CRUD + archive_year (removable)
  *.js              auto-included after app.js
  *.css             auto-included after style.css
```
Plugin PHP file returns array: `id` (required), `routes`, `views`, `nav`, `admin_only`, `schema`, `schema_version`, `etag_routes`, `write_routes`.
Schema versions tracked in `config` table as `plugin_schema_{id}`.
`compile.php` inlines plugin files into single-file output — no `plugins/` dir needed in production.

## RULE FORMAT
```json
[{"title":"Cleaning","days":"ER","weeks":"1234","start":"08:00","end":"16:00"}]
```
`days`: 1-7 or ETKN RLP. `weeks`: 1-4 (ISO full-weeks in month).

## COMPILE
`compile.php` merges `src/` + `plugins/` $\rightarrow$ `index_release.php`. Uses tokenizer-safe line splitting. Plugin PHP inlined after `plugins.php` marker, JS appended, CSS injected before `</style>`.

## STYLE
Tabs only. CSS 4-step spacing (`--sp-1`..`4`) + single `--radius`. JS uses compiled string templates. No inline styles/colors.

## PROHIBIT
- External dependencies or build tools
- Changing `UNIQUE(user_id,task_date,title)`
- Plain `INSERT` in rule engine (must be `INSERT OR IGNORE`)
- Forgetting `PRAGMA foreign_keys=ON`
- HTML in API handlers (must `json_exit+exit`)
- SQL/$pdo in `view_*()` functions (must be HTML shell only)
- `<script>const X = <?= json_encode(...) ?></script>` (data via API)
- String concat in SQL (must use prepared stmts)
- Exposing `$e->getMessage()` to client
- Re-declaring auth variables outside `index.php`
- POST requests without CSRF token validation
- Passing prepared statements as function parameters
- `return;` at file scope in any `src/*.php` file
- Editing `index_release.php` (edit `src/`, run `compile.php`)
- Handler logic in `api.php`
- Schema or seed logic outside `database.php`
- Date calculations in `views.php`
- Inline styles or hardcoded px/rem spacing/colors

## STATE v3.2.0
Working: auth, CSRF, login rate limiting, input validation, ETag caching, Surgical DOM updates, lazy loading, compiled string templates, rule engine (ISO full-week), multi-month navigation, admin/user CRUD, config/location mgmt, print/CSV, i18n, compile, performance instrumentation, **plugin system (routes/views/nav/schema)**, **audit plugin (checklists, access control, report)**. High concurrency (WAL). REST API active. All handlers testable. No inline styles.

## ROLE
You modify this codebase. Follow constraints above. Read `src/` files before editing. Use `str_replace`, not full rewrites.

## CHANGELOG FORMAT
<!-- FORMAT: ## vX.Y.Z — Short Title, then ### Category heading, then prose or - bullet items. Newest version first. -->

## SESSION END
When asked for a session summary, produce draft CHANGELOG entry in the project's format
```
Session: YYYY-MM-DD
Version: vX.Y.Z → vX.Y.Z (if changed)
Changed: one-line description
Files: list of modified files
Details:
- what changed and why, one bullet per logical change
Docs to update: which docs need edits (CHANGELOG, Spec, TESTING, README)
```
