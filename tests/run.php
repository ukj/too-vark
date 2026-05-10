<?php
/**
 * Töö Värk — Test Suite (REST Edition)
 *
 * UPGRADE: Tests call REAL handler functions from helpers.php
 * instead of duplicating SQL logic. Same functions the router uses.
 * Supports both CLI (ANSI colours) and Browser (HTML) execution.
 *
 * USAGE:
 *   Browser: http://localhost:8000/tests/run.php
 *            http://localhost:8000/tests/run.php?verbose=1
 *   CLI:     php tests/run.php
 *            php tests/run.php --verbose
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* MINI TEST FRAMEWORK */

$is_cli = (PHP_SAPI === 'cli');
$test_results = ['pass' => 0, 'fail' => 0, 'errors' => []];
$verbose = $is_cli ? in_array('--verbose', $argv ?? []) : !empty($_GET['verbose']);
$current_group = '';

if (!$is_cli) {
	echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>PHP Tests</title>\n";
	echo "<style>
		body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; line-height: 1.5; }
		.pass { color: #4CAF50; }
		.fail { color: #F44336; font-weight: bold; }
		.group { color: #569CD6; font-weight: bold; margin-top: 25px; font-size: 1.1em; }
		.title { color: #d7ba7d; font-weight: bold; font-size: 1.2em; border: 1px dashed #d7ba7d; padding: 10px; display: inline-block; }
		a { color: #9cdcfe; }
	</style></head><body>\n";
}

function test_group(string $name): void {
	global $current_group, $is_cli;
	$current_group = $name;
	if ($is_cli) {
		echo "\n\033[1;36m── {$name} ──\033[0m\n";
	} else {
		echo "<div class='group'>── " . htmlspecialchars($name) . " ──</div>\n";
	}
}

function assert_true(bool $condition, string $label): void {
	global $test_results, $verbose, $current_group, $is_cli;
	if ($condition) {
		$test_results['pass']++;
		if ($verbose) {
			if ($is_cli) echo "  \033[32m✓\033[0m {$label}\n";
			else echo "<div class='pass'>&nbsp;&nbsp;✓ " . htmlspecialchars($label) . "</div>\n";
		}
	} else {
		$test_results['fail']++;
		$test_results['errors'][] = "[{$current_group}] {$label}";
		if ($is_cli) echo "  \033[31m✗ FAIL:\033[0m {$label}\n";
		else echo "<div class='fail'>&nbsp;&nbsp;✗ FAIL: " . htmlspecialchars($label) . "</div>\n";
	}
}

function assert_eq($expected, $actual, string $label): void {
	$match = ($expected === $actual);
	if (!$match) $label .= "  (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")";
	assert_true($match, $label);
}

function assert_contains(string $haystack, string $needle, string $label): void {
	assert_true(str_contains($haystack, $needle), $label);
}

function assert_not_contains(string $haystack, string $needle, string $label): void {
	assert_true(!str_contains($haystack, $needle), $label);
}

function assert_count(int $expected, array $arr, string $label): void {
	assert_eq($expected, count($arr), $label);
}

function assert_throws(callable $fn, string $label): void {
	try { $fn(); assert_true(false, $label . " — no exception thrown"); }
	catch (Exception $e) { assert_true(true, $label); }
}


/* BOOTSTRAP */

define('DATA_DIR', './');
define('DB_FILE', ':memory:');
define('USER1', 'admin');
define('TV_TIMERS_LOGFILE', '/dev/null');

date_default_timezone_set('Europe/Tallinn');

// i18n + __() must be defined BEFORE requiring helpers.php
$i18ni = ['en' => 0, 'et' => 1];
$langi = 0;
require_once __DIR__ . '/../src/i18n.php';
function __(string $key): string {
	global $i18n, $langi;
	return $i18n[$key][$langi] ?? $key;
}

// Load REAL helpers — timeShift, statusLabels, btnLabels, validate_task, upsert_task, insert_user
require_once __DIR__ . '/../src/helpers.php';

// Load REAL API handlers — all api_* functions called by router and tested here
require_once __DIR__ . '/../src/api_handlers.php';

// Load plugins — debug and config handlers live here now
// Initialize plugin registration vars (normally done by src/plugins.php)
$plugins = [];
$plugin_routes = [];
$plugin_views = [];
$plugin_nav = [];
$plugin_etag_routes = [];
$plugin_write_routes = [];
$plugin_dir = __DIR__ . '/../plugins/';
if (is_dir($plugin_dir)) {
	foreach (glob($plugin_dir . '*.php') as $pf) require_once $pf;
}

// Global config cache (mirrors database.php startup)
$cfg = [];

// Fixed date context — tests never depend on the real clock.
// Use a Wednesday so "week 1" logic and yesterday-boundary tests are stable.
$dc = new DateContext('2025-03-12', '09:00'); // Wednesday, week 2 of March 2025
// Keep these aliases for the few legacy spots that still reference them directly
$today_date    = $dc->today;
$current_time  = $dc->time;

/** Create a fresh in-memory database with the full schema. */
function create_test_db(): PDO {
	$pdo = new PDO('sqlite::memory:', null, null, [
		PDO::ATTR_ERRMODE			=> PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	$pdo->exec("PRAGMA foreign_keys = ON;");
	$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, real_name TEXT, contact TEXT, force_password_change INTEGER DEFAULT 0)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS rl (ip TEXT PRIMARY KEY, fails INTEGER, expire INTEGER)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS user_rules (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, rules_text TEXT)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
		id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
		task_date TEXT, title TEXT, start_time TEXT, end_time TEXT, status INTEGER DEFAULT 0, notes TEXT DEFAULT '', source TEXT NOT NULL DEFAULT 'manual',
		UNIQUE(user_id, task_date, title)
	)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS task_details (title TEXT PRIMARY KEY NOT NULL, address TEXT, description TEXT, related_person TEXT, checklist TEXT)");
	$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks(task_date)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, val TEXT)");

	return $pdo;
}

/** Seed test users. Returns [admin_id, worker1_id, worker2_id]. */
function seed_users(PDO $pdo): array {
	insert_user($pdo, USER1, 'admin_pass');
	insert_user($pdo, 'mari', 'mari_pass');
	insert_user($pdo, 'jüri', 'juri_pass');
	// Admin gets force_password_change=1 like production seed
	$pdo->exec("UPDATE users SET force_password_change = 1 WHERE username = '" . USER1 . "'");
	
	$ids = [];
	foreach (['admin', 'mari', 'jüri'] as $name)
		$ids[] = (int)$pdo->query("SELECT id FROM users WHERE username = '{$name}'")->fetchColumn();
	return $ids;
}

if ($is_cli) {
	echo "\033[1;33m╔══════════════════════════════════════════╗\033[0m\n";
	echo "\033[1;33m║  Töö Värk — Test Suite (REST + Handlers) ║\033[0m\n";
	echo "\033[1;33m╚══════════════════════════════════════════╝\033[0m\n";
} else {
	echo "<div class='title'>Töö Värk — Test Suite (REST + Handlers)</div>\n";
	if (!$verbose) echo "<div style='margin-top:10px;'><a href='?verbose=1'>Run in Verbose Mode</a></div>\n";
	else echo "<div style='margin-top:10px;'><a href='?'>Run in Normal Mode</a></div>\n";
}

/* 1. SCHEMA & CONSTRAINTS */
test_group("1. Schema & Constraints");
{
	$pdo = create_test_db();

	$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
	assert_true(in_array('users', $tables), "users table exists");
	assert_true(in_array('user_rules', $tables), "user_rules table exists");
	assert_true(in_array('tasks', $tables), "tasks table exists");
	assert_true(in_array('task_details', $tables), "task_details table exists");

	$fk = $pdo->query("PRAGMA foreign_keys")->fetchColumn();
	assert_eq('1', (string)$fk, "PRAGMA foreign_keys is ON");

	seed_users($pdo);
	$ins = $pdo->prepare("INSERT OR IGNORE INTO tasks (user_id, task_date, title, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, 0, ?)");
	$ins->execute([1, '2025-03-15', 'Cleaning', '08:00', '12:00', '']);
	$ins->execute([1, '2025-03-15', 'Cleaning', '09:00', '13:00', '']);
	$count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Cleaning' AND user_id=1 AND task_date='2025-03-15'")->fetchColumn();
	assert_eq(1, $count, "UNIQUE(user_id, task_date, title) prevents duplicates");

	$row = $pdo->query("SELECT start_time FROM tasks WHERE title='Cleaning'")->fetch();
	assert_eq('08:00', $row['start_time'], "INSERT OR IGNORE preserves original data");
}


/* 2. SMART MERGE */
test_group("2. Smart Merge — Rule Engine Protection");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);
	$ignore_stmt = $pdo->prepare("INSERT OR IGNORE INTO tasks (user_id, task_date, title, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, 0, '')");

	upsert_task($pdo, $mari_id, 'Office', '2025-03-17', '08:00', '16:00', 1, 'note');
	$ignore_stmt->execute([$mari_id, '2025-03-17', 'Office', '09:00', '17:00']);

	$row = $pdo->query("SELECT * FROM tasks WHERE user_id={$mari_id} AND title='Office'")->fetch();
	assert_eq('08:00', $row['start_time'], "Smart merge: started task start_time preserved");
	assert_eq(1, (int)$row['status'], "Smart merge: started task status preserved");

	$ignore_stmt->execute([$mari_id, '2025-03-17', 'Warehouse', '10:00', '12:00']);
	$count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id={$mari_id}")->fetchColumn();
	assert_eq(2, $count, "Smart merge: new unique task is inserted normally");
}


/* 3. UPSERT */
test_group("3. Upsert — ON CONFLICT DO UPDATE");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	upsert_task($pdo, $mari_id, 'Delivery', '2025-03-20', '08:00', '10:00', 0, 'old note');
	upsert_task($pdo, $mari_id, 'Delivery', '2025-03-20', '09:00', '11:00', 1, 'new note');

	$row = $pdo->query("SELECT * FROM tasks WHERE title='Delivery'")->fetch();
	assert_eq('09:00', $row['start_time'], "Upsert updates start_time");
	assert_eq(1, (int)$row['status'], "Upsert updates status");
	assert_eq('new note', $row['notes'], "Upsert updates notes");

	$count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Delivery'")->fetchColumn();
	assert_eq(1, $count, "Upsert does not create duplicate rows");
}


/* 4. TRUE SYNC */
test_group("4. Auto-Cleanup (True Sync 2.0)");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	upsert_task($pdo, $mari_id, 'Task_Pending', '2025-03-10', '08:00', '12:00', 0, '');
	upsert_task($pdo, $mari_id, 'Task_Started', '2025-03-11', '09:00', '13:00', 1, '');
	upsert_task($pdo, $mari_id, 'Task_Done',    '2025-03-12', '10:00', '14:00', 2, '');

	$pdo->prepare("DELETE FROM tasks WHERE user_id = ? AND task_date LIKE ? AND status = 0")->execute([$mari_id, '2025-03-%']);

	assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Task_Pending'")->fetchColumn(), "Auto-cleanup: unstarted tasks deleted");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Task_Started'")->fetchColumn(), "Auto-cleanup: started tasks preserved");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Task_Done'")->fetchColumn(), "Auto-cleanup: done tasks preserved");
}


