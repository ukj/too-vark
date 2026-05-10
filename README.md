# ![icon-128.png](icon-128.png) Töö Värk — Lightweight Work Scheduler

A zero-dependency, single-file PHP + SQLite work scheduling application for small teams. Define repeating schedules with a visual rule editor, track task progress in real time, manage workers — all from one PHP file and one SQLite database.

## Features

- Visual rule editor with checkbox weekdays/weeks and time pickers
- Monthly schedule generation from rules, with smart merge (never overwrites started tasks)
- Real-time task tracking: Pending → In Progress → Done
- Team overview with scroll-to-today, ad-hoc task assignment, batch print, CSV export
- Location/object details management (address, contact, description) in dedicated tab
- Bi-lingual UI (Estonian / English), PWA support
- Single-file deployment via `compile.php`

#### Screenshots

[img_admin_audits.jpeg](img_admin_audits.jpeg)  
[img_admin_month.jpeg](img_admin_month.jpeg)  
[img_team_objects.jpeg](img_team_objects.jpeg)  
[img_workers_month.jpeg](img_workers_month.jpeg)

## Quick Start

```bash
git clone https://github.com/ukj/too-vark.git
cd too-vark

# Development
php -S localhost:8000

# Production — compile to single file, deploy with app.sqlite
php compile.php
```

Default login: `admin` : `admin` — change immediately in production!

## Requirements

- PHP 8.3+ with `pdo_sqlite`
- Any web server (Apache, Nginx, or PHP built-in)

## Project Structure

```
index.php              Entry point
compile.php            Build tool → index_release.php  (GenDemoDB link → tests/demo-db.php)
app.sqlite             SQLite database (auto-created)
src/                   Source files (PHP, JS, CSS)
plugins/               Plugins
tests/run.php          Test suite, demo database seed (40 workers + admin, org config, rules, 3-year task history)
Styleguide.html        CSS variable reference (dev only)
```

## Testing

```bash
php tests/run.php
```

Self-contained, in-memory SQLite, no external test framework. See [TESTING.md](TESTING.md).

To populate a local database with realistic demo data (15 workers, org config, repeating rules, 3-year task history with ~50k tasks):

```bash
php tests/demo-db.php   # writes app-demo.sqlite to project root
YEARS=5 php tests/demo-db.php   # 5-year dataset for stress testing
```

The `compile.php` admin page includes a **GenDemoDB** link that runs this script in-browser during development. Rename `app-demo.sqlite` to `app.sqlite` or DB_FILE in `index.php`. admin:admin 
User Name => usern:usern123 .

## Documentation

- **[TooVark-Specification.md](TooVark-Specification.md)** — Full specification: architecture, API endpoints, data model, security, rule format
- **[CHANGELOG.md](CHANGELOG.md)** — Version history
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — Contribution guidelines

## Configuration

Constants at the top of `index.php`: `USER1` (admin username), `USER1_INITIAL_PASS` (initial password), `ORG_NAME` (page title), `APP_DEBUG`. Timezone defaults to `Europe/Tallinn`.
App icon.png

## Security

Passwords use bcrypt. Admin account requires password change on first login. Login rate-limited to 5 attempts per IP (5-minute window). All POSTs require CSRF token. All SQL uses prepared statements. Database errors are logged server-side, never exposed to clients. Put database to safe location outside web dir., block direct access to `.sqlite` files on your web server.

## Licence

[MIT](LICENSE)
