<?php
/** DO NOT BEAUTIFI INCLUDE LINES
 *  or use own compiler
 * 
 * Töö Värk — Lightweight Work Scheduler Entry point (REST edition).
 * 
 * In production, run `php compile.php` to merge everything into a `index_release.php`.

 * @requires PHP 8.3+
 * @requires ext-pdo_sqlite
 */
declare(strict_types=1);
define('APP_DEBUG', false);
date_default_timezone_set('Europe/Tallinn');

if (defined('APP_DEBUG') && APP_DEBUG) $time_start = hrtime(true);
if (!defined('APP_VERSION')) define('APP_VERSION', 'dev');

define('DATA_DIR', './');
define('DB_FILE', DATA_DIR . '/app-demo.sqlite');
define('ORG_NAME', 'Minu Ettevõtte');
define('USER1', 'admin');// initial pass: admin

define('JS_ERR_LOGFILE', DATA_DIR . '/js_errors.log');
define('TV_TIMERS_LOGFILE', DATA_DIR . '/tv_timers.txt');

if (session_status() === PHP_SESSION_NONE) {
	ini_set('session.gc_maxlifetime', '2592000');
session_set_cookie_params([
'lifetime' => 2592000,'path' => '/',
'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
'httponly' => true,'samesite' => 'Lax'
]);session_start();
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

include_once 'src/i18n.php';

if (isset($_GET['lang']) && isset($i18ni[$_GET['lang']])) {
	$_SESSION['lang'] = $_GET['lang'];
	header("Location: ?view=" . ($_GET['view'] ?? 'today'));exit;
}

$lang = $_SESSION['lang'] ?? 'et';
$langi=$i18ni[$lang];
function __(string $key): string {global $i18n, $langi; return $i18n[$key][$langi] ?? $key;}

include_once 'src/database.php';

include_once 'src/helpers.php';

include_once 'src/plugins.php';

// --- Auth context (shared by api.php and views.php)
$logged_in  = $_SESSION['logged_in'] ?? false;
$uid		= $_SESSION['uid'] ?? 0;
$u_name	= $_SESSION['username'] ?? '';
$is_admin	= ($u_name === USER1);

$today_date = date('Y-m-d');
$current_time = date('H:i');
$view		= $_GET['view'] ?? 'today';
$scope		= $_GET['scope'] ?? 'today';
$ym		= $_GET['ym'] ?? date('Y-m');
$current_month = $ym . '-%';

if ($logged_in && !isset($_GET['api'])) {
	$stmt = $pdo->prepare("SELECT force_password_change FROM users WHERE id = ?");
	$stmt->execute([$uid]);
	if ((int)$stmt->fetchColumn() === 1 && $view !== 'change_password') {
		header("Location: ?view=change_password&msg=force_change");
		exit;
	}
}



// --- called by api.php router and tests
include_once 'src/api_handlers.php';

// --- ?api= requests return JSON and exit
include_once 'src/api.php';

// --- HTML shell: everything below is the static page frame
?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head><meta charset="UTF-8">
<title><?= htmlspecialchars(ORG_NAME) ?> – <?= __('app_title') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="manifest" href="?api=manifest">
<meta name="theme-color" content="#333333">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= __('app_title') ?>">
<link rel="apple-touch-icon" href="icon.png">
<style>
<?php include_once 'src/style.css'; ?>

<?php // Plugin CSS files
if (is_dir($plugin_dir)) foreach (glob($plugin_dir . '*.css') as $pcss) include $pcss;
?>
</style>
<script>
<?php if (defined('APP_DEBUG') && APP_DEBUG): ?>const _t0 = performance.now();<?php endif; ?>
const CSRF_TOKEN='<?= $_SESSION['csrf_token'] ?? '' ?>';

<?php // Inline i18n — eliminates separate fetch round-trip
$_flat = [];
foreach ($i18n as $k => $v) $_flat[$k] = $v[$langi] ?? $k;
?>
const _i18n_data = <?= json_encode($_flat, JSON_UNESCAPED_UNICODE) ?>;

<?php // Early data fetch — starts while browser still parses <body>
if ($logged_in) {
  $preload = match($view) {
	'today' => 'tasks/today',
	'rules' => 'tasks/month&ym=' . urlencode($ym) . (($is_admin && !empty($_GET['user_id'])) ? '&user_id=' . (int)$_GET['user_id'] : ''),
	'team'  => match($scope) {
	  'today','month' => 'tasks/team&scope=' . urlencode($scope) . ($ym !== date('Y-m') ? '&ym=' . urlencode($ym) : ''),
	  default => null,
	},
	default => null,
  };
  if ($preload) { echo "const _preload = fetch('?api=" . $preload . "').then(r=>r.json()).catch(()=>null);\n"; }
} ?>

<?php if (defined('APP_DEBUG') && APP_DEBUG) include_once 'src/debug.js'; ?>

<?php include_once 'src/init.js'; ?>
</script>
</head>
<body>
<?php 

include_once 'src/views.php';

?>
<script>
<?php include_once 'src/app.js'; ?>

<?php // Plugin JS files
if (is_dir($plugin_dir)) foreach (glob($plugin_dir . '*.js') as $pjs) include $pjs;
?>
</script>
<?php if (defined('APP_DEBUG') && APP_DEBUG) {
$e='tests/eruda.js';
if(file_exists($e));
else $e='https://cdn.jsdelivr.net/npm/eruda';
echo "<script src='$e'></script>";
?>
<script>if(typeof eruda!=='undefined')eruda.init();</script>
<?php } ?>
</body>
</html>
