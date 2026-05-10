<?php 
/** 
 * DATABASE INIT KEY DESIGN DECISIONS:
 *	• UNIQUE(user_id, task_date, title) allows `INSERT OR IGNORE` so the rule
 *	  engine never overwrites tasks that a worker has already started or edited.
 *	• ON DELETE CASCADE on user_id means deleting a user automatically removes
 *	  all their tasks and rules — no orphan cleanup needed.
 *	• An index on task_date speeds up the most common query (today's tasks).
 *	• PRAGMA user_version bypasses schema checks on subsequent loads to drop init overhead.
 */
if (defined('APP_DEBUG') && APP_DEBUG) $time_dbinit = hrtime(true);

$pdo = new PDO('sqlite:' . DB_FILE, null, null, [
	PDO::ATTR_ERRMODE			=>PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

// Group per-connection PRAGMAs to minimize SQLite parser roundtrips.
// foreign_keys is REQUIRED every connection.
$pdo->exec("
	PRAGMA foreign_keys = ON;
	PRAGMA synchronous = NORMAL;
	PRAGMA temp_store = MEMORY;
	PRAGMA cache_size = -20000;
");

// WAL mode is persistent — only set once
if ($pdo->query("PRAGMA journal_mode")->fetchColumn() !== 'wal') {
	$pdo->exec("PRAGMA journal_mode=WAL;");
}

function ensure_tasks_table(PDO $pdo): void {
	$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
		id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
		task_date TEXT, title TEXT, start_time TEXT, end_time TEXT, status INTEGER DEFAULT 0, source TEXT NOT NULL DEFAULT 'manual', notes TEXT DEFAULT '',
		UNIQUE(user_id, task_date, title)
	)");
	$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks(task_date)");
	$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_date_title ON tasks(task_date, title, user_id)");
}


// Skip parsing/evaluating CREATE TABLE statements if schema is already up to date
if ((int)$pdo->query("PRAGMA user_version")->fetchColumn() < 1) {
	$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, real_name TEXT, contact TEXT, force_password_change INTEGER DEFAULT 0)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS user_rules (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, rules_text TEXT)");

	ensure_tasks_table($pdo);

	$pdo->exec("CREATE TABLE IF NOT EXISTS rl (ip TEXT PRIMARY KEY, fails INTEGER, expire INTEGER)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS task_details (title TEXT PRIMARY KEY NOT NULL, address TEXT, description TEXT, related_person TEXT, checklist TEXT)");


	$pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, val TEXT)");

	if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
		insert_user($pdo, USER1, USER1, USER1, USER1, 1);
	}

	// Lock schema version to skip these checks on next connection
	$pdo->exec("PRAGMA user_version = 1");
}

$cfg = array_column($pdo->query("SELECT key,val FROM config ORDER BY key")->fetchAll(), 'val', 'key');

if (defined('APP_DEBUG') && APP_DEBUG) $time_db_ms = round((hrtime(true) - $time_dbinit) / 1e6, 2);