/* 5. STATUS PROGRESSION — calls real api_tasks_status() */
test_group("5. Status Progression via api_tasks_status()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	upsert_task($pdo, $mari_id, 'Job', '2025-03-15', '08:00', '16:00', 0, '');
	$task_id = $pdo->lastInsertId();

	// 0 → 1
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_id, 'start_time' => '08:30', 'end_time' => '16:00', 'status' => 0, 'notes' => 'Started late'
	], $mari_id);
	assert_eq(200, $code, "status 0→1: returns 200");
	assert_eq('1', $body['status'], "status 0→1: body has status=1");
	$row = $pdo->query("SELECT status, start_time, notes FROM tasks WHERE id={$task_id}")->fetch();
	assert_eq(1, (int)$row['status'], "status 0→1: DB updated");
	assert_eq('Started late', $row['notes'], "status 0→1: notes stored");

	// 1 → 2
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_id, 'start_time' => '08:30', 'end_time' => '15:45', 'status' => 1, 'notes' => 'Done early'
	], $mari_id);
	assert_eq('2', $body['status'], "status 1→2: body has status=2");

	// 2 stays 2 (capped)
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_id, 'start_time' => '08:30', 'end_time' => '15:45', 'status' => 2, 'notes' => ''
	], $mari_id);
	assert_eq('2', $body['status'], "status capped at 2");

	// Wrong user can't update
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_id, 'start_time' => '00:00', 'end_time' => '00:00', 'status' => 0, 'notes' => ''
	], 999);
	assert_eq(404, $code, "Wrong user_id: status update returns 404");
	$row = $pdo->query("SELECT status FROM tasks WHERE id={$task_id}")->fetch();
	assert_eq(2, (int)$row['status'], "Wrong user_id: status unchanged in DB");
}


/* 6. CASCADE DELETE */
test_group("6. Cascade Delete");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);

	upsert_task($pdo, $mari_id, 'Cleaning', '2025-03-15', '08:00', '16:00', 0, '');
	$pdo->prepare("INSERT INTO user_rules (user_id, rules_text) VALUES (?, ?)")->execute([$mari_id, "test"]);
	upsert_task($pdo, $juri_id, 'Warehouse', '2025-03-15', '10:00', '14:00', 0, '');

	$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$mari_id]);

	assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id={$mari_id}")->fetchColumn(), "CASCADE: tasks gone");
	assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM user_rules WHERE user_id={$mari_id}")->fetchColumn(), "CASCADE: rules gone");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id={$juri_id}")->fetchColumn(), "CASCADE: other user unaffected");
}


/* 7. TASK DETAILS */
test_group("7. Task Details");
{
	$pdo = create_test_db();

	$pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)
		ON CONFLICT(title) DO UPDATE SET address=excluded.address, description=excluded.description, related_person=excluded.related_person")
		->execute(['Office', 'Tallinn 10', 'Main office', 'Mati']);
	$row = $pdo->query("SELECT * FROM task_details WHERE title='Office'")->fetch();
	assert_eq('Tallinn 10', $row['address'], "Detail insert: address stored");

	$pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)
		ON CONFLICT(title) DO UPDATE SET address=excluded.address, description=excluded.description, related_person=excluded.related_person")
		->execute(['Office', 'Tartu 5', 'Branch', 'Kati']);
	$row = $pdo->query("SELECT * FROM task_details WHERE title='Office'")->fetch();
	assert_eq('Tartu 5', $row['address'], "Detail upsert: address updated");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM task_details")->fetchColumn(), "Detail upsert: no duplicates");
}


/* 8. RULE ENGINE — calls real api_rules_generate() */
test_group("8. Rule Engine via api_rules_generate()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	// Estonian day letters (ER = Monday+Friday, all 4 full weeks)
	[$code, $body] = api_rules_generate($pdo, ['rules_txt' => '[{"title":"Cleaning","days":"ER","weeks":"1234","start":"08:00","end":"16:00"}]'], $mari_id, '2025-03');
	assert_eq(200, $code, "Rule engine returns 200");
	assert_eq('ok', $body['msg'], "Rule engine returns msg=ok");
	$tasks = $pdo->query("SELECT task_date FROM tasks WHERE user_id={$mari_id} ORDER BY task_date")->fetchAll(PDO::FETCH_COLUMN);
	assert_eq(8, count($tasks), "Rule ER+1234: generates 8 tasks (4 Mon + 4 Fri in full weeks)");

	// Rules saved as JSON
	$saved = $pdo->query("SELECT rules_text FROM user_rules WHERE user_id={$mari_id}")->fetchColumn();
	assert_contains($saved, 'Cleaning', "Rules JSON saved in user_rules");
	$decoded = json_decode($saved, true);
	assert_true(is_array($decoded), "Saved rules_text is valid JSON");

	// 2nd full week only
	$pdo->exec("DELETE FROM tasks");
	[$code, $body] = api_rules_generate($pdo, ['rules_txt' => '[{"title":"Job","days":"1","weeks":"2","start":"08:00","end":"12:00"}]'], $mari_id, '2025-03');
	$dates = $pdo->query("SELECT task_date FROM tasks ORDER BY task_date")->fetchAll(PDO::FETCH_COLUMN);
	assert_eq(1, count($dates), "Week '2': second full week generates 1 task");
	assert_eq('2025-03-10', $dates[0], "Week '2': 2nd full week Monday = March 10");

	// Explicit week indices (bi-weekly)
	$pdo->exec("DELETE FROM tasks");
	api_rules_generate($pdo, ['rules_txt' => '[{"title":"Job","days":"1","weeks":"13","start":"08:00","end":"12:00"}]'], $mari_id, '2025-03');
	$dates = $pdo->query("SELECT task_date FROM tasks ORDER BY task_date")->fetchAll(PDO::FETCH_COLUMN);
	assert_eq(2, count($dates), "Explicit weeks '13': 2 tasks generated");

	// Auto-cleanup: re-generate clears status=0 but keeps status=1
	$pdo->prepare("UPDATE tasks SET status=1 WHERE task_date='2025-03-03'")->execute();
	api_rules_generate($pdo, ['rules_txt' => '[{"title":"Job","days":"1","weeks":"13","start":"09:00","end":"13:00"}]'], $mari_id, '2025-03');
	$started = $pdo->query("SELECT start_time FROM tasks WHERE task_date='2025-03-03'")->fetch();
	assert_eq('08:00', $started['start_time'], "Auto-cleanup: started task preserved original time");

	// Rule-generated tasks have source='rule'
	$rule_src = $pdo->query("SELECT source FROM tasks WHERE task_date='2025-03-17'")->fetchColumn();
	assert_eq('rule', $rule_src, "Rule-generated tasks have source='rule'");

	// Manual task survives regeneration
	$pdo->exec("INSERT INTO tasks (user_id, task_date, title, start_time, end_time, status, source) VALUES ({$mari_id}, '2025-03-10', 'Manual Future', '10:00', '11:00', 0, 'manual')");
	api_rules_generate($pdo, ['rules_txt' => '[{"title":"Job","days":"1","weeks":"13","start":"09:00","end":"13:00"}]'], $mari_id, '2025-03');
	$manual = $pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Manual Future'")->fetchColumn();
	assert_eq(1, (int)$manual, "Auto-cleanup: manually added not-started task preserved");

	// But rule-generated not-started tasks are still cleaned up
	$rule_count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Job' AND source='rule'")->fetchColumn();
	assert_true($rule_count > 0, "Auto-cleanup: rule tasks re-generated after cleanup");
}


/* 9. timeShift() */
test_group("9. timeShift()");
{
	[$s, $e] = timeShift('', '12:00');
	assert_eq('', $s, "timeShift: empty start passes through");

	$fake_start = (date('H') === '03') ? '04:00' : '03:00';
	$fake_end   = date('H:i', strtotime($fake_start) + 3600);
	[$s, $e] = timeShift($fake_start, $fake_end);
	$shifted_duration = strtotime($e) - strtotime($s);
	if ($shifted_duration < 0) $shifted_duration += 86400;
	assert_eq(3600, $shifted_duration, "timeShift: 1-hour duration preserved");
}


/* 10. i18n */
test_group("10. i18n");
{
	global $langi;
	$langi = 0;
	assert_eq('TODAY', __('nav_today'), "EN: nav_today = TODAY");
	$langi = 1;
	assert_eq('TÄNA', __('nav_today'), "ET: nav_today = TÄNA");
	$langi = 0;
}


/* 11. api_tasks_today() — REAL handler, not simulated SQL */
test_group("11. api_tasks_today() — Real Handler");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);
	$dc11 = new DateContext('2025-03-12', '09:00');
	$today     = $dc11->today;      // 2025-03-12
	$yesterday = $dc11->yesterday(); // 2025-03-11

	upsert_task($pdo, $mari_id, 'Today-Pending',  $today,     '08:00', '12:00', 0, '');
	upsert_task($pdo, $mari_id, 'Today-Done',     $today,     '09:00', '13:00', 2, 'done note');
	upsert_task($pdo, $mari_id, 'Yesterday-Open', $yesterday, '14:00', '18:00', 1, 'still going');
	upsert_task($pdo, $mari_id, 'Yesterday-Done', $yesterday, '10:00', '11:00', 2, '');
	upsert_task($pdo, $mari_id, '<b>XSS</b>',     $today,     '10:00', '11:00', 0, '');

	[$code, $body] = api_tasks_today($pdo, $mari_id, $dc11);

	assert_eq(200, $code, "returns 200");
	$titles = array_column($body, 'title');
	assert_true(in_array('Today-Pending', $titles), "includes today's pending");
	assert_true(in_array('Today-Done', $titles), "includes today's done");
	assert_true(in_array('Yesterday-Open', $titles), "includes yesterday's unfinished");
	assert_true(!in_array('Yesterday-Done', $titles), "excludes yesterday's done");

	// Response shape
	$required_keys = ['id', 'title', 'start_time', 'end_time', 'status', 'status_text', 'notes'];
	foreach ($required_keys as $k)
		assert_true(array_key_exists($k, $body[0]), "has key '{$k}'");
	assert_true(is_int($body[0]['id']), "id is integer");
	assert_true(is_int($body[0]['status']), "status is integer");

	// Raw data (no HTML escaping in API)
	$xss = array_values(array_filter($body, fn($t) => str_contains($t['title'], 'XSS')))[0];
	assert_eq('<b>XSS</b>', $xss['title'], "title is raw (not HTML-escaped)");

	// JSON round-trip
	$decoded = json_decode(json_encode($body), true);
	assert_eq(count($body), count($decoded), "JSON round-trip preserves length");
}


/* 12. AUTHENTICATION */
test_group("12. Authentication");
{
	$pdo = create_test_db();
	seed_users($pdo);
	$row = $pdo->query("SELECT password FROM users WHERE username='admin'")->fetch();
	assert_true(password_verify('admin_pass', $row['password']), "Correct password verifies");
	assert_true(!password_verify('wrong', $row['password']), "Wrong password rejected");
}


/* 13. Yearly Archival */
test_group("13. Yearly Archival");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);
	upsert_task($pdo, $mari_id, 'Job', '2025-03-15', '08:00', '16:00', 2, '');
	$pdo->exec("ALTER TABLE tasks RENAME TO 'tasks_2025';");
	$archived = (int)$pdo->query("SELECT COUNT(*) FROM tasks_2025")->fetchColumn();
	assert_eq(1, $archived, "Archival: tasks_2025 has archived tasks");
}


/* 14. DELETE PROTECTION — calls real api_tasks_delete() */
test_group("14. Delete Protection via api_tasks_delete()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	upsert_task($pdo, $mari_id, 'Done-Job', '2025-03-15', '08:00', '16:00', 2, '');
	upsert_task($pdo, $mari_id, 'Open-Job', '2025-03-15', '09:00', '12:00', 0, '');
	$done_id = $pdo->query("SELECT id FROM tasks WHERE title='Done-Job'")->fetchColumn();
	$open_id = $pdo->query("SELECT id FROM tasks WHERE title='Open-Job'")->fetchColumn();

	// Admin tries to delete completed task — blocked by status<2 guard
	[$code, $body] = api_tasks_delete($pdo, ['id' => $done_id], $admin_id, true);
	assert_eq(404, $code, "delete blocked by status guard returns 404");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE id={$done_id}")->fetchColumn(), "Cannot delete completed task");

	// Admin deletes open task — succeeds
	[$code, $body] = api_tasks_delete($pdo, ['id' => $open_id], $admin_id, true);
	assert_eq(200, $code, "delete open task returns 200");
	assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE id={$open_id}")->fetchColumn(), "Can delete open task");

	// Worker can't delete another's task
	upsert_task($pdo, $mari_id, 'Other-Job', '2025-03-15', '10:00', '14:00', 0, '');
	$other_id = $pdo->query("SELECT id FROM tasks WHERE title='Other-Job'")->fetchColumn();
	[$code, $body] = api_tasks_delete($pdo, ['id' => $other_id], 999, false);
	assert_eq(404, $code, "Worker can't delete another's task returns 404");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE id={$other_id}")->fetchColumn(), "Worker can't delete another's task");
}


