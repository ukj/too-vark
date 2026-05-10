<?php
/**
 * PLUGIN: debug — JS error logging + performance timing.
 * Extracted from: api.php (debug_log routes), helpers.php (app_log, timer_log).
 *
 * Handlers follow P11: explicit params, [code, body] return, no globals.
 */

/** Append a line to the app log file (JS_ERR_LOGFILE). Caps at 1 MB. */
function app_log(string $line): void {
	if (!defined('JS_ERR_LOGFILE')) return;
	$f = JS_ERR_LOGFILE;
	file_put_contents($f, $line, file_exists($f) && filesize($f) > 1048576 ? 0 : FILE_APPEND);
}

/** Log JS performance timing data to time.txt. */
function plugin_debug_timer(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	$fetch = $d['fetch'] ?? '';
	$render = $d['render'] ?? '';
	$msg = $d['qs'] . "\tjs:" . $d['jstm'] . "ms\tfetch:" . $fetch . "ms\trender:" . $render . "ms\n";
	file_put_contents(TV_TIMERS_LOGFILE, $msg, FILE_APPEND);
	return [200, ['msg' => 'ok']];
}

/** Thin alias — called by json_exit() in helpers.php via function_exists guard. */
function timer_log(array $d): array {
	$msg = ($d['qs'] ?? '') . "\tjs:" . ($d['jstm'] ?? 0) . "ms\tfetch:" . ($d['fetch'] ?? '') . "ms\trender:" . ($d['render'] ?? '') . "ms\n";
	file_put_contents(TV_TIMERS_LOGFILE, $msg, FILE_APPEND);
	return [200, ['msg' => 'ok']];
}


/** Log JS errors/promise rejections to app log. */
function plugin_debug_log(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	if (!defined('APP_DEBUG') || !APP_DEBUG) return [200, ['msg' => 'ignored']];
	app_log(sprintf("[%s] %s: %s | URL: %s\n",
		date('Y-m-d H:i:s'),
		strtoupper($d['type'] ?? 'UNKNOWN'),
		json_encode($d['payload'] ?? []),
		$d['url'] ?? 'unknown'
	));
	return [200, ['msg' => 'logged']];
}

return [
	'id' => 'debug',
	'routes' => [
		'debug_log' => 'plugin_debug_log',
		'debug_log/jstimer' => 'plugin_debug_timer',
	],
];
