<?php
/**
 * REST API — thin router.
 *	1. Pre-auth early exits (manifest, login, i18n, backup)
 *	2. Auth gate
 *	3. match($api)
 *	4. Single JSON exit point
 */

$api = $_GET['api'] ?? null;
if ($api !== null) {

$method = $_SERVER['REQUEST_METHOD'];

// CSRF protection: validate token on all POST requests
if ($method === 'POST') {
	$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
	if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf))
		json_exit(['error' => 'invalid_token'], 403);
}

// ─── PRE-AUTH EARLY EXITS

if ($api === 'manifest') {
	header('Content-Type: application/manifest+json');
	echo json_encode(["name" => __('app_title'), "short_name" => __('short_name'),
		"start_url" => "?view=today", "display" => "standalone",
		"background_color" => "#eeeeee", "theme_color" => "#3498db",
		"icons" => [["src" => "icon.png", "sizes" => "192x192 512x512", "type" => "image/png", "purpose" => "any maskable"]]
	]);exit;
}



if ($api === 'login' && $method === 'POST') {
	$ip = get_ip();
	if (!rl_ok($pdo, $ip)) json_exit(['error' => 'too_many_attempts'], 429, 1);
	$d = json_input();
	try {
		$stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
		$stmt->execute([trim($d['username'] ?? '')]);
		$user = $stmt->fetch();

		if ($user && password_verify($d['password'] ?? '', $user['password'])) {
			rl_clear($pdo, $ip); // Success: Wipe fail history
			session_regenerate_id(true);
			$_SESSION['logged_in'] = true;
			$_SESSION['uid'] = $user['id'];
			$_SESSION['username'] = $user['username'];
			json_exit(['uid' => $user['id'], 'username' => $user['username'], 'is_admin' => ($user['username'] === USER1)]);
		}

		rl_fail($pdo, $ip); // Failure: Log attempt
		json_exit(['error' => 'invalid_credentials'], 401, 1);

	} catch (Exception $e) { json_exit(['error' => 'Database error'], 500); }
}




if ($api === 'i18n') {
	global $i18n, $langi;
	$flat = [];
	foreach ($i18n as $k => $v) $flat[$k] = $v[$langi] ?? $k;
	json_exit($flat);
}

if ($api === 'backup') {
	if (!$logged_in || !$is_admin) json_exit(['error' => 'forbidden'], 403);
	header('Content-Description: File Transfer');
	header('Content-Type: application/vnd.sqlite3');
	header('Content-Disposition: attachment; filename="app_backup.SQLite"');
	header('Content-Length: ' . filesize(DB_FILE));
	readfile(DB_FILE);
	exit;
}

// ─── AUTH GATE

if (!$logged_in) json_exit(['error' => 'unauthorized'], 401);

$d = ($method !== 'GET') ? json_input() : [];

// ─── DISPATCH

// Bust stat cache after writes so next GET sees fresh ETag
if (in_array($api, array_merge(['tasks/status','tasks/save','tasks/delete','tasks/batch','rules/generate','users','users/delete','users/update','details','details/delete'], $plugin_write_routes))) {
	clearstatcache(true, DB_FILE);
}

// ─── ETAG CACHING
if ($method === 'GET' && in_array($api, array_merge(['tasks/today', 'tasks/month', 'tasks/team', 'details', 'users'], $plugin_etag_routes))) {
	clearstatcache(true, DB_FILE);
	$stat = filemtime(DB_FILE) . '-' . filesize(DB_FILE);
	$wal = DB_FILE . '-wal';
	if (file_exists($wal)) $stat .= '-' . filemtime($wal) . '-' . filesize($wal);
	$etag = '"dbv-' . crc32($stat) . '"';
	
	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
		http_response_code(304); // "Not Modified"
		exit;
	}
	
	header("ETag: $etag");
	header("Cache-Control: private, must-revalidate");
}


// Build injectable date context from request-scoped vars set by index.php
$dc = new DateContext($today_date ?? '', $current_time ?? '');

// ─── ADMIN GATE — reject non-admins early for admin-only endpoints
$_admin_routes = ['tasks/batch','users','users/delete','users/update','details','details/delete'];
if (in_array($api, $_admin_routes) && !$is_admin) json_exit(['error' => 'forbidden'], 403);

[$code, $body] = match($api) {

'me'	 => [200, ['uid' => $uid, 'username' => $u_name, 'is_admin' => $is_admin, 'today' => $today_date, 'time' => $current_time]],
'logout' => (function() { session_destroy(); return [200, ['msg' => 'ok']]; })(),

// (call handler functions from helpers.php)
'tasks/today' => api_tasks_today($pdo, $uid, $dc),
'tasks/month' => api_tasks_month($pdo, $uid, $is_admin, $ym, ($is_admin && !empty($_GET['user_id'])) ? (int)$_GET['user_id'] : $uid, $dc),
'tasks/team'  => api_tasks_team($pdo, $is_admin, $_GET['scope'] ?? 'today', $dc),
'tasks/print' => api_tasks_print($pdo, $uid, $is_admin, $_GET['task_id'] ?? null, $dc),
'tasks/status' => api_tasks_status($pdo, $d, $uid),
'tasks/save'   => api_tasks_save($pdo, $d, $uid, $is_admin),
'tasks/delete' => api_tasks_delete($pdo, $d, $uid, $is_admin),
'tasks/batch'  => api_tasks_batch($pdo, $d),

'rules/generate' => api_rules_generate($pdo, $d,
	($is_admin && !empty($d['worker_id'])) ? (int)$d['worker_id'] : $uid,
	$d['ym'] ?? $ym),

'users' => match($method) {
	'GET'  => api_users_list($pdo),
	'POST' => api_users_create($pdo, $d),
	default => [405, ['error' => 'method_not_allowed']],
},

'users/delete' => api_users_delete($pdo, $d),
'users/update' => api_users_update($pdo, $d),

'users/password' => api_users_password($pdo, $d, $uid),

'details' => match($method) {
	'GET'  => (function() use ($pdo) {
		[$c, $b] = api_details_get($pdo);
		$b['can_archive'] = function_exists('api_can_archive') ? api_can_archive() : false;
		return [$c, $b];
	})(),
	'POST' => api_details_save($pdo, $d),
	default => [405, ['error' => 'method_not_allowed']],
},

'details/delete' => api_details_delete($pdo, $d),


default => isset($plugin_routes[$api])
	? ($plugin_routes[$api])($pdo, $d, $uid, $is_admin, $dc, $method)
	: [404, ['error' => 'unknown_endpoint']],
};


// ── SINGLE EXIT POINT
if (defined('APP_DEBUG') && APP_DEBUG && !str_starts_with($api, 'debug_log')) {
	$api_ms = round((hrtime(true) - $time_start) / 1e6, 2);
	file_put_contents(TV_TIMERS_LOGFILE,
		'?' . $_SERVER['QUERY_STRING']
		. "\tphp:" . $api_ms . "ms\tdb:" . ($time_db_ms ?? 0) . "ms\n",
		FILE_APPEND);
}
json_exit($body, $code);
} // end if ($api !== null)