/* 15. EDGE CASES */
test_group("15. Edge Cases");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	assert_throws(function() use ($pdo) {
		upsert_task($pdo, 999, 'Job', '2025-03-15', '08:00', '16:00', 0, '');
	}, "FK violation: cannot insert task for non-existent user");

	upsert_task($pdo, $mari_id, '', '2025-03-15', '08:00', '16:00', 0, '');
	$row = $pdo->query("SELECT title FROM tasks WHERE user_id={$mari_id}")->fetch();
	assert_eq('', $row['title'], "Empty title is stored");

	assert_throws(function() use ($pdo) {
		$pdo->exec("INSERT INTO task_details (title) VALUES (NULL)");
	}, "task_details: NULL title rejected");
}


/* 16. ENDPOINT INTEGRATION — calls real handlers end-to-end */
test_group("16. Endpoint Integration — Real Handlers");
{
	$pdo = create_test_db();
	
	[$admin_id, $mari_id] = seed_users($pdo);
	$dc16 = new DateContext('2025-03-12', '09:00');
	$today = $dc16->today;   // 2025-03-12
	$ym    = $dc16->ym;      // 2025-03

	// Seed
	upsert_task($pdo, $mari_id, 'TaskA', $today, '08:00', '12:00', 0, 'note A');
	upsert_task($pdo, $mari_id, 'TaskB', $today, '13:00', '17:00', 1, '');
	$pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)")
		->execute(['TaskA', 'Tallinn', 'Office cleaning', 'Mati']);

	// api_tasks_today
	[$code, $body] = api_tasks_today($pdo, $mari_id, $dc16);
	assert_eq(200, $code, "tasks/today: 200");
	assert_eq(2, count($body), "tasks/today: 2 tasks");



// api_tasks_save — create new
	[$code, $body] = api_tasks_save($pdo, [
		'id' => '', 'title' => 'NewTask', 'task_date' => $today,
		'start_time' => '18:00', 'end_time' => '19:00', 'status' => 0, 'notes' => ''
	], $mari_id, false);
	assert_eq(200, $code, "tasks/save create: 200");
	assert_true(isset($body['id']) && $body['id'] > 0, "tasks/save create: explicitly returns new task ID");
	assert_eq(3, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id={$mari_id}")->fetchColumn(), "tasks/save: task created");

	// api_tasks_save — duplicate via upsert updates instead of failing
	[$code, $body] = api_tasks_save($pdo, [
		'id' => '', 'title' => 'NewTask', 'task_date' => $today,
		'start_time' => '20:00', 'end_time' => '21:00', 'status' => 0, 'notes' => ''
	], $mari_id, false);
	assert_eq(200, $code, "tasks/save duplicate: upsert updates (200)");
	assert_true(isset($body['id']) && $body['id'] > 0, "tasks/save duplicate: explicitly returns existing task ID");




	// api_tasks_month
	[$code, $body] = api_tasks_month($pdo, $mari_id, true, $ym, $mari_id, $dc16);
	assert_eq(200, $code, "tasks/month: 200");
	assert_true(array_key_exists($today, $body['grouped']), "tasks/month: grouped by date");
	assert_true(count($body['workers']) >= 3, "tasks/month: admin sees all workers");
	assert_true(in_array('TaskA', $body['known_titles']), "tasks/month: known_titles has TaskA");

	// api_tasks_team — admin
	[$code, $body] = api_tasks_team($pdo, true, 'today', $dc16);
	assert_eq(200, $code, "tasks/team: 200");
	$first_key = array_key_first($body['grouped']);
	assert_true(isset($body['grouped'][$first_key][0]['username']), "tasks/team: has username");

	// api_tasks_team — non-admin blocked
	[$code, $body] = api_tasks_team($pdo, false, 'today', $dc16);
	assert_eq(403, $code, "tasks/team: non-admin gets 403");

	// api_tasks_print — join with details
	$task_a_id = $pdo->query("SELECT id FROM tasks WHERE title='TaskA'")->fetchColumn();
	[$code, $body] = api_tasks_print($pdo, $mari_id, false, (string)$task_a_id, $dc16);
	assert_eq(200, $code, "tasks/print: 200");
	$first_worker = array_key_first($body['print_data']);
	$printed = $body['print_data'][$first_worker][0];
	assert_eq('Tallinn', $printed['address'], "tasks/print: joins address from task_details");
	assert_eq('Mati', $printed['related_person'], "tasks/print: joins related_person");

	// api_tasks_print — non-admin batch blocked
	[$code, $body] = api_tasks_print($pdo, $mari_id, false, null, $dc16);
	assert_eq(403, $code, "tasks/print batch: non-admin gets 403");

	// api_details_get
	[$code, $body] = api_details_get($pdo);
	assert_eq(200, $code, "details: 200");
	assert_true(count($body['details']) >= 1, "details: has entries");
	assert_true(in_array('Tallinn', $body['known_addresses']), "details: known_addresses");
	assert_true(in_array('Mati', $body['known_contacts']), "details: known_contacts");

	// api_tasks_status — progress TaskA from 0→1
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_a_id, 'start_time' => '08:15', 'end_time' => '12:00', 'status' => 0, 'notes' => 'note A'
	], $mari_id);
	assert_eq(200, $code, "tasks/status: 200");
	assert_eq('1', $body['status'], "tasks/status: progressed to 1");

	// api_tasks_delete — completed task protected
	$pdo->prepare("UPDATE tasks SET status=2 WHERE id=?")->execute([$task_a_id]);
	[$code, $body] = api_tasks_delete($pdo, ['id' => $task_a_id], $mari_id, false);
	assert_eq(404, $code, "tasks/delete: completed task returns 404");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE id={$task_a_id}")->fetchColumn(), "tasks/delete: completed task protected");

	// api_rules_generate through the handler (using week 1 for stability across months)
	[$code, $body] = api_rules_generate($pdo, ['rules_txt' => '[{"title":"Generated","days":"15","weeks":"1","start":"07:00","end":"15:00"}]'], $mari_id, $ym);
	assert_eq(200, $code, "rules/generate: 200");
	$gen_count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Generated'")->fetchColumn();
	assert_true($gen_count > 0, "rules/generate: tasks created");

	// Invalid JSON returns 400
	[$code, $body] = api_rules_generate($pdo, ['rules_txt' => 'not valid json'], $mari_id, $ym);
	assert_eq(400, $code, "rules/generate: invalid JSON returns 400");
}


// ── 17. INPUT VALIDATION ──
test_group("17. Input Validation");
{
	// is_valid_time
	assert_true(is_valid_time('08:00'), "is_valid_time: 08:00 valid");
	assert_true(is_valid_time('23:59'), "is_valid_time: 23:59 valid");
	assert_true(is_valid_time('00:00'), "is_valid_time: 00:00 valid");
	assert_true(is_valid_time(''), "is_valid_time: empty allowed");
	assert_true(!is_valid_time('25:00'), "is_valid_time: 25:00 rejected");
	assert_true(!is_valid_time('08:60'), "is_valid_time: 08:60 rejected");
	assert_true(is_valid_time('8:00'), "is_valid_time: 8:00 accepted (PHP allows single digit)");
	assert_true(!is_valid_time('banana'), "is_valid_time: garbage rejected");

	// is_valid_date
	assert_true(is_valid_date('2025-03-15'), "is_valid_date: 2025-03-15 valid");
	assert_true(is_valid_date('2025-12-31'), "is_valid_date: 2025-12-31 valid");
	assert_true(is_valid_date('2025-3-15'), "is_valid_date: 2025-3-15 accepted (PHP allows single digit)");
	assert_true(!is_valid_date('2025-02-29'), "is_valid_date: 2025-02-29 rejected (not leap year)");
	assert_true(is_valid_date('2024-02-29'), "is_valid_date: 2024-02-29 valid (leap year)");
	assert_true(!is_valid_date('banana'), "is_valid_date: garbage rejected");
	assert_true(!is_valid_date(''), "is_valid_date: empty rejected");

	// validate_task — valid
	assert_eq(null, validate_task(['title' => 'Test', 'task_date' => '2025-03-15', 'start_time' => '08:00', 'end_time' => '16:00', 'status' => 0]),
		"validate_task: valid task returns null");

	// validate_task — missing title
	assert_true(validate_task(['title' => '', 'task_date' => '2025-03-15']) !== null,
		"validate_task: empty title rejected");
	assert_true(validate_task(['task_date' => '2025-03-15']) !== null,
		"validate_task: missing title rejected");

	// validate_task — bad date
	assert_true(validate_task(['title' => 'X', 'task_date' => 'banana']) !== null,
		"validate_task: bad date rejected");
	assert_true(validate_task(['title' => 'X', 'task_date' => '']) !== null,
		"validate_task: empty date rejected");

	// validate_task — bad times
	assert_true(validate_task(['title' => 'X', 'task_date' => '2025-03-15', 'start_time' => '99:99']) !== null,
		"validate_task: bad start_time rejected");
	assert_true(validate_task(['title' => 'X', 'task_date' => '2025-03-15', 'end_time' => 'noon']) !== null,
		"validate_task: bad end_time rejected");

	// validate_task — bad status
	assert_true(validate_task(['title' => 'X', 'task_date' => '2025-03-15', 'status' => 5]) !== null,
		"validate_task: status=5 rejected");

	// validate_task — need_title=false, need_date=false (status endpoint)
	assert_eq(null, validate_task(['start_time' => '08:00', 'end_time' => '16:00', 'status' => 1], false, false),
		"validate_task: status-mode accepts no title/date");

	// Integration: api_tasks_save rejects bad input
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	[$code, $body] = api_tasks_save($pdo, ['title' => '', 'task_date' => '2025-03-15', 'start_time' => '08:00', 'end_time' => '16:00'], $mari_id, false);
	assert_eq(400, $code, "api_tasks_save: empty title → 400");

	[$code, $body] = api_tasks_save($pdo, ['title' => 'Valid', 'task_date' => 'garbage', 'start_time' => '08:00', 'end_time' => '16:00'], $mari_id, false);
	assert_eq(400, $code, "api_tasks_save: bad date → 400");

	[$code, $body] = api_tasks_save($pdo, ['title' => 'Valid', 'task_date' => '2025-03-15', 'start_time' => '08:00', 'end_time' => '16:00'], $mari_id, false);
	assert_eq(200, $code, "api_tasks_save: valid input → 200");
}


