<?php
// HELPER FUNCTIONS


/** Gets accurate IP, handling Proxies/Cloudflare, and extracting the first IP if chained */
function get_ip(): string {
	return trim(strtok($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '', ','));
}

/** Checks if IP is allowed, and auto-cleans old database records */
function rl_ok(PDO $pdo, string $ip, int $max = 5): bool {
	$pdo->exec("DELETE FROM rl WHERE expire < " . time());
	$stmt = $pdo->prepare("SELECT fails FROM rl WHERE ip = ?");
	$stmt->execute([$ip]);
	return (int)$stmt->fetchColumn() < $max;
}

/** Records a failure using SQLite UPSERT */
function rl_fail(PDO $pdo, string $ip, int $sec = 300): void {
	$pdo->prepare("INSERT INTO rl (ip, fails, expire) VALUES (?, 1, ?) ON CONFLICT(ip) DO UPDATE SET fails = fails + 1, expire = excluded.expire")->execute([$ip, time() + $sec]);
}

/** Clears tracking on success */
function rl_clear(PDO $pdo, string $ip): void {
	$pdo->prepare("DELETE FROM rl WHERE ip = ?")->execute([$ip]);
}

/** Redirect and stop execution. Optionally append query string. */
function location_exit(string $rq=''): never {
	$base=strtok($_SERVER['REQUEST_URI'], '?');
	header("Location: " . $base . ($rq !== '' ? '?' . $rq : ''));
	exit;
}



/** Return JSON response and stop execution. */
function json_exit(array $data, int $status=200, int $sleep=0): never {
	if($sleep > 0)sleep($sleep);
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($data);
//timer_log() in plugins/debug.php
if(($_GET['api'] ?? '') !== 'debug_log/jstimer' && function_exists('timer_log')) timer_log(['qs'=>"?".$_SERVER['QUERY_STRING'], 'jstm'=>0]);
exit;
}



/** Read JSON from request body */
function json_input(): array {
	return json_decode(file_get_contents("php://input"), true) ?? [];
}

/** Check HH:MM format. Empty string allowed (optional times). */
function is_valid_time(string $t): bool {
	if ($t === '') return true;
	$r = date_parse_from_format('H:i', $t);
	return $r['error_count'] === 0 && $r['warning_count'] === 0;
}

/** Check YYYY-MM-DD format and valid calendar date. */
function is_valid_date(string $d): bool {
	$r = date_parse_from_format('Y-m-d', $d);
	if ($r['error_count'] > 0 || $r['warning_count'] > 0) return false;
	return checkdate($r['month'], $r['day'], $r['year']);
}
/**
 * Validate task fields from client. Returns null if valid, error string if not.
 * Used by tasks/save, tasks/batch, tasks/status.
 */
function validate_task(array $d, bool $need_title = true, bool $need_date = true): ?string {
	if ($need_title && (empty($d['title']) || trim($d['title']) === ''))
		return 'title is required';
	if ($need_date && (empty($d['task_date']) || !is_valid_date($d['task_date'])))
		return 'invalid date format (expected YYYY-MM-DD)';
	if (isset($d['start_time']) && !is_valid_time($d['start_time']))
		return 'invalid start_time (expected HH:MM)';
	if (isset($d['end_time']) && !is_valid_time($d['end_time']))
		return 'invalid end_time (expected HH:MM)';
	if (isset($d['status']) && !in_array((int)$d['status'], [0, 1, 2], true))
		return 'status must be 0, 1, or 2';
	return null;
}




/**
 * Shift a task's start time to "now" if the worker hasn't manually started it yet.
 *
 * When a worker clicks "Start", the planned times are replaced with:
 *	start_time=current clock time
 *	end_time	= current time + original planned duration
 *
 * If the times are already the current time, no shift occurs (prevents double-shift).
 *
 * @param string $v0  Planned start time (HH:MM).
 * @param string $v1  Planned end time (HH:MM).
 * @return array	[adjusted_start, adjusted_end]
 */
function timeShift(string $v0, string $v1): array {
	if(!$v0 || !$v1) return [$v0, $v1];
	$start=strtotime($v0); $end=strtotime($v1); $now=time();
	if($start && $end && date('H:i', $now) !== date('H:i', $start))
		return [date('H:i', $now), date('H:i', $now + ($end - $start))];
	return [$v0, $v1];
}



/** Translate status int to localized label array. */
function statusLabels(): array {
	return [0=>__('status_0'), 1=>__('status_1'), 2=>__('status_2')];
}



/** Button labels for progressive status (Start → Finish → Done). */
function btnLabels(): array {
	return [__('btn_start'), __('btn_finish'), __('status_2')];
}



