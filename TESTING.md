# Testing
<!-- FORMAT: Quick Start (CLI + browser), Requirements, What Gets Tested (numbered table: #, Category bold, What it covers), Architecture section, Coverage Map. Update test count and table rows when groups are added/removed. -->

## Quick Start

The test suite can be run either from the command line or directly in your web browser. It automatically detects the environment and formats the output accordingly.

**Option A: Command Line (CLI)**
```bash
php tests/run.php             # run all tests
php tests/run.php --verbose   # show every assertion (pass + fail)
```
*(Exit code `0` = all passed, `1` = failures)*

**Option B: Web Browser**
1. Start your local server (e.g., `php -S localhost:8000`).
2. Navigate to `http://localhost:8000/tests/run.php`.
3. Click "Run in Verbose Mode" (or add `?verbose=1` to the URL) to see all passing assertions.

## Requirements

- PHP 8.3+ with `pdo_sqlite` (same as the application itself)
- No additional dependencies — the test runner is completely self-contained

## What Gets Tested (18 categories, 120+ assertions)

| # | Category | What it covers |
|---|----------|----------------|
| 1 | **Schema & Constraints** | All 4 tables exist, indexes exist, UNIQUE constraint prevents duplicates, foreign keys enforced |
| 2 | **Smart Merge** | `INSERT OR IGNORE` protects manually started tasks from being overwritten by the rule engine |
| 3 | **Upsert** | `upsert_task()` correctly updates times/status without creating duplicate rows |
| 4 | **True Sync 2.0** | Auto-cleanup removes `status=0` tasks before generating new rules. Verifies that `status=1` and `status=2` tasks are strictly preserved during generation. |
| 5 | **Status Progression** | 0→1→2 transitions, cap at 2, actual times recorded, user isolation, rowCount guard returns 404 for wrong user |
| 6 | **Cascade Delete** | Deleting a user removes their tasks + rules, other users unaffected |
| 7 | **Task Details** | Location detail insert, upsert, and delete |
| 8 | **Rule Engine** | **(v2.1 ISO Engine)** Estonian day letters, numeric day codes, ISO full-week relative indices (`1234` for all full weeks, `2` for 2nd full week, `13` for bi-weekly), auto-cleanup handling |
| 9 | **timeShift()** | Empty inputs, no-shift when start equals now, duration preservation |
| 10 | **i18n** | English and Estonian translations, fallback for unknown keys, all keys have both languages |
| 11 | **api_tasks_today()** | Today-view filtering (yesterday's done excluded, unfinished included), task JSON shape validation (all required keys present), XSS escaping via raw data return |
| 12 | **Authentication** | bcrypt hashing, password verification, wrong/empty password rejection, duplicate username protection |
| 13 | **Yearly Archival** | Table rename, data preservation in archive, fresh table creation |
| 14 | **Delete Protection** | Completed tasks (status=2) cannot be deleted via `api_tasks_delete()`, open tasks can, wrong user blocked by rowCount guard |
| 15 | **Edge Cases** | FK violation via `upsert_task()`, empty titles, Unicode (Estonian characters), explicit `NOT NULL` rejection for Primary Keys (SQLite legacy quirk) |
| 16 | **Endpoint Integration** | End-to-end testing of real REST API handlers (`api_tasks_save`, `api_tasks_month`, `api_tasks_team`, `api_tasks_print`, etc.), Admin vs non-admin authorization blocks, upsert-on-duplicate behaviour |
| 17 | **Input Validation** | `is_valid_time()`, `is_valid_date()`, `validate_task()` with all field combinations, `api_tasks_save()` rejects bad input |
| 18 | **Shared Functions & Guards** | `upsert_task()` insert/update/FK-violation, `api_tasks_status()` validation (missing id, bad time) + rowCount guard, `api_tasks_delete()` validation (missing id) + rowCount guard, `app_log()` exists |

## How It Works

The test suite creates a fresh **in-memory SQLite database** for each test group. This means:

- Tests are fast (no disk I/O)
- Tests are completely isolated (each group starts with a clean database)
- No test data is left behind
- No risk of corrupting your real `app.sqlite`

The core logic (like the rule engine and shared functions from `helpers.php`) is loaded directly into the test suite. This means the tests run the exact same handler functions that the REST API endpoints use.

## Architecture Notes

The test runner follows the project's zero-dependency philosophy. There is no PHPUnit, no Composer, no framework. The assertion helpers (`assert_true`, `assert_eq`, `assert_contains`, `assert_count`, `assert_throws`) are defined at the top of the test file.

Output formatting is adaptive:
- **CLI:** Uses ANSI colour codes (`\033[32m`) for readability in terminal windows.
- **Browser:** Outputs a clean, dark-themed HTML/CSS interface matching the JS test suite style.

## Adding New Tests

Add a new block inside `tests/run.php`:

```php
test_group("19. My New Feature");
{
    [$pdo, $um_astmt] = create_test_db();
    [$admin_id, $mari_id] = seed_users($pdo, $um_astmt);

    // Seed tasks using the shared function
    upsert_task($pdo, $mari_id, 'TestJob', '2025-06-15', '08:00', '16:00', 0, '');

    // Your test logic here
    assert_eq($expected, $actual, "Description of what you're testing");
}
```

Each `test_group` block should call `create_test_db()` so it provisions its own isolated database schema. Use `upsert_task()` to seed tasks — this is the same shared function the production code uses.