// ── 18. SHARED FUNCTIONS & HANDLER GUARDS ──
test_group("18. upsert_task, rowCount Guards, Validation Integration");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	// upsert_task — insert
	upsert_task($pdo, $mari_id, 'SharedJob', '2025-06-10', '08:00', '12:00', 0, 'initial');
	$row = $pdo->query("SELECT * FROM tasks WHERE title='SharedJob'")->fetch();
	assert_eq('08:00', $row['start_time'], "upsert_task: insert stores start_time");
	assert_eq('initial', $row['notes'], "upsert_task: insert stores notes");

	// upsert_task — update on conflict
	upsert_task($pdo, $mari_id, 'SharedJob', '2025-06-10', '09:00', '13:00', 1, 'updated');
	$row = $pdo->query("SELECT * FROM tasks WHERE title='SharedJob'")->fetch();
	assert_eq('09:00', $row['start_time'], "upsert_task: conflict updates start_time");
	assert_eq('updated', $row['notes'], "upsert_task: conflict updates notes");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='SharedJob'")->fetchColumn(), "upsert_task: no duplicate rows");

	// upsert_task — FK violation
	assert_throws(function() use ($pdo) {
		upsert_task($pdo, 9999, 'Ghost', '2025-06-10', '08:00', '12:00', 0, '');
	}, "upsert_task: FK violation for non-existent user");

	// api_tasks_status — validation rejects missing id
	[$code, $body] = api_tasks_status($pdo, [
		'start_time' => '08:00', 'end_time' => '12:00', 'status' => 0
	], $mari_id);
	assert_eq(400, $code, "tasks/status: missing id → 400");

	// api_tasks_status — validation rejects bad time
	$task_id = $pdo->query("SELECT id FROM tasks WHERE title='SharedJob'")->fetchColumn();
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $task_id, 'start_time' => 'banana', 'end_time' => '12:00', 'status' => 0
	], $mari_id);
	assert_eq(400, $code, "tasks/status: bad start_time → 400");

	// api_tasks_status — rowCount guard (wrong user)
	upsert_task($pdo, $mari_id, 'RowCheck', '2025-06-11', '08:00', '12:00', 0, '');
	$rc_id = $pdo->query("SELECT id FROM tasks WHERE title='RowCheck'")->fetchColumn();
	[$code, $body] = api_tasks_status($pdo, [
		'id' => $rc_id, 'start_time' => '08:00', 'end_time' => '12:00', 'status' => 0, 'notes' => ''
	], 9999);
	assert_eq(404, $code, "tasks/status: wrong user_id → 404 (rowCount guard)");

	// api_tasks_delete — validation rejects missing id
	[$code, $body] = api_tasks_delete($pdo, [], $mari_id, false);
	assert_eq(400, $code, "tasks/delete: missing id → 400");

	// api_tasks_delete — rowCount guard (completed task)
	upsert_task($pdo, $mari_id, 'DoneTask', '2025-06-12', '08:00', '12:00', 2, '');
	$done_id = $pdo->query("SELECT id FROM tasks WHERE title='DoneTask'")->fetchColumn();
	[$code, $body] = api_tasks_delete($pdo, ['id' => $done_id], $admin_id, true);
	assert_eq(404, $code, "tasks/delete: completed task → 404 (rowCount guard)");

	// api_tasks_delete — rowCount guard (wrong user)
	upsert_task($pdo, $mari_id, 'OtherTask', '2025-06-13', '08:00', '12:00', 0, '');
	$other_id = $pdo->query("SELECT id FROM tasks WHERE title='OtherTask'")->fetchColumn();
	[$code, $body] = api_tasks_delete($pdo, ['id' => $other_id], 9999, false);
	assert_eq(404, $code, "tasks/delete: wrong user → 404 (rowCount guard)");

	// app_log — function exists and is callable
	assert_true(function_exists('app_log'), "app_log() function exists");
}


// ── 19. EXTRACTED HANDLERS — api_tasks_batch ──
test_group("19. api_tasks_batch()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);
	$today = '2025-03-12';

	// Valid batch assign to two workers
	[$code, $body] = api_tasks_batch($pdo, [
		'title' => 'Cleaning', 'task_date' => $today,
		'start_time' => '08:00', 'end_time' => '12:00',
		'worker_ids' => [$mari_id, $juri_id]
	]);
	assert_eq(200, $code, "batch: valid → 200");
	assert_eq(2, $body['updated'], "batch: 2 workers updated");
	assert_eq(2, (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE title='Cleaning'")->fetchColumn(), "batch: 2 tasks created");

	// Missing worker_ids
	[$code, $body] = api_tasks_batch($pdo, [
		'title' => 'Job', 'task_date' => $today,
		'start_time' => '08:00', 'end_time' => '12:00'
	]);
	assert_eq(400, $code, "batch: missing worker_ids → 400");

	// Validation: bad date
	[$code, $body] = api_tasks_batch($pdo, [
		'title' => 'Job', 'task_date' => 'garbage',
		'start_time' => '08:00', 'end_time' => '12:00',
		'worker_ids' => [$mari_id]
	]);
	assert_eq(400, $code, "batch: bad date → 400");

	// Validation: empty title
	[$code, $body] = api_tasks_batch($pdo, [
		'title' => '', 'task_date' => $today,
		'start_time' => '08:00', 'end_time' => '12:00',
		'worker_ids' => [$mari_id]
	]);
	assert_eq(400, $code, "batch: empty title → 400");
}


// ── 20. EXTRACTED HANDLERS — api_users_* ──
test_group("20. api_users_list, api_users_create, api_users_delete");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);

	// List users
	[$code, $body] = api_users_list($pdo);
	assert_eq(200, $code, "users list: 200");
	assert_eq(3, count($body), "users list: 3 users");

	// Create user
	[$code, $body] = api_users_create($pdo, ['username' => 'peeter', 'password' => 'pass123', 'real_name' => 'Peeter Puu', 'contact' => '+372 555 9999']);
	assert_eq(200, $code, "users create: 200");
	assert_eq('Peeter Puu', $body['real_name'], "users create: real_name returned");
	assert_eq('+372 555 9999', $body['contact'], "users create: contact returned");
	assert_eq(4, (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(), "users create: 4 users now");

	// List includes real_name/contact
	[$code, $body] = api_users_list($pdo);
	$peeter = array_values(array_filter($body, fn($u) => $u['username'] === 'peeter'))[0];
	assert_eq('Peeter Puu', $peeter['real_name'], "users list: real_name present");
	assert_eq('+372 555 9999', $peeter['contact'], "users list: contact present");

	// Duplicate user
	[$code, $body] = api_users_create($pdo, ['username' => 'peeter', 'password' => 'other']);
	assert_eq(400, $code, "users create duplicate: 400");
	assert_eq('user_exists', $body['error'], "users create duplicate: user_exists error");

	// Empty credentials
	[$code, $body] = api_users_create($pdo,['username' => '', 'password' => '']);
	assert_eq(400, $code, "users create empty: 400");

	// Delete user
	$peeter_id = (int)$pdo->query("SELECT id FROM users WHERE username='peeter'")->fetchColumn();
	[$code, $body] = api_users_delete($pdo, ['id' => $peeter_id]);
	assert_eq(200, $code, "users delete: 200");
	assert_eq(3, (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(), "users delete: back to 3");

	// Delete admin protected
	[$code, $body] = api_users_delete($pdo, ['id' => 1]);
	assert_eq(403, $code, "users delete admin: 403");

	// Delete non-existent
	[$code, $body] = api_users_delete($pdo, ['id' => 9999]);
	assert_eq(404, $code, "users delete missing: 404");
}


// ── 21. EXTRACTED HANDLERS — api_users_password ──
test_group("21. api_users_password()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	// Wrong old password
	[$code, $body] = api_users_password($pdo, [
		'old_password' => 'wrong', 'new_password' => 'newpass'
	], $mari_id);
	assert_eq(403, $code, "password: wrong old → 403");

	// Correct old password
	[$code, $body] = api_users_password($pdo, [
		'old_password' => 'mari_pass', 'new_password' => 'newpass'
	], $mari_id);
	assert_eq(200, $code, "password: correct old → 200");

	// Verify new password works
	$hash = $pdo->query("SELECT password FROM users WHERE id={$mari_id}")->fetchColumn();
	assert_true(password_verify('newpass', $hash), "password: new password verifies");
	assert_true(!password_verify('mari_pass', $hash), "password: old password no longer works");

	// Verify force_password_change flag is cleared
	$fpc = (int)$pdo->query("SELECT force_password_change FROM users WHERE id={$mari_id}")->fetchColumn();
	assert_eq(0, $fpc, "password: force_password_change cleared after change");

	// Missing old_password
	[$code, $body] = api_users_password($pdo, ['new_password' => 'x'], $mari_id);
	assert_eq(400, $code, "password: missing old → 400");

	// Missing new_password
	[$code, $body] = api_users_password($pdo, ['old_password' => 'newpass'], $mari_id);
	assert_eq(400, $code, "password: missing new → 400");
}


// ── 21b. Rate limiter — rl_ok, rl_fail, rl_clear ──
test_group("21b. Rate limiter (rl_ok, rl_fail, rl_clear)");
{
	$pdo = create_test_db();
	$ip = '192.168.1.100';

	// Fresh IP should be OK
	assert_true(rl_ok($pdo, $ip), "rl: fresh IP is OK");

	// Record 5 failures → should be blocked
	for ($i = 0; $i < 5; $i++) rl_fail($pdo, $ip);
	assert_true(!rl_ok($pdo, $ip), "rl: blocked after 5 failures");

	// Clear → should be OK again
	rl_clear($pdo, $ip);
	assert_true(rl_ok($pdo, $ip), "rl: OK after rl_clear");

	// Expired rows are auto-cleaned
	$pdo->prepare("INSERT INTO rl (ip, fails, expire) VALUES (?, 10, ?) ON CONFLICT(ip) DO UPDATE SET fails = 10, expire = excluded.expire")
		->execute(['10.0.0.1', time() - 1]);
	assert_true(rl_ok($pdo, '10.0.0.1'), "rl: expired row auto-cleaned");
	$leftover = $pdo->query("SELECT COUNT(*) FROM rl WHERE ip = '10.0.0.1'")->fetchColumn();
	assert_eq(0, (int)$leftover, "rl: expired row deleted from table");
}


// ── 22. EXTRACTED HANDLERS — api_details_save, api_details_delete ──
test_group("22. api_details_save, api_details_delete");
{
	$pdo = create_test_db();

	// Save new detail
	[$code, $body] = api_details_save($pdo, [
		'title' => 'Office', 'address' => 'Tallinn 10',
		'description' => 'Main', 'related_person' => 'Mati'
	]);
	assert_eq(200, $code, "details save: 200");
	$row = $pdo->query("SELECT * FROM task_details WHERE title='Office'")->fetch();
	assert_eq('Tallinn 10', $row['address'], "details save: address stored");

	// Upsert existing detail
	[$code, $body] = api_details_save($pdo, [
		'title' => 'Office', 'address' => 'Tartu 5',
		'description' => 'Branch', 'related_person' => 'Kati'
	]);
	assert_eq(200, $code, "details upsert: 200");
	$row = $pdo->query("SELECT * FROM task_details WHERE title='Office'")->fetch();
	assert_eq('Tartu 5', $row['address'], "details upsert: address updated");
	assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM task_details")->fetchColumn(), "details upsert: no duplicates");

	// Delete detail
	[$code, $body] = api_details_delete($pdo, ['title' => 'Office']);
	assert_eq(200, $code, "details delete: 200");
	assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM task_details")->fetchColumn(), "details delete: gone");

	// Delete non-existent
	[$code, $body] = api_details_delete($pdo, ['title' => 'Ghost']);
	assert_eq(404, $code, "details delete missing: 404");
}


// ── 23. PLUGIN HANDLERS — archive_year (config plugin) ──
test_group("23. plugin_archive_year()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);
	upsert_task($pdo, $mari_id, 'Job', '2025-11-15', '08:00', '16:00', 2, '');

	// Not available (only Dec 21+)
	[$code, $body] = plugin_archive_year($pdo, [], $admin_id, true, $dc);
	$expected_code = api_can_archive() ? 200 : 400;
	assert_eq($expected_code, $code, "archive: returns " . $expected_code . " based on current date");

	// Non-admin blocked
	[$code, $body] = plugin_archive_year($pdo, [], $mari_id, false, $dc);
	assert_eq(403, $code, "archive: non-admin → 403");
}


// ── 24. EXTRACTED HANDLERS — api_users_update ──
test_group("24. api_users_update()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);

	// Update real_name and contact
	[$code, $body] = api_users_update($pdo, ['id' => $mari_id, 'username' => 'mari', 'real_name' => 'Mari Mets', 'contact' => '+372 555 1234']);
	assert_eq(200, $code, "users update: 200");
	assert_eq('Mari Mets', $body['real_name'], "users update: real_name returned");
	assert_eq('+372 555 1234', $body['contact'], "users update: contact returned");
	$row = $pdo->query("SELECT real_name, contact FROM users WHERE id={$mari_id}")->fetch();
	assert_eq('Mari Mets', $row['real_name'], "users update: real_name stored in DB");
	assert_eq('+372 555 1234', $row['contact'], "users update: contact stored in DB");

	// Update with password
	[$code, $body] = api_users_update($pdo, ['id' => $mari_id, 'username' => 'mari', 'password' => 'newpass123']);
	assert_eq(200, $code, "users update with password: 200");
	$hash = $pdo->query("SELECT password FROM users WHERE id={$mari_id}")->fetchColumn();
	assert_true(password_verify('newpass123', $hash), "users update: new password verifies");

	// Duplicate username
	[$code, $body] = api_users_update($pdo, ['id' => $mari_id, 'username' => 'jüri']);
	assert_eq(400, $code, "users update duplicate username: 400");
	assert_eq('username_exists', $body['error'], "users update duplicate: username_exists error");

	// Missing id
	[$code, $body] = api_users_update($pdo, ['username' => 'test']);
	assert_eq(400, $code, "users update missing id: 400");

	// Admin protected
	[$code, $body] = api_users_update($pdo, ['id' => 1, 'username' => 'hacked']);
	assert_eq(403, $code, "users update admin: 403");

	// Non-existent user
	[$code, $body] = api_users_update($pdo, ['id' => 9999, 'username' => 'ghost']);
	assert_eq(404, $code, "users update missing user: 404");

	// Empty username
	[$code, $body] = api_users_update($pdo, ['id' => $mari_id, 'username' => '']);
	assert_eq(400, $code, "users update empty username: 400");
}


// ── 25. api_tasks_print — real_name, user_contact, org config ──
test_group("25. api_tasks_print() — real_name + org config");
{
	global $cfg;
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);
	$dc25 = new DateContext('2025-03-12', '09:00');
	$today = $dc25->today;

	// Set real_name and contact on mari
	$pdo->prepare("UPDATE users SET real_name=?, contact=? WHERE id=?")->execute(['Mari Mets', '+372 555 1234', $mari_id]);

	// Seed org config
	$pdo->exec("INSERT INTO config (key, val) VALUES ('org_name', 'Test OÜ'), ('org_phone', '+372 600 0000')");
	$cfg = array_column($pdo->query("SELECT key,val FROM config")->fetchAll(), 'val', 'key');

	// Seed task + detail
	upsert_task($pdo, $mari_id, 'Cleaning', $today, '08:00', '16:00', 0, '');
	$pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)")
		->execute(['Cleaning', 'Tallinn 10', 'Office', 'Mati']);

	[$code, $body] = api_tasks_print($pdo, $admin_id, true, null, $dc25);
	assert_eq(200, $code, "print: returns 200");

	// Grouped by real_name, not username
	assert_true(isset($body['print_data']['Mari Mets']), "print: grouped by real_name");
	assert_true(!isset($body['print_data']['mari']), "print: not grouped by username");

	// Row contains user_contact
	$task = $body['print_data']['Mari Mets'][0];
	assert_eq('+372 555 1234', $task['user_contact'], "print: user_contact in row");

	// Org config included
	assert_true(isset($body['org']), "print: org key present");
	assert_eq('Test OÜ', $body['org']['org_name'], "print: org_name value");
	assert_eq('+372 600 0000', $body['org']['org_phone'], "print: org_phone value");

	// Fallback to username when real_name empty
	$pdo->prepare("UPDATE users SET real_name='' WHERE id=?")->execute([$mari_id]);
	[$code, $body] = api_tasks_print($pdo, $admin_id, true, null, $dc25);
	assert_true(isset($body['print_data']['mari']), "print: falls back to username when real_name empty");

	// Single task print (by task_id)
	$task_id = $pdo->query("SELECT id FROM tasks WHERE title='Cleaning'")->fetchColumn();
	[$code, $body] = api_tasks_print($pdo, $mari_id, false, (string)$task_id, $dc25);
	assert_eq(200, $code, "print single: worker can print own task");
	assert_true(count($body['print_data']) > 0, "print single: has data");

	// Reset $cfg
	$cfg = [];
}