/** Insert or update a task row. Shared by tasks/save and tasks/batch. */
function upsert_task(PDO $pdo, int $user_id, string $title, string $task_date, string $start_time, string $end_time, int $status, string $notes): void {
if (defined('APP_DEBUG') && APP_DEBUG) $t=hrtime(true);//DB_TIME
	$pdo->prepare("INSERT INTO tasks (user_id, title, task_date, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)
		ON CONFLICT(user_id, task_date, title) DO UPDATE SET start_time=excluded.start_time, end_time=excluded.end_time, status=excluded.status, notes=excluded.notes")
		->execute([$user_id, $title, $task_date, $start_time, $end_time, $status, $notes]);
if (defined('APP_DEBUG') && APP_DEBUG && function_exists('timer_log'))timer_log(['qs'=>'SQL:upsert_task','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME
}



/** Used in two places: the initial seed and users POST.
 * Returns rowCount: 1 if inserted, 0 if username already exists (IGNORE).
 * Relies on SQLite PDO returning 0 for ignored inserts — verified behaviour. */
function insert_user(PDO $pdo, string $username, string $password, string $real_name='', string $contact='',$force_pw_change=0): int {
	$stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password,real_name, contact, force_password_change) VALUES (?,?,?,?,?)");
	$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT),$real_name, $contact, $force_pw_change]);
// TODO: relying on rowCount() for INSERT OR IGNORE is SQLite-driver-dependent behavior.
	return $stmt->rowCount();
}



function known_titles(PDO $pdo): array {
// TODO: SELECT title FROM task_details UNION SELECT title FROM tasks ORDER BY titl
	return $pdo->query("SELECT title FROM task_details ORDER BY title")->fetchAll(PDO::FETCH_COLUMN);
}



/** Wrap a DB operation in try/catch. Returns [code, body] on success or [500, error] on failure.
 * Handles UNIQUE constraint violations as 400. Logs context tag for debugging.
 * @param string $unique_msg Error string returned on UNIQUE violation — callers can pass human-readable messages. */
function db_try(string $tag, callable $fn, string $unique_msg = 'duplicate_entry'): array {
	try { return $fn(); }
	catch (Exception $e) {
		if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Integrity constraint violation'))
			return [400, ['error' => $unique_msg]];
		error_log($tag . ': ' . $e->getMessage());
		return [500, ['error' => 'Database error']];
	}
}



/** Worker dropdown list: [{id, username}]. Used by rules and team views. */
function workers_list(PDO $pdo): array {
	return $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
}

/** Full user list: [{id, username, real_name, contact}]. Admin user management. */
function users_full_list(PDO $pdo): array {
	return $pdo->query("SELECT id, username, real_name, contact FROM users ORDER BY username")->fetchAll();
}



/** Full ISO weeks for a month: maps relative index (1-based) → [iso_week, monday, sunday].
 * Only ISO weeks with all 7 days inside the month are included.
 * Used by api_tasks_month (hints) and api_rules_generate (scheduling). */
function full_weeks_info(string $ym): array {
	$y =(int)substr($ym, 0, 4);
	$m =(int)substr($ym, 5, 2);
	$days_in_month = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
	$week_day_count = [];
	$week_first_day = [];
	for ($di = 1; $di <= $days_in_month; $di++) {
		$ts = mktime(12, 0, 0, $m, $di, $y);
		$w = (int)date('W', $ts);
		$week_day_count[$w] = ($week_day_count[$w] ?? 0) + 1;
		$week_first_day[$w] ??= $di;
	}
	$result = [];
	$rel = 1;
	foreach($week_day_count as $iso_w => $count) {
		if($count<7) continue;
		$mon_ts = mktime(12, 0, 0, $m, $week_first_day[$iso_w], $y);
		$sun_ts = mktime(12, 0, 0, $m, $week_first_day[$iso_w] + 6, $y);
	$result[$rel] = [
	'iso'	=>$iso_w,
	'from'	=>date('d', $mon_ts),
	'to'	=>date('d', $sun_ts),
		];
	$rel++;
	}
	return $result;
}



/** Fetch coworkers map: other users sharing same (title, task_date). */
function coworkers_map(PDO $pdo, int $exclude_uid, string $where, array $params): array {
if (defined('APP_DEBUG') && APP_DEBUG) $t=hrtime(true);//DB_TIME
	$stmt=$pdo->prepare("SELECT t.title, t.task_date, u.username FROM tasks t JOIN users u ON u.id=t.user_id WHERE t.user_id!=? AND $where ORDER BY u.username");
	$stmt->execute(array_merge([$exclude_uid], $params));
	$map=[];
	foreach ($stmt->fetchAll() as $c) $map[$c['task_date']."\t".$c['title']][]=$c['username'];
if (defined('APP_DEBUG') && APP_DEBUG && function_exists('timer_log'))timer_log(['qs'=>'SQL:coworkers_map','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME
	return $map;
}

/**
 * DateContext — injectable "what time is it?" for handlers and views.
 * Pass a fixed date in tests; omit constructor args in production.
 */
class DateContext {
	public readonly string $today;   // 'Y-m-d'
	public readonly string $time;	// 'H:i'
	public readonly string $month;   // 'Y-m-d' first day of current month
	public readonly string $ym;	// 'Y-m'

	public function __construct(string $today = '', string $time = '') {
		$this->today = $today !== '' ? $today : date('Y-m-d');
		$this->time  = $time  !== '' ? $time  : date('H:i');
		$ts = strtotime($this->today);
		$this->ym    = date('Y-m', $ts);
		$this->month = date('Y-m', $ts) . '-01';
	}

	public function yesterday(): string {
		return date('Y-m-d', strtotime($this->today . ' -1 day'));
	}
}