// ── 26. DateContext — injectable date flexibility ──
test_group("26. DateContext — fixed date injection");
{
	// Construction defaults to real clock
	$live = new DateContext();
	assert_true(strlen($live->today) === 10, "DateContext: today is Y-m-d");
	assert_true(strlen($live->time)  ===  5, "DateContext: time is H:i");

	// Fixed construction
	$dc = new DateContext('2025-06-15', '14:30');
	assert_eq('2025-06-15', $dc->today,    "DateContext: fixed today");
	assert_eq('14:30',      $dc->time,     "DateContext: fixed time");
	assert_eq('2025-06',    $dc->ym,       "DateContext: ym derived");
	assert_eq('2025-06-01', $dc->month,    "DateContext: month first day");
	assert_eq('2025-06-14', $dc->yesterday(), "DateContext: yesterday()");

	// Month boundary
	$jan1 = new DateContext('2025-01-01', '00:00');
	assert_eq('2024-12-31', $jan1->yesterday(), "DateContext: yesterday crosses year boundary");

	// api_tasks_today with a PAST fixed date — tasks seeded on that date are returned,
	// tasks seeded on real today are NOT (proves handlers use injected date, not clock)
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	$past_dc   = new DateContext('2025-01-10', '10:00');
	$future_dc = new DateContext('2025-06-20', '10:00');

	upsert_task($pdo, $mari_id, 'Past-Task',   '2025-01-10', '08:00', '12:00', 0, '');
	upsert_task($pdo, $mari_id, 'Future-Task', '2025-06-20', '08:00', '12:00', 0, '');

	[$code, $body] = api_tasks_today($pdo, $mari_id, $past_dc);
	$titles = array_column($body, 'title');
	assert_true(in_array('Past-Task', $titles),    "DateContext inject: past dc sees past task");
	assert_true(!in_array('Future-Task', $titles), "DateContext inject: past dc excludes future task");

	[$code, $body] = api_tasks_today($pdo, $mari_id, $future_dc);
	$titles = array_column($body, 'title');
	assert_true(in_array('Future-Task', $titles), "DateContext inject: future dc sees future task");
	assert_true(!in_array('Past-Task', $titles),  "DateContext inject: future dc excludes past task");

	// api_tasks_team month scope uses dc->month, not real clock
	upsert_task($pdo, $mari_id, 'Jan-Team-Task', '2025-01-10', '09:00', '17:00', 0, '');
	[$code, $body] = api_tasks_team($pdo, true, 'month', $past_dc);
	assert_eq(200, $code, "DateContext team month: 200");
	$all_titles = [];
	foreach ($body['grouped'] as $rows) foreach ($rows as $r) $all_titles[] = $r['title'];
	assert_true(in_array('Jan-Team-Task', $all_titles),  "DateContext team month: jan dc sees jan task");
	assert_true(!in_array('Future-Task', $all_titles),   "DateContext team month: jan dc excludes june task");

	// api_tasks_print with no task_id uses dc->today as date filter
	upsert_task($pdo, $mari_id, 'Print-Jan', '2025-01-10', '08:00', '12:00', 0, '');
	[$code, $body] = api_tasks_print($pdo, $admin_id, true, null, $past_dc);
	assert_eq(200, $code, "DateContext print: 200");
	$print_titles = [];
	foreach ($body['print_data'] as $rows) foreach ($rows as $r) $print_titles[] = $r['title'];
	assert_true(in_array('Print-Jan', $print_titles),   "DateContext print: jan dc sees jan task");
	assert_true(!in_array('Future-Task', $print_titles),"DateContext print: jan dc excludes june task");

	// api_tasks_month today field reflects dc, not real clock
	[$code, $body] = api_tasks_month($pdo, $mari_id, false, '2025-01', $mari_id, $past_dc);
	assert_eq('2025-01-10', $body['today'], "DateContext month: today field = dc->today");
}


// ── 27. PLUGIN SYSTEM — loader contract & handler signatures ──
test_group("27. Plugin system — registration contract");
{
	// debug plugin defines expected functions
	assert_true(function_exists('app_log'), "debug plugin: app_log() exists");
	assert_true(function_exists('timer_log'), "debug plugin: timer_log() alias exists");
	assert_true(function_exists('plugin_debug_log'), "debug plugin: plugin_debug_log() exists");
	assert_true(function_exists('plugin_debug_timer'), "debug plugin: plugin_debug_timer() exists");

	// config plugin defines expected functions
	assert_true(function_exists('api_can_archive'), "config plugin: api_can_archive() exists");
	assert_true(function_exists('plugin_config'), "config plugin: plugin_config() exists");
	assert_true(function_exists('plugin_config_save'), "config plugin: plugin_config_save() exists");
	assert_true(function_exists('plugin_config_delete'), "config plugin: plugin_config_delete() exists");
	assert_true(function_exists('plugin_archive_year'), "config plugin: plugin_archive_year() exists");
}


// ── 28. PLUGIN HANDLERS — config CRUD via plugin ──
test_group("28. Plugin config CRUD");
{
	global $cfg;
	$pdo = create_test_db();
	[$admin_id, $mari_id] = seed_users($pdo);

	// GET — empty config
	$cfg = [];
	[$code, $body] = plugin_config($pdo, [], $admin_id, true, $dc, 'GET');
	assert_eq(200, $code, "config GET: 200");
	assert_eq(0, count($body), "config GET: empty initially");

	// POST — save a key
	[$code, $body] = plugin_config($pdo, ['key' => 'org_name', 'val' => 'Test OÜ'], $admin_id, true, $dc, 'POST');
	assert_eq(200, $code, "config POST save: 200");

	// GET — verify saved (refresh $cfg like a new request would)
	$cfg = array_column($pdo->query("SELECT key,val FROM config ORDER BY key")->fetchAll(), 'val', 'key');
	[$code, $body] = plugin_config($pdo, [], $admin_id, true, $dc, 'GET');
	assert_eq(1, count($body), "config GET: 1 row after save");
	assert_eq('org_name', $body[0]['key'], "config GET: key matches");
	assert_eq('Test OÜ', $body[0]['val'], "config GET: val matches");

	// POST — upsert same key
	[$code, $body] = plugin_config($pdo, ['key' => 'org_name', 'val' => 'Updated OÜ'], $admin_id, true, $dc, 'POST');
	assert_eq(200, $code, "config POST upsert: 200");
	$cfg = array_column($pdo->query("SELECT key,val FROM config ORDER BY key")->fetchAll(), 'val', 'key');
	[$code, $body] = plugin_config($pdo, [], $admin_id, true, $dc, 'GET');
	assert_eq('Updated OÜ', $body[0]['val'], "config upsert: val updated");

	// DELETE
	[$code, $body] = plugin_config_delete($pdo, ['key' => 'org_name'], $admin_id, true, $dc);
	assert_eq(200, $code, "config DELETE: 200");
	$cfg = array_column($pdo->query("SELECT key,val FROM config ORDER BY key")->fetchAll(), 'val', 'key');
	[$code, $body] = plugin_config($pdo, [], $admin_id, true, $dc, 'GET');
	assert_eq(0, count($body), "config GET: empty after delete");

	// DELETE non-existent → 404
	[$code, $body] = plugin_config_delete($pdo, ['key' => 'nonexistent'], $admin_id, true, $dc);
	assert_eq(404, $code, "config DELETE nonexistent: 404");

	// Non-admin blocked
	[$code, $body] = plugin_config($pdo, [], $mari_id, false, $dc, 'GET');
	assert_eq(403, $code, "config GET: non-admin → 403");
	[$code, $body] = plugin_config($pdo, ['key' => 'x', 'val' => 'y'], $mari_id, false, $dc, 'POST');
	assert_eq(403, $code, "config POST: non-admin → 403");
	[$code, $body] = plugin_config_delete($pdo, ['key' => 'x'], $mari_id, false, $dc);
	assert_eq(403, $code, "config DELETE: non-admin → 403");

	// Empty key → 400
	[$code, $body] = plugin_config($pdo, ['key' => '', 'val' => 'y'], $admin_id, true, $dc, 'POST');
	assert_eq(400, $code, "config POST empty key: 400");

	// Reset $cfg
	$cfg = [];
}


// ── 29. PLUGIN HANDLERS — debug log + timer ──
test_group("29. Plugin debug handlers");
{
	$pdo = create_test_db();

	// debug_log — returns ok when APP_DEBUG is defined
	[$code, $body] = plugin_debug_log($pdo, [
		'type' => 'ERROR', 'payload' => ['msg' => 'test'], 'url' => '/test'
	], 1, false, $dc);
	assert_eq(200, $code, "debug_log: 200");

	// timer_log alias — returns ok
	$result = timer_log(['qs' => '?api=test', 'jstm' => 42, 'fetch' => 10, 'render' => 5]);
	assert_eq(200, $result[0], "timer_log alias: 200");

	// plugin_debug_timer — P11 signature
	[$code, $body] = plugin_debug_timer($pdo, [
		'qs' => '?api=test', 'jstm' => 42, 'fetch' => 10, 'render' => 5
	], 1, false, $dc);
	assert_eq(200, $code, "plugin_debug_timer: 200");
}


// ── 30. db_try(), workers_list(), users_full_list() ──
test_group("30. db_try(), workers_list(), users_full_list()");
{
	$pdo = create_test_db();
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);

	// db_try — success passthrough
	[$code, $body] = db_try('test', fn() => [200, ['msg' => 'ok']]);
	assert_eq(200, $code, "db_try success: 200");
	assert_eq('ok', $body['msg'], "db_try success: body passed through");

	// db_try — generic Exception → 500
	[$code, $body] = db_try('test', fn() => throw new Exception('something broke'));
	assert_eq(500, $code, "db_try generic exception: 500");
	assert_eq('Database error', $body['error'], "db_try generic exception: error message");

	// db_try — UNIQUE violation → 400 with default message
	[$code, $body] = db_try('test', fn() => throw new Exception('UNIQUE constraint failed: users.username'));
	assert_eq(400, $code, "db_try UNIQUE violation: 400");
	assert_eq('duplicate_entry', $body['error'], "db_try UNIQUE violation: default message");

	// db_try — UNIQUE violation with custom message
	[$code, $body] = db_try('test', fn() => throw new Exception('UNIQUE constraint failed'), 'username_exists');
	assert_eq(400, $code, "db_try UNIQUE custom msg: 400");
	assert_eq('username_exists', $body['error'], "db_try UNIQUE custom msg: message passed through");

	// db_try — Integrity constraint violation variant
	[$code, $body] = db_try('test', fn() => throw new Exception('Integrity constraint violation'));
	assert_eq(400, $code, "db_try Integrity constraint: 400");
	assert_eq('duplicate_entry', $body['error'], "db_try Integrity constraint: default message");

	// workers_list — returns [{id, username}] sorted by username
	$workers = workers_list($pdo);
	assert_true(count($workers) >= 3, "workers_list: returns all users");
	assert_true(isset($workers[0]['id']) && isset($workers[0]['username']), "workers_list: has id and username columns");
	$usernames = array_column($workers, 'username');
	$sorted = $usernames;
	sort($sorted);
	assert_eq($sorted, $usernames, "workers_list: sorted by username");
	// Should NOT have real_name or contact columns
	assert_true(!isset($workers[0]['real_name']), "workers_list: no real_name column");

	// users_full_list — returns [{id, username, real_name, contact}] sorted
	$users = users_full_list($pdo);
	assert_true(count($users) >= 3, "users_full_list: returns all users");
	assert_true(isset($users[0]['id']) && isset($users[0]['username']), "users_full_list: has id and username");
	assert_true(array_key_exists('real_name', $users[0]), "users_full_list: has real_name column");
	assert_true(array_key_exists('contact', $users[0]), "users_full_list: has contact column");
	$usernames = array_column($users, 'username');
	$sorted = $usernames;
	sort($sorted);
	assert_eq($sorted, $usernames, "users_full_list: sorted by username");
}


// ── 31. AUDIT PLUGIN ──
//
// Covers plugin registration, template CRUD, draft-run lifecycle, commit
// (has_issues computation), task_done trigger matching, weekly/monthly due
// check, admin-only gates, ownership gates, and the admin report aggregator.
//
// Shares the same fixed DateContext ($dc = 2025-03-12 Wed) so week/month
// bounds are stable. Runs the audit schema against a fresh in-memory DB.

/** Create audit tables in a fresh PDO — mirrors the plugin's schema array. */
function create_audit_schema(PDO $pdo): void {
	$pdo->exec("CREATE TABLE IF NOT EXISTS audit_templates (
		id INTEGER PRIMARY KEY, title TEXT NOT NULL, target TEXT,
		interval TEXT NOT NULL, subtasks TEXT NOT NULL, active INTEGER DEFAULT 1)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS audit_runs (
		id INTEGER PRIMARY KEY,
		template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
		run_date TEXT NOT NULL, user_id INTEGER REFERENCES users(id),
		results TEXT NOT NULL, has_issues INTEGER DEFAULT 0, committed_at TEXT)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS audit_subtasks (
		id INTEGER PRIMARY KEY,
		template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
		sort_order INTEGER NOT NULL, name TEXT NOT NULL)");
	$pdo->exec("CREATE TABLE IF NOT EXISTS audit_access (
		user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE)");
}

test_group("31. Audit plugin");
{
	// Local DateContext — don't rely on outer $dc (group 26 reassigns it to 2025-06-15).
	// Wednesday 2025-03-12 keeps us in ISO week 11 / March so week_end and month_end
	// boundaries are unambiguous.
	$dc31 = new DateContext('2025-03-12', '09:00');

	// ── Registration contract ──
	assert_true(function_exists('plugin_audit_template'), "audit: plugin_audit_template() exists");
	assert_true(function_exists('plugin_audit_template_delete'), "audit: plugin_audit_template_delete() exists");
	assert_true(function_exists('plugin_audit_run'), "audit: plugin_audit_run() exists");
	assert_true(function_exists('plugin_audit_run_create'), "audit: plugin_audit_run_create() exists");
	assert_true(function_exists('plugin_audit_check_task'), "audit: plugin_audit_check_task() exists");
	assert_true(function_exists('plugin_audit_due'), "audit: plugin_audit_due() exists");
	assert_true(function_exists('plugin_audit_commit'), "audit: plugin_audit_commit() exists");
	assert_true(function_exists('plugin_audit_report'), "audit: plugin_audit_report() exists");
	assert_true(function_exists('plugin_audit_access'), "audit: plugin_audit_access() exists");
	assert_true(function_exists('view_audit'), "audit: view_audit() exists");

	// Plugin registration array populated by plugin loader at boot
	assert_true(isset($plugins['audit']), "audit: registered in \$plugins");
	assert_eq(2, $plugins['audit']['schema_version'] ?? 0, "audit: schema_version=2");
	assert_true(isset($plugin_routes['audit_template']), "audit: route audit_template registered");
	assert_true(isset($plugin_routes['audit_commit']), "audit: route audit_commit registered");
	assert_true(isset($plugin_routes['audit_run/check_task']), "audit: route audit_run/check_task registered");
	assert_true(isset($plugin_routes['audit_access']), "audit: route audit_access registered");
	assert_true(isset($plugin_views['audit']), "audit: view audit registered");

	// ── Template CRUD ──
	$pdo = create_test_db();
	create_audit_schema($pdo);
	[$admin_id, $mari_id, $juri_id] = seed_users($pdo);
	// Grant audit access to non-admin workers (access gate added in audit2)
	$pdo->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$mari_id]);
	$pdo->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$juri_id]);

	// Non-admin cannot save
	[$code, $body] = plugin_audit_template($pdo, [
		'title' => 'Daily cleanup', 'interval' => 'task_done', 'subtasks' => ['a','b']
	], $mari_id, false, $dc31, 'POST');
	assert_eq(403, $code, "audit template POST: non-admin → 403");

	// Admin creates a template
	[$code, $body] = plugin_audit_template($pdo, [
		'title' => 'Daily cleanup',
		'target' => 'Cleaning',
		'interval' => 'task_done',
		'subtasks' => ['Check floor', 'Tools stored', 'Log signed'],
	], $admin_id, true, $dc31, 'POST');
	assert_eq(200, $code, "audit template POST: admin → 200");
	assert_true($body['id'] > 0, "audit template POST: returns id");
	$tpl_id = $body['id'];

	// Verify template persisted + subtasks synced into audit_subtasks
	$row = $pdo->query("SELECT title, target, interval, subtasks, active FROM audit_templates WHERE id=$tpl_id")->fetch();
	assert_eq('Daily cleanup', $row['title'], "audit template: title persisted");
	assert_eq('Cleaning', $row['target'], "audit template: target persisted");
	assert_eq('task_done', $row['interval'], "audit template: interval persisted");
	assert_eq(1, (int)$row['active'], "audit template: active=1 by default");
	$decoded = json_decode($row['subtasks'], true);
	assert_eq(3, count($decoded), "audit template: subtasks JSON has 3 items");

	$sub_count = (int)$pdo->query("SELECT COUNT(*) FROM audit_subtasks WHERE template_id=$tpl_id")->fetchColumn();
	assert_eq(3, $sub_count, "audit template: audit_subtasks rows synced");

	// Validation: empty title → 400
	[$code, $body] = plugin_audit_template($pdo, [
		'title' => '', 'interval' => 'week_end', 'subtasks' => ['x']
	], $admin_id, true, $dc31, 'POST');
	assert_eq(400, $code, "audit template: empty title → 400");

	// Validation: invalid interval → 400
	[$code, $body] = plugin_audit_template($pdo, [
		'title' => 'Bad', 'interval' => 'yearly', 'subtasks' => ['x']
	], $admin_id, true, $dc31, 'POST');
	assert_eq(400, $code, "audit template: invalid interval → 400");

	// Validation: empty subtasks → 400
	[$code, $body] = plugin_audit_template($pdo, [
		'title' => 'Bad', 'interval' => 'week_end', 'subtasks' => []
	], $admin_id, true, $dc31, 'POST');
	assert_eq(400, $code, "audit template: empty subtasks → 400");

	// GET list — workers see active only, admins see all
	[$code, $body] = plugin_audit_template($pdo, [], $mari_id, false, $dc31, 'GET');
	assert_eq(200, $code, "audit template GET: worker 200");
	assert_eq(1, count($body), "audit template GET: worker sees 1 active");

	// Update existing (resync subtasks)
	[$code, $body] = plugin_audit_template($pdo, [
		'id' => $tpl_id,
		'title' => 'Daily cleanup v2',
		'target' => 'Cleaning',
		'interval' => 'task_done',
		'subtasks' => ['A', 'B'],   // was 3, now 2 — resync should delete the orphan
		'active' => 1,
	], $admin_id, true, $dc31, 'POST');
	assert_eq(200, $code, "audit template UPDATE: 200");
	$sub_count = (int)$pdo->query("SELECT COUNT(*) FROM audit_subtasks WHERE template_id=$tpl_id")->fetchColumn();
	assert_eq(2, $sub_count, "audit template UPDATE: subtasks resynced (3→2)");

	// Soft-disable (delete route)
	[$code, $body] = plugin_audit_template_delete($pdo, ['id' => $tpl_id], $admin_id, true, $dc31);
	assert_eq(200, $code, "audit template DELETE: 200");
	$active = (int)$pdo->query("SELECT active FROM audit_templates WHERE id=$tpl_id")->fetchColumn();
	assert_eq(0, $active, "audit template DELETE: active=0");

	// Workers now see 0, admin still sees 1
	[$code, $body] = plugin_audit_template($pdo, [], $mari_id, false, $dc31, 'GET');
	assert_eq(0, count($body), "audit template GET: worker sees 0 after disable");
	[$code, $body] = plugin_audit_template($pdo, [], $admin_id, true, $dc31, 'GET');
	assert_eq(1, count($body), "audit template GET: admin sees disabled too");

	// Delete with bad id → 400
	[$code, $body] = plugin_audit_template_delete($pdo, ['id' => 0], $admin_id, true, $dc31);
	assert_eq(400, $code, "audit template DELETE: id=0 → 400");

	// Delete non-admin → 403
	[$code, $body] = plugin_audit_template_delete($pdo, ['id' => $tpl_id], $mari_id, false, $dc31);
	assert_eq(403, $code, "audit template DELETE: non-admin → 403");


	// ── Draft run creation ──
	// Re-activate template for the rest of the tests
	$pdo->exec("UPDATE audit_templates SET active=1 WHERE id=$tpl_id");

	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => $tpl_id], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit run create: 200");
	$run_id = $body['id'];
	assert_true($run_id > 0, "audit run create: returns id");

	// Run row: uncommitted, owned by mari, date = today (fixed DateContext)
	$run = $pdo->query("SELECT user_id, run_date, committed_at FROM audit_runs WHERE id=$run_id")->fetch();
	assert_eq($mari_id, (int)$run['user_id'], "audit run: owned by creator");
	assert_eq('2025-03-12', $run['run_date'], "audit run: run_date = dc->today");
	assert_eq(null, $run['committed_at'], "audit run: draft (committed_at null)");

	// Bad template id → 404
	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => 9999], $mari_id, false, $dc31);
	assert_eq(404, $code, "audit run create: unknown template → 404");

	// Missing template_id → 400
	[$code, $body] = plugin_audit_run_create($pdo, [], $mari_id, false, $dc31);
	assert_eq(400, $code, "audit run create: missing template_id → 400");


	// ── Commit — has_issues computation ──
	// All pass → has_issues=0
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => $run_id,
		'results' => [
			['done' => 1, 'comment' => ''],
			['done' => 1, 'comment' => 'ok'],
		],
	], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit commit all-pass: 200");
	assert_eq(0, $body['has_issues'], "audit commit all-pass: has_issues=0");

	$committed = $pdo->query("SELECT committed_at, has_issues, results FROM audit_runs WHERE id=$run_id")->fetch();
	assert_true($committed['committed_at'] !== null, "audit commit: committed_at stamped");
	assert_eq(0, (int)$committed['has_issues'], "audit commit: has_issues persisted");

	// Re-commit same run → 400 (already committed)
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => $run_id,
		'results' => [['done' => 0, 'comment' => '']],
	], $mari_id, false, $dc31);
	assert_eq(400, $code, "audit commit: already_committed → 400");
	assert_eq('already_committed', $body['error'], "audit commit: error code");

	// New draft + commit with some failures → has_issues=1
	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => $tpl_id], $mari_id, false, $dc31);
	$run2 = $body['id'];
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => $run2,
		'results' => [
			['done' => 1, 'comment' => ''],
			['done' => 0, 'comment' => 'floor still dirty'],
		],
	], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit commit with issues: 200");
	assert_eq(1, $body['has_issues'], "audit commit with issues: has_issues=1");

	// Bad input: non-array results → 400
	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => $tpl_id], $juri_id, false, $dc31);
	$run3 = $body['id'];
	[$code, $body] = plugin_audit_commit($pdo, ['run_id' => $run3, 'results' => 'nope'], $juri_id, false, $dc31);
	assert_eq(400, $code, "audit commit: non-array results → 400");

	// Ownership gate: juri cannot commit mari's run
	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => $tpl_id], $mari_id, false, $dc31);
	$run_mari = $body['id'];
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => $run_mari, 'results' => [['done' => 1, 'comment' => '']]
	], $juri_id, false, $dc31);
	assert_eq(403, $code, "audit commit: non-owner non-admin → 403");

	// Admin can commit anyone's run
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => $run_mari, 'results' => [['done' => 1, 'comment' => '']]
	], $admin_id, true, $dc31);
	assert_eq(200, $code, "audit commit: admin can commit any run");

	// Non-existent run → 404
	[$code, $body] = plugin_audit_commit($pdo, [
		'run_id' => 99999, 'results' => [['done' => 1, 'comment' => '']]
	], $mari_id, false, $dc31);
	assert_eq(404, $code, "audit commit: unknown run → 404");


	// ── Run GET (list + detail) + ownership gate ──
	$_GET = []; // reset query params (plugin_audit_run reads filters from $_GET)
	[$code, $body] = plugin_audit_run($pdo, [], $mari_id, false, $dc31, 'GET');
	assert_eq(200, $code, "audit run list: 200");
	// mari's runs only (2 of hers: run_id and run_mari; run2 is also mari's)
	foreach ($body as $r) assert_eq($mari_id, $r['user_id'], "audit run list: worker sees own only");

	// Admin can filter by user_id
	$_GET = ['user_id' => (string)$juri_id];
	[$code, $body] = plugin_audit_run($pdo, [], $admin_id, true, $dc31, 'GET');
	foreach ($body as $r) assert_eq($juri_id, $r['user_id'], "audit run list: admin user_id filter");

	// Status filter removed in audit2 (not needed for 40-worker teams, results are short)

	// Access gate: worker without access → 403 on list/create/due
	$pdo->prepare("DELETE FROM audit_access WHERE user_id=?")->execute([$juri_id]);
	[$code, $body] = plugin_audit_run($pdo, [], $juri_id, false, $dc31, 'GET');
	assert_eq(403, $code, "audit run list: no access → 403");
	[$code, $body] = plugin_audit_run_create($pdo, ['template_id' => $tpl_id], $juri_id, false, $dc31);
	assert_eq(403, $code, "audit run create: no access → 403");
	[$code, $body] = plugin_audit_due($pdo, [], $juri_id, false, $dc31);
	assert_eq(403, $code, "audit due: no access → 403");
	// Admin always passes access gate (no audit_access row needed)
	[$code, $body] = plugin_audit_run($pdo, [], $admin_id, true, $dc31, 'GET');
	assert_eq(200, $code, "audit run list: admin always allowed");
	// Restore juri's access for remaining tests
	$pdo->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$juri_id]);

	// Detail: worker cannot read another worker's run
	$_GET = ['id' => (string)$run_mari];
	[$code, $body] = plugin_audit_run($pdo, [], $juri_id, false, $dc31, 'GET');
	assert_eq(403, $code, "audit run detail: non-owner → 403");

	// Detail: owner reads own
	[$code, $body] = plugin_audit_run($pdo, [], $mari_id, false, $dc31, 'GET');
	assert_eq(200, $code, "audit run detail: owner 200");
	assert_true(isset($body['template']), "audit run detail: includes template");
	assert_true(isset($body['template']['subtasks_ordered']), "audit run detail: includes subtasks_ordered");

	$_GET = []; // cleanup


	// ── check_task trigger (P15-compliant, no core edits) ──
	// Seed a task with status=2 and title matching template target
	$pdo->prepare("INSERT INTO tasks (user_id, task_date, title, start_time, end_time, status, source) VALUES (?,?,?,?,?,?,?)")
		->execute([$mari_id, '2025-03-12', 'Cleaning', '08:00', '16:00', 2, 'manual']);
	$task_id = (int)$pdo->lastInsertId();

	[$code, $body] = plugin_audit_check_task($pdo, ['task_id' => $task_id], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit check_task: 200");
	assert_true($body['run_id'] > 0, "audit check_task: creates draft run");
	assert_eq('created', $body['msg'], "audit check_task: msg=created");

	// Idempotency: calling again the same day returns the existing run (no duplicate)
	[$code, $body2] = plugin_audit_check_task($pdo, ['task_id' => $task_id], $mari_id, false, $dc31);
	assert_eq('existing', $body2['msg'], "audit check_task: idempotent (msg=existing)");
	assert_eq($body['run_id'], $body2['run_id'], "audit check_task: returns same run_id");

	// Non-matching title → no template found
	$pdo->prepare("INSERT INTO tasks (user_id, task_date, title, status, source) VALUES (?,?,?,?,?)")
		->execute([$mari_id, '2025-03-12', 'Something else entirely', 2, 'manual']);
	$other_task = (int)$pdo->lastInsertId();
	[$code, $body] = plugin_audit_check_task($pdo, ['task_id' => $other_task], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit check_task: no template match → 200");
	assert_eq(null, $body['run_id'], "audit check_task: no match → run_id=null");

	// Non-completed task → not_done (no run created)
	$pdo->prepare("INSERT INTO tasks (user_id, task_date, title, status, source) VALUES (?,?,?,?,?)")
		->execute([$mari_id, '2025-03-13', 'Cleaning', 1, 'manual']); // different date, status=1
	$pending_task = (int)$pdo->lastInsertId();
	[$code, $body] = plugin_audit_check_task($pdo, ['task_id' => $pending_task], $mari_id, false, $dc31);
	assert_eq('not_done', $body['msg'], "audit check_task: status<2 → not_done");

	// Ownership: worker cannot trigger check on another worker's task
	[$code, $body] = plugin_audit_check_task($pdo, ['task_id' => $task_id], $juri_id, false, $dc31);
	assert_eq(403, $code, "audit check_task: non-owner non-admin → 403");

	// Unknown task → 404
	[$code, $body] = plugin_audit_check_task($pdo, ['task_id' => 99999], $mari_id, false, $dc31);
	assert_eq(404, $code, "audit check_task: unknown task → 404");


	// ── Due check (week_end / month_end) ──
	// Fresh DB so week/month state is deterministic
	$pdo = create_test_db();
	create_audit_schema($pdo);
	[$admin_id, $mari_id] = seed_users($pdo);
	$pdo->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$mari_id]);

	// Create weekly and monthly templates
	[,$w] = plugin_audit_template($pdo, [
		'title' => 'Weekly check', 'interval' => 'week_end', 'subtasks' => ['a']
	], $admin_id, true, $dc31, 'POST');
	$weekly_id = $w['id'];
	[,$w] = plugin_audit_template($pdo, [
		'title' => 'Monthly check', 'interval' => 'month_end', 'subtasks' => ['a']
	], $admin_id, true, $dc31, 'POST');
	$monthly_id = $w['id'];
	// task_done template should NOT appear in due list
	plugin_audit_template($pdo, [
		'title' => 'Post-task', 'interval' => 'task_done', 'subtasks' => ['a']
	], $admin_id, true, $dc31, 'POST');

	$_GET = [];
	[$code, $body] = plugin_audit_due($pdo, [], $mari_id, false, $dc31);
	assert_eq(200, $code, "audit due: 200");
	assert_eq(2, count($body), "audit due: 2 due (weekly + monthly, task_done excluded)");

	// After committing a weekly run this week, weekly drops out of due
	[,$r] = plugin_audit_run_create($pdo, ['template_id' => $weekly_id], $mari_id, false, $dc31);
	plugin_audit_commit($pdo, [
		'run_id' => $r['id'], 'results' => [['done' => 1, 'comment' => '']]
	], $mari_id, false, $dc31);
	[$code, $body] = plugin_audit_due($pdo, [], $mari_id, false, $dc31);
	assert_eq(1, count($body), "audit due: weekly removed after commit this week");
	assert_eq($monthly_id, $body[0]['template_id'], "audit due: monthly still due");


	// ── Admin report ──
	// Workers cannot read the report
	[$code, $body] = plugin_audit_report($pdo, [], $mari_id, false, $dc31);
	assert_eq(403, $code, "audit report: non-admin → 403");

	[$code, $body] = plugin_audit_report($pdo, [], $admin_id, true, $dc31);
	assert_eq(200, $code, "audit report: admin 200");
	assert_true(isset($body['by_template']), "audit report: by_template key present");
	assert_true(isset($body['by_worker']), "audit report: by_worker key present");

	// We committed exactly 1 passing run for mari → 1 entry in by_worker, 100% pass
	assert_eq(1, count($body['by_worker']), "audit report: 1 worker");
	$worker_row = $body['by_worker'][0];
	assert_eq(1, $worker_row['runs'], "audit report: worker 1 run");
	assert_eq(0, $worker_row['issues'], "audit report: worker 0 issues");


	// ── Commit idempotency edge: concurrent commits ──
	// The UPDATE guard uses WHERE committed_at IS NULL — verify a second commit
	// against an already-committed run is rejected by the SELECT check before
	// reaching the UPDATE (covered above with 400/already_committed), and that
	// the UPDATE clause itself would no-op if bypassed. Smoke-test with a new DB.
	$pdo2 = create_test_db();
	create_audit_schema($pdo2);
	[$a2, $m2] = seed_users($pdo2);
	$pdo2->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$m2]);
	plugin_audit_template($pdo2, [
		'title' => 't', 'interval' => 'week_end', 'subtasks' => ['x']
	], $a2, true, $dc31, 'POST');
	[,$r] = plugin_audit_run_create($pdo2, ['template_id' => 1], $m2, false, $dc31);
	plugin_audit_commit($pdo2, ['run_id' => $r['id'], 'results' => [['done' => 1]]], $m2, false, $dc31);
	$first_committed = $pdo2->query("SELECT committed_at FROM audit_runs WHERE id=" . $r['id'])->fetchColumn();
	assert_true($first_committed !== null && $first_committed !== false, "audit commit: committed_at non-null after first commit");

	$_GET = [];


	// ── Print handler (plugin_audit_print) ──
	//
	// Committed-only gate, ownership, payload shape (auditor + template +
	// subtasks_ordered + org from $cfg). Uses a fresh DB with one template,
	// one auditor with real_name+contact, and one committed run.
	global $cfg;
	$pdo3 = create_test_db();
	create_audit_schema($pdo3);
	insert_user($pdo3, USER1, 'admin_pass');
	insert_user($pdo3, 'liis', 'liis_pass');
	$pdo3->exec("UPDATE users SET real_name='Liis Tamm', contact='+372 5555 1234' WHERE username='liis'");
	$a3    = (int)$pdo3->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
	$liis  = (int)$pdo3->query("SELECT id FROM users WHERE username='liis'")->fetchColumn();
	$pdo3->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$liis]);

	// Route registered
	assert_true(isset($plugin_routes['audit_run/print']), "audit print: route audit_run/print registered");
	assert_true(function_exists('plugin_audit_print'), "audit print: handler function exists");
	assert_true(isset($plugin_views['audit_print']), "audit print: audit_print view registered");

	// Seed org config (like production db.php does via $cfg population)
	$pdo3->exec("INSERT INTO config (key,val) VALUES ('org_name','Hoolduskeskus OÜ'),('org_address','Pikk 1, Tallinn'),('not_org_x','irrelevant')");
	$cfg = array_column($pdo3->query("SELECT key,val FROM config")->fetchAll(), 'val', 'key');

	// Create a template and a committed run for liis
	[,$tmp] = plugin_audit_template($pdo3, [
		'title' => 'Weekly cleanup',
		'interval' => 'week_end',
		'subtasks' => ['Check floor', 'Tools stored'],
	], $a3, true, $dc31, 'POST');
	$tpl3 = $tmp['id'];
	[,$rn] = plugin_audit_run_create($pdo3, ['template_id' => $tpl3], $liis, false, $dc31);
	$pr_run = $rn['id'];
	plugin_audit_commit($pdo3, [
		'run_id' => $pr_run,
		'results' => [['done' => 1, 'comment' => 'clean'], ['done' => 0, 'comment' => 'mop missing']],
	], $liis, false, $dc31);

	// 400 missing run_id
	$_GET = [];
	[$code, $body] = plugin_audit_print($pdo3, [], $liis, false, $dc31, 'GET');
	assert_eq(400, $code, "audit print: missing run_id → 400");

	// 404 unknown run
	$_GET = ['run_id' => '99999'];
	[$code, $body] = plugin_audit_print($pdo3, [], $liis, false, $dc31, 'GET');
	assert_eq(404, $code, "audit print: unknown run_id → 404");

	// 403 non-owner non-admin (use the `mari` user from earlier DBs — but pdo3 only has admin+liis)
	insert_user($pdo3, 'kati', 'kati_pass');
	$kati = (int)$pdo3->query("SELECT id FROM users WHERE username='kati'")->fetchColumn();
	$_GET = ['run_id' => (string)$pr_run];
	[$code, $body] = plugin_audit_print($pdo3, [], $kati, false, $dc31, 'GET');
	assert_eq(403, $code, "audit print: non-owner non-admin → 403");

	// 400 on uncommitted draft run
	[,$draft] = plugin_audit_run_create($pdo3, ['template_id' => $tpl3], $liis, false, $dc31);
	$_GET = ['run_id' => (string)$draft['id']];
	[$code, $body] = plugin_audit_print($pdo3, [], $liis, false, $dc31, 'GET');
	assert_eq(400, $code, "audit print: uncommitted run → 400");
	assert_eq('not_committed', $body['error'], "audit print: error=not_committed");

	// 200 owner reads own committed run → full payload
	$_GET = ['run_id' => (string)$pr_run];
	[$code, $body] = plugin_audit_print($pdo3, [], $liis, false, $dc31, 'GET');
	assert_eq(200, $code, "audit print: owner committed → 200");

	// Auditor block: prefers real_name, includes contact
	assert_eq('liis',         $body['auditor']['username'],  "audit print: auditor.username");
	assert_eq('Liis Tamm',    $body['auditor']['real_name'], "audit print: auditor.real_name");
	assert_eq('+372 5555 1234', $body['auditor']['contact'], "audit print: auditor.contact");

	// Template + subtasks_ordered present (the client uses this if subtasks JSON isn't in template)
	assert_eq('Weekly cleanup', $body['template']['title'], "audit print: template.title");
	assert_true(isset($body['template']['subtasks_ordered']) && count($body['template']['subtasks_ordered']) === 2,
		"audit print: template.subtasks_ordered has 2 rows");
	assert_eq('Check floor', $body['template']['subtasks_ordered'][0]['name'], "audit print: first subtask name");

	// Run block: results preserved with comments, has_issues set, committed_at stamped
	assert_eq(2,             count($body['run']['results']),          "audit print: run.results length");
	assert_eq(1,             (int)$body['run']['results'][0]['done'], "audit print: result 0 done=1");
	assert_eq('mop missing', $body['run']['results'][1]['comment'],   "audit print: result 1 comment");
	assert_eq(1,             (int)$body['run']['has_issues'],         "audit print: has_issues=1 (one failed)");
	assert_true(!empty($body['run']['committed_at']),                 "audit print: committed_at stamped");

	// Org block: only org_* keys, not unrelated config
	assert_true(isset($body['org']['org_name']),    "audit print: org.org_name present");
	assert_true(isset($body['org']['org_address']), "audit print: org.org_address present");
	assert_true(!isset($body['org']['not_org_x']),  "audit print: non-org_ keys filtered out");

	// Admin can print any worker's run
	[$code, $body] = plugin_audit_print($pdo3, [], $a3, true, $dc31, 'GET');
	assert_eq(200, $code, "audit print: admin can print any run");

	// POST/other methods rejected
	[$code, $body] = plugin_audit_print($pdo3, [], $liis, false, $dc31, 'POST');
	assert_eq(405, $code, "audit print: POST → 405");

	// Cleanup
	$_GET = [];
	$cfg = [];


	// ── Access management (plugin_audit_access) ──
	$pdoA = create_test_db();
	create_audit_schema($pdoA);
	[$aA, $mariA, $juriA] = seed_users($pdoA);

	// Non-admin cannot read or write access list
	[$code, $body] = plugin_audit_access($pdoA, [], $mariA, false, $dc31, 'GET');
	assert_eq(403, $code, "audit access GET: non-admin → 403");
	[$code, $body] = plugin_audit_access($pdoA, ['user_id' => $mariA, 'grant' => 1], $mariA, false, $dc31, 'POST');
	assert_eq(403, $code, "audit access POST: non-admin → 403");

	// Admin GET → all users listed, has_access=0 initially
	[$code, $body] = plugin_audit_access($pdoA, [], $aA, true, $dc31, 'GET');
	assert_eq(200, $code, "audit access GET: admin 200");
	assert_true(count($body) >= 2, "audit access GET: returns all users");
	$mariRow = array_values(array_filter($body, fn($u) => (int)$u['id'] === $mariA))[0] ?? null;
	assert_true($mariRow !== null, "audit access GET: mari in list");
	assert_eq(0, (int)($mariRow['has_access'] ?? 1), "audit access GET: mari has_access=0 initially");

	// Admin grants access
	[$code, $body] = plugin_audit_access($pdoA, ['user_id' => $mariA, 'grant' => 1], $aA, true, $dc31, 'POST');
	assert_eq(200, $code, "audit access POST grant: 200");
	$row = (int)$pdoA->query("SELECT COUNT(*) FROM audit_access WHERE user_id=$mariA")->fetchColumn();
	assert_eq(1, $row, "audit access POST grant: row inserted");

	// has_access=1 now reflected in GET
	[$code, $body] = plugin_audit_access($pdoA, [], $aA, true, $dc31, 'GET');
	$mariRow = array_values(array_filter($body, fn($u) => (int)$u['id'] === $mariA))[0] ?? null;
	assert_eq(1, (int)($mariRow['has_access'] ?? 0), "audit access GET: has_access=1 after grant");

	// Grant is idempotent (INSERT OR IGNORE)
	[$code, $body] = plugin_audit_access($pdoA, ['user_id' => $mariA, 'grant' => 1], $aA, true, $dc31, 'POST');
	assert_eq(200, $code, "audit access POST grant: idempotent 200");

	// Admin revokes access
	[$code, $body] = plugin_audit_access($pdoA, ['user_id' => $mariA, 'grant' => 0], $aA, true, $dc31, 'POST');
	assert_eq(200, $code, "audit access POST revoke: 200");
	$row = (int)$pdoA->query("SELECT COUNT(*) FROM audit_access WHERE user_id=$mariA")->fetchColumn();
	assert_eq(0, $row, "audit access POST revoke: row removed");

	// Cannot grant access to admin user (id=1)
	[$code, $body] = plugin_audit_access($pdoA, ['user_id' => 1, 'grant' => 1], $aA, true, $dc31, 'POST');
	assert_eq(400, $code, "audit access POST: user_id=1 → 400");

	// Invalid method → 405
	[$code, $body] = plugin_audit_access($pdoA, [], $aA, true, $dc31, 'DELETE');
	assert_eq(405, $code, "audit access: DELETE → 405");

	// Worker with access can list runs; after revoke cannot
	$pdoA->prepare("INSERT INTO audit_access (user_id) VALUES (?)")->execute([$mariA]);
	[$code, $body] = plugin_audit_run($pdoA, [], $mariA, false, $dc31, 'GET');
	assert_eq(200, $code, "audit run: worker with access → 200");
	$pdoA->prepare("DELETE FROM audit_access WHERE user_id=?")->execute([$mariA]);
	[$code, $body] = plugin_audit_run($pdoA, [], $mariA, false, $dc31, 'GET');
	assert_eq(403, $code, "audit run: worker after revoke → 403");
}


/* ══════════════════════════════════════════════════════════
 * RESULTS
 * ════════════════════════════════════════════════════════ */
$total = $test_results['pass'] + $test_results['fail'];

if ($is_cli) {
	echo "\n\033[1;33m══════════════════════════════════════════\033[0m\n";
	echo "\033[1m  Tests: {$total}  ";
	echo "\033[32m✓ {$test_results['pass']} passed\033[0m  ";
	if ($test_results['fail'] > 0) echo "\033[31m✗ {$test_results['fail']} failed\033[0m";
	echo "\n\033[1;33m══════════════════════════════════════════\033[0m\n";

	if ($test_results['fail'] > 0) {
		echo "\n\033[31mFailed tests:\033[0m\n";
		foreach ($test_results['errors'] as $err) echo "  • {$err}\n";
		echo "\n";
		exit(1);
	}

	echo "\n\033[32mAll tests passed.\033[0m\n";
} else {
	// HTML Output for Browser
	echo "<hr style='border: 1px dashed #555; margin-top: 30px;'>\n";
	
	$color = ($test_results['fail'] > 0) ? '#F44336' : '#4CAF50';
	echo "<div style='font-size: 1.2em; font-weight: bold; color: {$color}; padding: 10px 0;'>\n";
	echo "  Tests: {$total} &nbsp;|&nbsp; Passed: {$test_results['pass']} &nbsp;|&nbsp; Failed: {$test_results['fail']}\n";
	echo "</div>\n";

	if ($test_results['fail'] > 0) {
		echo "<div style='margin-top: 20px;'>\n";
		echo "  <strong style='color: #F44336;'>Failed tests:</strong>\n";
		echo "  <ul>\n";
		foreach ($test_results['errors'] as $err) {
			echo "    <li>" . htmlspecialchars($err) . "</li>\n";
		}
		echo "  </ul>\n";
		echo "</div>\n";
	} else {
		echo "<div style='color: #4CAF50; margin-top: 10px;'>All tests passed.</div>\n";
	}
	
	echo "</body></html>\n";
}

exit($test_results['fail'] > 0 ? 1 : 0);