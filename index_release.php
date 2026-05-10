<?php
declare(strict_types=1);
define('APP_VERSION', 'v3.3.0 — 2026-05-10 13:43');
/** DO NOT BEAUTIFI INCLUDE LINES
 *  or use own compiler
 * 
 * Töö Värk — Lightweight Work Scheduler Entry point (REST edition).
 * 
 * In production, run `php compile.php` to merge everything into a `index_release.php`.

 * @requires PHP 8.3+
 * @requires ext-pdo_sqlite
 */

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



/* included from src/i18n.php [[[*/
$i18ni = ['en'=>0, 'et'=>1]; $i18n = [ 'app_title' =>['Work Stuff','Töö Värk'], 'short_name' =>['Work','Töö'], 'nav_today' =>['TODAY','TÄNA'], 'nav_rules' =>['MONTH & RULES','KUU JA REEGLID'], 'nav_team' =>['TEAM','MEESKOND'], 'logout' =>['LOGOUT','VÄLJU'], 'change_password'=>['Change Password', 'Muuda parooli'], 'old_password' =>['Old Password','Vana salasõna'], 'new_password' =>['New Password', 'Uus parool'], 'msg_force_change_required' => ['Password change required', 'Parooli vahetamine on kohustuslik'], 'g_btn_add' =>['Add','Lisa'], 'g_btn_save' =>['Save','Salvesta'], 'g_btn_ok' =>['Ok','Tegu'], 'g_btn_clear' =>['Clear','Tühjenda'], 'g_btn_delete' =>['Delete','Kustuta'], 'g_btn_wait' =>['...','...'], 'g_btn_done' =>['✓','✓'], 'g_btn_retry' =>['Try Again','Proovi uuesti'], 'g_text_size' =>['Text size','Teksti suurus'], 'g_err_conn' =>['Connection Error!','Ühenduse viga!'], 'g_del_confirm' =>['Delete this task?','Kas kustutada see tegevus?'], 'g_sel_worker' =>['Assign Worker','Kes teeb'], 'g_username' =>['Username','Kasutajanimi'], 'g_pass' =>['Password','Parool'], 'g_ph_notes' =>['Notes, Sick note, client late...', 'Märkmed, muutused objekil, haigus...'], 'g_notes_draft_restored' =>['Draft restored from this device','Mustand taastatud sellest seadmest'], 'g_ph_location' =>['Location/Title','Asukoht/Nimetus'], 'g_worker' =>['Worker','Töötaja'], 'g_real_name'=>['Real name','Pärisnimi'], 'g_contact_data'=>['Contact','Kontaktid'], 'g_week' =>['Week','Nädal'], 'g_click_to_edit'=>['Click on existing work record to Edit','Muutmiseks klõpsa tööde loendis kirjel'], 'btn_enter' =>['Enter','Sisene'], 'btn_start' =>['Start','Alusta'], 'btn_finish' =>['Finish','Lõpeta'], 'notes' =>['Notes', 'Märkused'], 'Status' =>['Status','Seis'], 'Status_sh' =>['S','S'], 'status_0' =>['Pending','Ootel'], 'status_1' =>['In Progress','Töös'], 'status_2' =>['Done','Valmis'], 'no_tasks_today' =>['No tasks scheduled for today.','Tänaseks pole töid planeeritud.'], 'no_tasks_period' =>['No tasks for this period.','Sellel perioodil töid pole.'], 'team_today' =>["Today's Overview",'Tänane koond'], 'team_month' =>['Month Overview','Kuu koond'], 'team_wobj' =>['Objects','Asukohad'], 'team_base' =>['Users & Mgmt','Tegijad jm'], 'add_act' =>['Add Activity','Lisa tegevus'], 'sel_reassign' =>['Reassign','Vaheta'], 'rules_title' =>['Current Month','See kuu'], 'rules_days_sh' =>[ '0 Mon Tue Wed Thu Fri Sat Sun', '0 Esm Tei Kol Nel Ree Lau Püh'], 'rules_full_weeks'=>['7-day weeks of the month','Kuu 7 päevased nädalad'], 'New_Task' =>['New_Task','Uus_Tegevus'], 'rules_save' =>['SAVE & GENERATE SCHEDULE','SALVESTA JA LOO UUS GRAAFIK'], 'rules_note' =>['Note: Generating rules is safe. It will not delete or alter manually added or started tasks.', 'Märkus: Reeglite genereerimine on turvaline. See ei kustuta ega muuda käsitsi lisatud ja juba alustatud töid.'], 'jsonDebug' =>['JSON Debug','JSON vaade'], 'H_last_month' =>['Last month','Eelmine kuu'], 'no_data_last_month'=>['No data available for last month.','Eelmise kuu kohta andmed puuduvad.'], 'date' =>['Date ','Kuupäev '], 'week_sh' =>['Wk','Näd'], 'date_short' =>['Dt','Kp'], 'start' =>['Start ','Algus '], 'start_short' =>['S','A'], 'end_short' =>['E','L'], 'how_select_multiple'=>['Ctrl/Cmd click to select multiple','Mitme valimiseks hoia klahvi Ctrl/Cmd click.'], 'teamTasks_csv' =>['thismonth','seekuu'], 'um_um' =>['User Manager','Kasutajad'], 'um_remove_user_option'=>['Remove User','Eemalda kasutaja'], 'um_Archive_year' =>['Archive year?','Arhiivi aasta?'], 'um_edit_user' =>['Edit User','Muuda kasutajat'], 'um_add_new' =>['Add New User','Lisa uus kasutaja'], 'um_pass_hint' =>['Leave blank to keep','Tühjana jätmisel ei muutu'], 'um_no_users' =>['No users found.','Kasutajaid ei leitud.'], 'um_del_confirm' =>['Delete this user?','Kas kustutada see kasutaja?'], 'db_state' =>['Health','Seisund'], 'db_backup' =>['Download Database','Rakenduse andmefail'], 'backup_year' =>['Archive Year & Start Fresh','Alusta uut tööde tabelit ja varunda senine'], 'ld_form_title' =>['Location Details','Objekti andmed'], 'ld_ph_title' =>['Job name (Location)','Töö nimi (Asukoht)'], 'ld_ph_address' =>['Address','Aadress'], 'ld_ph_contact' =>['Contact Person','Kontaktisik'], 'ld_ph_desc' =>['Job Description','Töö kirjeldus'], 'ld_saved_title'=>['Saved Locations','Salvestatud objektid'], 'ld_db_empty' =>['Database is empty.','Andmebaas on tühi.'], 'print_todays' =>["Print today's",'Trüki tänased'], 'print_signature_line'=>['Signature','Allkiri'], 'cfg_title' =>['Settings','Seaded'], 'cfg_ph_key' =>['Key','Võti'], 'cfg_ph_val' =>['Value','Väärtus'], ];
/* EOF src/i18n.php */



if (isset($_GET['lang']) && isset($i18ni[$_GET['lang']])) {
	$_SESSION['lang'] = $_GET['lang'];
	header("Location: ?view=" . ($_GET['view'] ?? 'today'));exit;
}

$lang = $_SESSION['lang'] ?? 'et';
$langi=$i18ni[$lang];
function __(string $key): string {global $i18n, $langi; return $i18n[$key][$langi] ?? $key;}



/* included from src/database.php [[[*/
if (defined('APP_DEBUG') && APP_DEBUG) $time_dbinit = hrtime(true); $pdo = new PDO('sqlite:' . DB_FILE, null, null, [ PDO::ATTR_ERRMODE =>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, ]); $pdo->exec("
	PRAGMA foreign_keys = ON;
	PRAGMA synchronous = NORMAL;
	PRAGMA temp_store = MEMORY;
	PRAGMA cache_size = -20000;
"); if ($pdo->query("PRAGMA journal_mode")->fetchColumn() !== 'wal') { $pdo->exec("PRAGMA journal_mode=WAL;"); }
 
function ensure_tasks_table(PDO $pdo): void { $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
		id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
		task_date TEXT, title TEXT, start_time TEXT, end_time TEXT, status INTEGER DEFAULT 0, source TEXT NOT NULL DEFAULT 'manual', notes TEXT DEFAULT '',
		UNIQUE(user_id, task_date, title)
	)"); $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks(task_date)"); $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_date_title ON tasks(task_date, title, user_id)"); }
 if ((int)$pdo->query("PRAGMA user_version")->fetchColumn() < 1) { $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, real_name TEXT, contact TEXT, force_password_change INTEGER DEFAULT 0)"); $pdo->exec("CREATE TABLE IF NOT EXISTS user_rules (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, rules_text TEXT)"); ensure_tasks_table($pdo); $pdo->exec("CREATE TABLE IF NOT EXISTS rl (ip TEXT PRIMARY KEY, fails INTEGER, expire INTEGER)"); $pdo->exec("CREATE TABLE IF NOT EXISTS task_details (title TEXT PRIMARY KEY NOT NULL, address TEXT, description TEXT, related_person TEXT, checklist TEXT)"); $pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, val TEXT)"); if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) { insert_user($pdo, USER1, USER1, USER1, USER1, 1); } $pdo->exec("PRAGMA user_version = 1"); }
 $cfg = array_column($pdo->query("SELECT key,val FROM config ORDER BY key")->fetchAll(), 'val', 'key'); if (defined('APP_DEBUG') && APP_DEBUG) $time_db_ms = round((hrtime(true) - $time_dbinit) / 1e6, 2);
/* EOF src/database.php */





/* included from src/helpers.php [[[*/
function get_ip(): string { return trim(strtok($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '', ',')); }
 
function rl_ok(PDO $pdo, string $ip, int $max = 5): bool { $pdo->exec("DELETE FROM rl WHERE expire < " . time()); $stmt = $pdo->prepare("SELECT fails FROM rl WHERE ip = ?"); $stmt->execute([$ip]); return (int)$stmt->fetchColumn() < $max; }
 
function rl_fail(PDO $pdo, string $ip, int $sec = 300): void { $pdo->prepare("INSERT INTO rl (ip, fails, expire) VALUES (?, 1, ?) ON CONFLICT(ip) DO UPDATE SET fails = fails + 1, expire = excluded.expire")->execute([$ip, time() + $sec]); }
 
function rl_clear(PDO $pdo, string $ip): void { $pdo->prepare("DELETE FROM rl WHERE ip = ?")->execute([$ip]); }
 
function location_exit(string $rq=''): never { $base=strtok($_SERVER['REQUEST_URI'], '?'); header("Location: " . $base . ($rq !== '' ? '?' . $rq : '')); exit; }
 
function json_exit(array $data, int $status=200, int $sleep=0): never { if($sleep > 0)sleep($sleep); http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); if(($_GET['api'] ?? '') !== 'debug_log/jstimer' && function_exists('timer_log')) timer_log(['qs'=>"?".$_SERVER['QUERY_STRING'], 'jstm'=>0]); exit; }
 
function json_input(): array { return json_decode(file_get_contents("php://input"), true) ?? []; }
 
function is_valid_time(string $t): bool { if ($t === '') return true; $r = date_parse_from_format('H:i', $t); return $r['error_count'] === 0 && $r['warning_count'] === 0; }
 
function is_valid_date(string $d): bool { $r = date_parse_from_format('Y-m-d', $d); if ($r['error_count'] > 0 || $r['warning_count'] > 0) return false; return checkdate($r['month'], $r['day'], $r['year']); }
 
function validate_task(array $d, bool $need_title = true, bool $need_date = true): ?string { if ($need_title && (empty($d['title']) || trim($d['title']) === '')) return 'title is required'; if ($need_date && (empty($d['task_date']) || !is_valid_date($d['task_date']))) return 'invalid date format (expected YYYY-MM-DD)'; if (isset($d['start_time']) && !is_valid_time($d['start_time'])) return 'invalid start_time (expected HH:MM)'; if (isset($d['end_time']) && !is_valid_time($d['end_time'])) return 'invalid end_time (expected HH:MM)'; if (isset($d['status']) && !in_array((int)$d['status'], [0, 1, 2], true)) return 'status must be 0, 1, or 2'; return null; }
 
function timeShift(string $v0, string $v1): array { if(!$v0 || !$v1) return [$v0, $v1]; $start=strtotime($v0); $end=strtotime($v1); $now=time(); if($start && $end && date('H:i', $now) !== date('H:i', $start)) return [date('H:i', $now), date('H:i', $now + ($end - $start))]; return [$v0, $v1]; }
 
function statusLabels(): array { return [0=>__('status_0'), 1=>__('status_1'), 2=>__('status_2')]; }
 
function btnLabels(): array { return [__('btn_start'), __('btn_finish'), __('status_2')]; }
 
function upsert_task(PDO $pdo, int $user_id, string $title, string $task_date, string $start_time, string $end_time, int $status, string $notes): void { if (defined('APP_DEBUG') && APP_DEBUG) $t=hrtime(true); $pdo->prepare("INSERT INTO tasks (user_id, title, task_date, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)
		ON CONFLICT(user_id, task_date, title) DO UPDATE SET start_time=excluded.start_time, end_time=excluded.end_time, status=excluded.status, notes=excluded.notes") ->execute([$user_id, $title, $task_date, $start_time, $end_time, $status, $notes]); if (defined('APP_DEBUG') && APP_DEBUG && function_exists('timer_log'))timer_log(['qs'=>'SQL:upsert_task','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]); }
 
function insert_user(PDO $pdo, string $username, string $password, string $real_name='', string $contact='',$force_pw_change=0): int { $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password,real_name, contact, force_password_change) VALUES (?,?,?,?,?)"); $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT),$real_name, $contact, $force_pw_change]); return $stmt->rowCount(); }
 
function known_titles(PDO $pdo): array { return $pdo->query("SELECT title FROM task_details ORDER BY title")->fetchAll(PDO::FETCH_COLUMN); }
 
function db_try(string $tag, callable $fn, string $unique_msg = 'duplicate_entry'): array { try { return $fn(); } catch (Exception $e) { if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Integrity constraint violation')) return [400, ['error' => $unique_msg]]; error_log($tag . ': ' . $e->getMessage()); return [500, ['error' => 'Database error']]; } }
 
function workers_list(PDO $pdo): array { return $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(); }
 
function users_full_list(PDO $pdo): array { return $pdo->query("SELECT id, username, real_name, contact FROM users ORDER BY username")->fetchAll(); }
 
function full_weeks_info(string $ym): array { $y =(int)substr($ym, 0, 4); $m =(int)substr($ym, 5, 2); $days_in_month = (int)date('t', mktime(0, 0, 0, $m, 1, $y)); $week_day_count = []; $week_first_day = []; for ($di = 1; $di <= $days_in_month; $di++) { $ts = mktime(12, 0, 0, $m, $di, $y); $w = (int)date('W', $ts); $week_day_count[$w] = ($week_day_count[$w] ?? 0) + 1; $week_first_day[$w] ??= $di; } $result = []; $rel = 1; foreach($week_day_count as $iso_w => $count) { if($count<7) continue; $mon_ts = mktime(12, 0, 0, $m, $week_first_day[$iso_w], $y); $sun_ts = mktime(12, 0, 0, $m, $week_first_day[$iso_w] + 6, $y); $result[$rel] = [ 'iso' =>$iso_w, 'from' =>date('d', $mon_ts), 'to' =>date('d', $sun_ts), ]; $rel++; } return $result; }
 
function coworkers_map(PDO $pdo, int $exclude_uid, string $where, array $params): array { if (defined('APP_DEBUG') && APP_DEBUG) $t=hrtime(true); $stmt=$pdo->prepare("SELECT t.title, t.task_date, u.username FROM tasks t JOIN users u ON u.id=t.user_id WHERE t.user_id!=? AND $where ORDER BY u.username"); $stmt->execute(array_merge([$exclude_uid], $params)); $map=[]; foreach ($stmt->fetchAll() as $c) $map[$c['task_date']."\t".$c['title']][]=$c['username']; if (defined('APP_DEBUG') && APP_DEBUG && function_exists('timer_log'))timer_log(['qs'=>'SQL:coworkers_map','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]); return $map; }
 
class DateContext { public readonly string $today; public readonly string $time; public readonly string $month; public readonly string $ym; public function __construct(string $today = '', string $time = '') { $this->today = $today !== '' ? $today : date('Y-m-d'); $this->time = $time !== '' ? $time : date('H:i'); $ts = strtotime($this->today); $this->ym = date('Y-m', $ts); $this->month = date('Y-m', $ts) . '-01'; } public function yesterday(): string { return date('Y-m-d', strtotime($this->today . ' -1 day')); } }
/* EOF src/helpers.php */





/* included from src/plugins.php [[[*/
$plugins=array('audit2'=>[], 'config'=>[], 'debug'=>[]); $plugin_routes = []; $plugin_views = []; $plugin_nav = []; $plugin_etag_routes = []; $plugin_write_routes = []; $plugin_dir = DATA_DIR . 'plugins/'; if (is_dir($plugin_dir)) { foreach (glob($plugin_dir . '*.php') as $pf) { if(!array_key_exists( pathinfo($pf,PATHINFO_FILENAME), $plugins)) $reg = include $pf; if (!is_array($reg) || empty($reg['id'])) continue; $plugins[$reg['id']] = $reg; if (isset($reg['routes'])) foreach ($reg['routes'] as $route => $handler) $plugin_routes[$route] = $handler; if (isset($reg['views'])) foreach ($reg['views'] as $vk => $vfn) $plugin_views[$vk] = $vfn; if (isset($reg['nav'])) $plugin_nav = array_merge($plugin_nav, $reg['nav']); if (isset($reg['etag_routes'])) $plugin_etag_routes = array_merge($plugin_etag_routes, $reg['etag_routes']); if (isset($reg['write_routes'])) $plugin_write_routes = array_merge($plugin_write_routes, $reg['write_routes']); } }
 foreach ($plugins as $id => $reg) { if (!isset($reg['schema_version']) || !isset($reg['schema'])) continue; $key = 'plugin_schema_' . $id; $cur = (int)($cfg[$key] ?? 0); if ($cur < $reg['schema_version']) { foreach ((array)$reg['schema'] as $sql) $pdo->exec($sql); $pdo->prepare("INSERT INTO config (key,val) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET val=excluded.val") ->execute([$key, (string)$reg['schema_version']]); $cfg[$key] = (string)$reg['schema_version']; } }
/* EOF src/plugins.php */
// ─── INLINED PLUGINS


/* included from ./plugins/audit2.php [[[*/
declare(strict_types=1); $_aud_i18n = [ 'nav_audit' => ['Audits', 'Auditid'], 'aud_runs' => ['Runs', 'Auditid'], 'aud_templates' => ['Templates', 'Šabloonid'], 'aud_report' => ['Report', 'Aruanne'], 'aud_access' => ['Access', 'Juurdepääs'], 'aud_no_runs' => ['No audits yet.', 'Auditeid pole veel.'], 'aud_no_templates' => ['No templates yet.', 'Šabloone pole veel.'], 'aud_draft' => ['Draft', 'Mustand'], 'aud_issues' => ['Issues', 'Probleemid'], 'aud_due' => ['Due', 'Tähtajaks'], 'aud_overdue' => ['Overdue', 'Tähtaeg möödas'], 'aud_start_new' => ['Start audit', 'Alusta auditit'], 'aud_back' => ['‹ Back', '‹ Tagasi'], 'aud_commit' => ['Commit audit', 'Kinnita audit'], 'aud_read_only' => ['Committed — read only.', 'Kinnitatud — kirjutuskaitstud.'], 'aud_committed_at' => ['Committed at', 'Kinnitatud'], 'aud_print' => ['🖨 Print', '🖨 Prindi'], 'aud_comment' => ['Comment', 'Kommentaar'], 'aud_saved_locally' => ['Saved locally', 'Salvestatud kohapeal'], 'aud_save' => ['Save template', 'Salvesta šabloon'], 'aud_delete' => ['Disable', 'Keela'], 'aud_new_template' => ['New template', 'Uus šabloon'], 'aud_title' => ['Title', 'Pealkiri'], 'aud_target' => ['Target task', 'Sihtülesanne'], 'aud_target_any' => ['— any —', '— kõik —'], 'aud_interval' => ['Interval', 'Intervall'], 'aud_int_task_done' => ['On task completion', 'Ülesande lõpetamisel'], 'aud_int_week_end' => ['Weekly', 'Nädalane'], 'aud_int_month_end' => ['Monthly', 'Kuine'], 'aud_subtasks' => ['Checklist items', 'Kontrollnimekiri'], 'aud_add_subtask' => ['+ item', '+ punkt'], 'aud_active' => ['Active', 'Aktiivne'], 'aud_disabled' => ['Disabled', 'Välja lülitatud'], 'aud_no_access' => ['No audit access.', 'Auditile juurdepääs puudub.'], 'aud_access_title' => ['Audit access', 'Auditi juurdepääs'], 'aud_subtask_passrate'=> ['Item pass rate', 'Punkti edukus'], 'aud_worker_compl' => ['Worker compliance', 'Töötaja vastavus'], 'aud_print_auditor' => ['Auditor', 'Auditeerija'], 'aud_print_date' => ['Audit date', 'Auditi kuupäev'], 'aud_print_committed' => ['Committed', 'Kinnitatud'], 'aud_print_summary' => ['Summary', 'Kokkuvõte'], 'aud_print_pass' => ['✓ OK', '✓ Korras'], 'aud_print_fail' => ['✗ Issue', '✗ Probleem'], ]; 
function __aud(string $key): string { global $_aud_i18n, $langi; $li = $langi ?? 0; return $_aud_i18n[$key][$li] ?? ($_aud_i18n[$key][0] ?? $key); }
 
function _aud_intervals(): array { return ['task_done', 'week_end', 'month_end']; }
 
function _aud_can(bool $is_admin, int $uid, PDO $pdo): bool { if ($is_admin) return true; $s = $pdo->prepare("SELECT 1 FROM audit_access WHERE user_id=?"); $s->execute([$uid]); return (bool)$s->fetchColumn(); }
 
function _aud_parse_subtasks(mixed $raw): ?array { if (is_string($raw)) { $decoded = json_decode($raw, true); if (!is_array($decoded)) return null; $raw = $decoded; } if (!is_array($raw)) return null; $out = []; foreach ($raw as $s) { if (!is_string($s)) return null; $t = trim($s); if ($t !== '') $out[] = $t; } return $out; }
 
function _aud_template_by_id(PDO $pdo, int $id): ?array { $s = $pdo->prepare("SELECT id, title, target, interval, subtasks, active FROM audit_templates WHERE id=?"); $s->execute([$id]); $t = $s->fetch(); if (!$t) return null; $sub = $pdo->prepare("SELECT id, sort_order, name FROM audit_subtasks WHERE template_id=? ORDER BY sort_order, id"); $sub->execute([$id]); $t['subtasks_ordered'] = $sub->fetchAll(); $t['id'] = (int)$t['id']; $t['active'] = (int)$t['active']; return $t; }
 
function _aud_sync_subtasks(PDO $pdo, int $template_id, array $names): void { $pdo->prepare("DELETE FROM audit_subtasks WHERE template_id=?")->execute([$template_id]); $ins = $pdo->prepare("INSERT INTO audit_subtasks (template_id, sort_order, name) VALUES (?,?,?)"); foreach ($names as $i => $n) $ins->execute([$template_id, $i, $n]); }
 
function _aud_week_bounds(string $ymd): array { $ts= strtotime($ymd); $dow = (int)date('N', $ts); $mon = date('Y-m-d', strtotime($ymd . ' -' . ($dow - 1) . ' days')); return [$mon, date('Y-m-d', strtotime($mon . ' +6 days'))]; }
 
function _aud_month_bounds(string $ymd): array { return [date('Y-m-01', strtotime($ymd)), date('Y-m-t', strtotime($ymd))]; }
 
function plugin_audit_template(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { return match($method) { 'GET' => _aud_templates_list($pdo, $is_admin), 'POST'=> _aud_template_save($pdo, $d, $is_admin), default => [405, ['error' => 'method_not_allowed']], }; }
 
function _aud_templates_list(PDO $pdo, bool $is_admin): array { $sql = "SELECT id, title, target, interval, subtasks, active FROM audit_templates"; if (!$is_admin) $sql .= " WHERE active=1"; $sql .= " ORDER BY title"; $out = []; foreach ($pdo->query($sql)->fetchAll() as $r) { $decoded = json_decode($r['subtasks'] ?? '[]', true); $out[] = [ 'id' => (int)$r['id'], 'title' => $r['title'], 'target' => $r['target'], 'interval' => $r['interval'], 'subtasks' => is_array($decoded) ? $decoded : [], 'active' => (int)$r['active'], ]; } return [200, $out]; }
 
function _aud_template_save(PDO $pdo, array $d, bool $is_admin): array { if (!$is_admin) return [403, ['error' => 'forbidden']]; $title = trim($d['title'] ?? ''); $target = trim($d['target'] ?? ''); $interval = $d['interval'] ?? ''; $subtasks = _aud_parse_subtasks($d['subtasks'] ?? []); if ($title === '') return [400, ['error' => 'title required']]; if (!in_array($interval, _aud_intervals(), true)) return [400, ['error' => 'invalid_interval']]; if ($subtasks === null) return [400, ['error' => 'invalid_subtasks']]; if (!$subtasks) return [400, ['error' => 'subtasks required']]; $js = json_encode(array_values($subtasks), JSON_UNESCAPED_UNICODE); $active = isset($d['active']) ? (int)!!$d['active'] : 1; return db_try('audit_template/save', function() use ($pdo, $d, $title, $target, $interval, $subtasks, $js, $active) { if (!empty($d['id'])) { $id = (int)$d['id']; $pdo->prepare("UPDATE audit_templates SET title=?, target=?, interval=?, subtasks=?, active=? WHERE id=?") ->execute([$title, $target !== '' ? $target : null, $interval, $js, $active, $id]); } else { $pdo->prepare("INSERT INTO audit_templates (title, target, interval, subtasks, active) VALUES (?,?,?,?,?)") ->execute([$title, $target !== '' ? $target : null, $interval, $js, $active]); $id = (int)$pdo->lastInsertId(); } _aud_sync_subtasks($pdo, $id, $subtasks); return [200, ['msg' => 'ok', 'id' => $id]]; }); }
 
function plugin_audit_template_delete(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { if (!$is_admin) return [403, ['error' => 'forbidden']]; $id = (int)($d['id'] ?? 0); if ($id <= 0) return [400, ['error' => 'id required']]; return db_try('audit_template/delete', function() use ($pdo, $id) { $pdo->prepare("UPDATE audit_templates SET active=0 WHERE id=?")->execute([$id]); return [200, ['msg' => 'ok']]; }); }
 
function plugin_audit_run(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']]; if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']]; if (!empty($_GET['id'])) return _aud_run_get($pdo, (int)$_GET['id'], $uid, $is_admin); $where = []; $params = []; if (!$is_admin) { $where[] = 'r.user_id=?'; $params[] = $uid; } elseif (!empty($_GET['user_id'])) { $where[] = 'r.user_id=?'; $params[] = (int)$_GET['user_id']; } if (!empty($_GET['template_id'])) { $where[] = 'r.template_id=?'; $params[] = (int)$_GET['template_id']; } if (!empty($_GET['from'])) { $where[] = 'r.run_date>=?'; $params[] = $_GET['from']; } if (!empty($_GET['to'])) { $where[] = 'r.run_date<=?'; $params[] = $_GET['to']; } $sql = "SELECT r.id, r.template_id, r.run_date, r.user_id, r.results, r.has_issues, r.committed_at,
				t.title AS template_title, u.username
			FROM audit_runs r
			LEFT JOIN audit_templates t ON t.id=r.template_id
			LEFT JOIN users u ON u.id=r.user_id"; if ($where) $sql .= ' WHERE ' . implode(' AND ', $where); $sql .= ' ORDER BY r.run_date DESC, r.id DESC LIMIT 200'; $stmt = $pdo->prepare($sql); $stmt->execute($params); $out = []; foreach ($stmt->fetchAll() as $r) { $results = json_decode($r['results'] ?? '[]', true); $results = is_array($results) ? $results : []; $done = count(array_filter($results, fn($it) => !empty($it['done']))); $out[] = [ 'id' => (int)$r['id'], 'template_id' => (int)$r['template_id'], 'template_title' => $r['template_title'], 'run_date' => $r['run_date'], 'user_id' => (int)$r['user_id'], 'username' => $r['username'], 'has_issues' => (int)$r['has_issues'], 'committed_at' => $r['committed_at'], 'done_count' => $done, 'total_count' => count($results), ]; } return [200, $out]; }
 
function _aud_run_get(PDO $pdo, int $run_id, int $uid, bool $is_admin): array { $stmt = $pdo->prepare("SELECT id, template_id, run_date, user_id, results, has_issues, committed_at FROM audit_runs WHERE id=?"); $stmt->execute([$run_id]); $r = $stmt->fetch(); if (!$r) return [404, ['error' => 'not_found']]; if (!$is_admin && (int)$r['user_id'] !== $uid) return [403, ['error' => 'forbidden']]; $results = json_decode($r['results'] ?? '[]', true); return [200, [ 'id' => (int)$r['id'], 'template_id'=> (int)$r['template_id'], 'template' => _aud_template_by_id($pdo, (int)$r['template_id']), 'run_date' => $r['run_date'], 'user_id' => (int)$r['user_id'], 'results' => is_array($results) ? $results : [], 'has_issues' => (int)$r['has_issues'], 'committed_at' => $r['committed_at'], ]]; }
 
function plugin_audit_run_create(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']]; if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']]; $tpl_id = (int)($d['template_id'] ?? 0); if ($tpl_id <= 0) return [400, ['error' => 'template_id required']]; $tpl = _aud_template_by_id($pdo, $tpl_id); if (!$tpl || !$tpl['active']) return [404, ['error' => 'template_not_found']]; $target_uid = ($is_admin && !empty($d['user_id'])) ? (int)$d['user_id'] : $uid; return db_try('audit_run/create', function() use ($pdo, $tpl_id, $target_uid, $dc) { $pdo->prepare("INSERT INTO audit_runs (template_id, run_date, user_id, results, has_issues) VALUES (?,?,?,?,0)") ->execute([$tpl_id, $dc->today, $target_uid, '[]']); return [200, ['msg' => 'ok', 'id' => (int)$pdo->lastInsertId()]]; }); }
 
function plugin_audit_check_task(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']]; $task_id = (int)($d['task_id'] ?? 0); if ($task_id <= 0) return [400, ['error' => 'task_id required']]; $stmt = $pdo->prepare("SELECT title, user_id, status FROM tasks WHERE id=?"); $stmt->execute([$task_id]); $task = $stmt->fetch(); if (!$task) return [404, ['error' => 'task_not_found']]; if (!$is_admin && (int)$task['user_id'] !== $uid) return [403, ['error' => 'forbidden']]; if ((int)$task['status'] !== 2) return [200, ['msg' => 'not_done', 'run_id' => null]]; $t = $pdo->prepare("SELECT id FROM audit_templates WHERE active=1 AND interval='task_done' AND target=? LIMIT 1"); $t->execute([$task['title']]); $tpl_id = (int)$t->fetchColumn(); if (!$tpl_id) { $t2 = $pdo->prepare("SELECT id FROM audit_templates WHERE active=1 AND interval='task_done' AND target IS NULL LIMIT 1"); $t2->execute(); $tpl_id = (int)$t2->fetchColumn(); } if (!$tpl_id) return [200, ['msg' => 'no_template', 'run_id' => null]]; $ex = $pdo->prepare("SELECT id FROM audit_runs WHERE template_id=? AND user_id=? AND run_date=? AND committed_at IS NULL LIMIT 1"); $ex->execute([$tpl_id, (int)$task['user_id'], $dc->today]); if ($rid = (int)$ex->fetchColumn()) return [200, ['msg' => 'existing', 'run_id' => $rid]]; return db_try('audit/check_task', function() use ($pdo, $tpl_id, $task, $dc) { $pdo->prepare("INSERT INTO audit_runs (template_id, run_date, user_id, results, has_issues) VALUES (?,?,?,?,0)") ->execute([$tpl_id, $dc->today, (int)$task['user_id'], '[]']); return [200, ['msg' => 'created', 'run_id' => (int)$pdo->lastInsertId()]]; }); }
 
function plugin_audit_due(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']]; if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']]; [$wkStart] = _aud_week_bounds($dc->today); [$moStart] = _aud_month_bounds($dc->today); $target_uid = ($is_admin && !empty($_GET['user_id'])) ? (int)$_GET['user_id'] : $uid; $stmt = $pdo->prepare("SELECT t.id, t.title, t.interval,
			(SELECT MAX(r.run_date) FROM audit_runs r
			 WHERE r.template_id=t.id AND r.user_id=? AND r.committed_at IS NOT NULL) AS last_run
		FROM audit_templates t WHERE t.active=1 AND t.interval IN ('week_end','month_end') ORDER BY t.title"); $stmt->execute([$target_uid]); $out = []; foreach ($stmt->fetchAll() as $r) { $last = $r['last_run']; $window = $r['interval'] === 'week_end' ? $wkStart : $moStart; if (!$last || $last < $window) $out[] = [ 'template_id' => (int)$r['id'], 'title' => $r['title'], 'interval' => $r['interval'], 'last_run' => $last, 'overdue' => $last && $last < $window, ]; } return [200, $out]; }
 
function plugin_audit_commit(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']]; $run_id = (int)($d['run_id'] ?? 0); if ($run_id <= 0) return [400, ['error' => 'run_id required']]; if (!is_array($d['results'] ?? null)) return [400, ['error' => 'results must be array']]; $chk = $pdo->prepare("SELECT user_id, committed_at FROM audit_runs WHERE id=?"); $chk->execute([$run_id]); $row = $chk->fetch(); if (!$row) return [404, ['error' => 'not_found']]; if (!$is_admin && (int)$row['user_id'] !== $uid) return [403, ['error' => 'forbidden']]; if ($row['committed_at']) return [400, ['error' => 'already_committed']]; $issues = 0; $clean = []; foreach ($d['results'] as $r) { if (!is_array($r)) continue; $done = !empty($r['done']) ? 1 : 0; if (!$done) $issues++; $clean[] = ['done' => $done, 'comment' => is_string($r['comment'] ?? null) ? trim((string)$r['comment']) : '']; } return db_try('audit_commit', function() use ($pdo, $run_id, $clean, $issues, $dc) { $pdo->prepare("UPDATE audit_runs SET results=?, has_issues=?, committed_at=? WHERE id=? AND committed_at IS NULL") ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $issues > 0 ? 1 : 0, $dc->today . ' ' . $dc->time, $run_id]); return [200, ['msg' => 'ok', 'has_issues' => $issues > 0 ? 1 : 0]]; }); }
 
function plugin_audit_print(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']]; $run_id = (int)($_GET['run_id'] ?? 0); if ($run_id <= 0) return [400, ['error' => 'run_id required']]; $stmt = $pdo->prepare("SELECT r.*, u.username, u.real_name, u.contact AS user_contact
		FROM audit_runs r LEFT JOIN users u ON u.id=r.user_id WHERE r.id=?"); $stmt->execute([$run_id]); $row = $stmt->fetch(); if (!$row) return [404, ['error' => 'not_found']]; if (!$is_admin && (int)$row['user_id'] !== $uid) return [403, ['error' => 'forbidden']]; if (!$row['committed_at']) return [400, ['error' => 'not_committed']]; $results = json_decode($row['results'] ?? '[]', true); if (!is_array($results)) $results = []; global $cfg; $org = array_filter($cfg ?? [], fn($v, $k) => str_starts_with($k, 'org_'), ARRAY_FILTER_USE_BOTH); return [200, [ 'run' => ['id' => (int)$row['id'], 'run_date' => $row['run_date'], 'committed_at' => $row['committed_at'], 'has_issues' => (int)$row['has_issues'], 'results' => $results], 'template' => _aud_template_by_id($pdo, (int)$row['template_id']), 'auditor'=> ['username' => $row['username'] ?? '', 'real_name' => $row['real_name'] ?? '', 'contact' => $row['user_contact'] ?? ''], 'org' => $org, 'today' => $dc->today, ]]; }
 
function plugin_audit_report(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']]; if (!$is_admin) return [403, ['error' => 'forbidden']]; $where = ['r.committed_at IS NOT NULL']; $params = []; if (!empty($_GET['from'])) { $where[] = 'r.run_date>=?'; $params[] = $_GET['from']; } if (!empty($_GET['to'])) { $where[] = 'r.run_date<=?'; $params[] = $_GET['to']; } if (!empty($_GET['template_id'])) { $where[] = 'r.template_id=?'; $params[] = (int)$_GET['template_id']; } $stmt = $pdo->prepare("SELECT r.template_id, r.user_id, r.results, r.has_issues, t.title AS template_title, u.username
		FROM audit_runs r LEFT JOIN audit_templates t ON t.id=r.template_id LEFT JOIN users u ON u.id=r.user_id
		WHERE " . implode(' AND ', $where)); $stmt->execute($params); $by_tpl = []; $by_worker = []; foreach ($stmt->fetchAll() as $r) { $tid= (int)$r['template_id']; $uidr = (int)$r['user_id']; $by_worker[$uidr] ??= ['user_id' => $uidr, 'username' => $r['username'], 'runs' => 0, 'issues' => 0]; $by_worker[$uidr]['runs']++; if ((int)$r['has_issues']) $by_worker[$uidr]['issues']++; $results = json_decode($r['results'] ?? '[]', true); if (!is_array($results)) continue; $by_tpl[$tid] ??= ['template_id' => $tid, 'template_title' => $r['template_title'], 'items' => []]; foreach ($results as $idx => $item) { $by_tpl[$tid]['items'][$idx] ??= ['done' => 0, 'total' => 0]; $by_tpl[$tid]['items'][$idx]['total']++; if (!empty($item['done'])) $by_tpl[$tid]['items'][$idx]['done']++; } } foreach ($by_tpl as $tid => &$entry) { $tpl = _aud_template_by_id($pdo, $tid); $names = $tpl ? array_column($tpl['subtasks_ordered'], 'name') : []; $items = []; foreach ($entry['items'] as $idx => $stat) $items[] = ['idx' => $idx, 'name' => $names[$idx] ?? ('#' . ($idx + 1)), 'done' => $stat['done'], 'total' => $stat['total'], 'rate' => $stat['total'] ? round(100 * $stat['done'] / $stat['total']) : 0]; usort($items, fn($a, $b) => $a['rate'] <=> $b['rate']); $entry['items'] = $items; } unset($entry); return [200, ['by_template' => array_values($by_tpl), 'by_worker' => array_values($by_worker)]]; }
 
function plugin_audit_access(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array { if (!$is_admin) return [403, ['error' => 'forbidden']]; if ($method === 'GET') { $stmt = $pdo->query("SELECT u.id, u.username, u.real_name, (a.user_id IS NOT NULL) AS has_access
			FROM users u LEFT JOIN audit_access a ON a.user_id=u.id ORDER BY u.username"); return [200, $stmt->fetchAll()]; } if ($method === 'POST') { $target = (int)($d['user_id'] ?? 0); if ($target <= 1) return [400, ['error' => 'invalid_user']]; $grant = !empty($d['grant']); return db_try('audit_access', function() use ($pdo, $target, $grant) { if ($grant) $pdo->prepare("INSERT OR IGNORE INTO audit_access (user_id) VALUES (?)")->execute([$target]); else $pdo->prepare("DELETE FROM audit_access WHERE user_id=?")->execute([$target]); return [200, ['msg' => 'ok']]; }); } return [405, ['error' => 'method_not_allowed']]; }
 
function view_audit(): void { ?>
<div id="audit-shell">
	<div id="audit-toolbar" class="sub-nav no_Print">
		<a href="#" data-aud-tab="runs"	class="active"><?= __aud('aud_runs') ?></a>
		<a href="#" data-aud-tab="templates" class="aud-admin-only hidden"><?= __aud('aud_templates') ?></a>
		<a href="#" data-aud-tab="report"	class="aud-admin-only hidden"><?= __aud('aud_report') ?></a>
		<a href="#" data-aud-tab="access"	class="aud-admin-only hidden"><?= __aud('aud_access') ?></a>
	</div>
	<div id="audit-due-banner" class="hidden"></div>
	<section id="audit-panel-runs"></section>
	<section id="audit-panel-templates" class="hidden aud-admin-only"></section>
	<section id="audit-panel-report"	class="hidden aud-admin-only"></section>
	<section id="audit-panel-access"	class="hidden aud-admin-only"></section>
	<section id="audit-panel-run"	 class="hidden"></section>
</div>
<?php }
 
function view_audit_print(): void { ?>
<div id="audit-print-container"></div>
<?php }
 $_preg = [ 'id' => 'audit', 'schema_version' => 2, 'schema' => [ "CREATE TABLE IF NOT EXISTS audit_templates (
			id	 INTEGER PRIMARY KEY,
			title	TEXT NOT NULL,
			target TEXT,
			interval TEXT NOT NULL,
			subtasks TEXT NOT NULL,
			active INTEGER DEFAULT 1
		)", "CREATE TABLE IF NOT EXISTS audit_runs (
			id		 INTEGER PRIMARY KEY,
			template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
			run_date	 TEXT NOT NULL,
			user_id	INTEGER REFERENCES users(id),
			results	TEXT NOT NULL,
			has_issues INTEGER DEFAULT 0,
			committed_at TEXT
		)", "CREATE TABLE IF NOT EXISTS audit_subtasks (
			id		INTEGER PRIMARY KEY,
			template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
			sort_order INTEGER NOT NULL,
			name		TEXT NOT NULL
		)", "CREATE TABLE IF NOT EXISTS audit_access (
			user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE
		)", "CREATE INDEX IF NOT EXISTS idx_audit_runs_tmpl ON audit_runs(template_id, run_date)", "CREATE INDEX IF NOT EXISTS idx_audit_runs_issues ON audit_runs(has_issues) WHERE has_issues=1", ], 'routes' => [ 'audit_template' => 'plugin_audit_template', 'audit_template/delete' => 'plugin_audit_template_delete', 'audit_run' => 'plugin_audit_run', 'audit_run/create' => 'plugin_audit_run_create', 'audit_run/check_task'=> 'plugin_audit_check_task', 'audit_run/due' => 'plugin_audit_due', 'audit_run/print' => 'plugin_audit_print', 'audit_commit' => 'plugin_audit_commit', 'audit_report' => 'plugin_audit_report', 'audit_access' => 'plugin_audit_access', ], 'views' => [ 'audit' => 'view_audit', 'audit_print' => 'view_audit_print', ], 'nav' => ['audit' => __aud('nav_audit')], 'etag_routes'=> ['audit_template', 'audit_run', 'audit_run/due', 'audit_run/print', 'audit_report', 'audit_access'], 'write_routes' => ['audit_template', 'audit_template/delete', 'audit_run/create', 'audit_run/check_task', 'audit_commit', 'audit_access'], ]; $_pid = $_preg['id'] ?? ''; if ($_pid) { $plugins[$_pid] = $_preg; if (isset($_preg['routes'])) foreach ($_preg['routes'] as $_r=> $_h) $plugin_routes[$_r]= $_h; if (isset($_preg['views'])) foreach ($_preg['views'] as $_vk => $_vfn) $plugin_views[$_vk]= $_vfn; if (isset($_preg['nav'])) $plugin_nav = array_merge($plugin_nav, $_preg['nav']); if (isset($_preg['etag_routes']))$plugin_etag_routes= array_merge($plugin_etag_routes,$_preg['etag_routes']); if (isset($_preg['write_routes'])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg['write_routes']); }
 $_pid = $_preg["id"] ?? ""; if ($_pid) { $plugins[$_pid] = $_preg; if (isset($_preg["routes"])) foreach ($_preg["routes"] as $_r => $_h) $plugin_routes[$_r] = $_h; if (isset($_preg["views"])) foreach ($_preg["views"] as $_vk => $_vfn) $plugin_views[$_vk] = $_vfn; if (isset($_preg["nav"])) $plugin_nav = array_merge($plugin_nav, $_preg["nav"]); if (isset($_preg["etag_routes"])) $plugin_etag_routes = array_merge($plugin_etag_routes, $_preg["etag_routes"]); if (isset($_preg["write_routes"])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg["write_routes"]); }
/* EOF ./plugins/audit2.php */



/* included from ./plugins/config.php [[[*/
function api_can_archive(): bool { return idate("m") === 12 && idate("d") > 20; }
 
function plugin_config( PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = "GET", ): array { if (!$is_admin) { return [403, ["error" => "forbidden"]]; } return match ($method) { "GET" => (function () { global $cfg; return [ 200, array_map( fn($k, $v) => ["key" => $k, "val" => $v], array_keys($cfg), array_values($cfg), ), ]; })(), "POST" => plugin_config_save($pdo, $d, $uid, $is_admin, $dc), default => [405, ["error" => "method_not_allowed"]], }; }
 
function plugin_config_save( PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = "POST", ): array { if (!$is_admin) { return [403, ["error" => "forbidden"]]; } $key = trim($d["key"] ?? ""); if ($key === "") { return [400, ["error" => "key is required"]]; } return db_try( "config/save", fn() => $pdo ->prepare( "INSERT INTO config (key, val) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET val=excluded.val", ) ->execute([$key, $d["val"] ?? ""]) ? [200, ["msg" => "ok"]] : [500, ["error" => "Database error"]], ); }
 
function plugin_config_delete( PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = "POST", ): array { if (!$is_admin) { return [403, ["error" => "forbidden"]]; } $key = trim($d["key"] ?? ""); if ($key === "") { return [400, ["error" => "key is required"]]; } return db_try("config/delete", function () use ($pdo, $key) { $stmt = $pdo->prepare("DELETE FROM config WHERE key = ?"); $stmt->execute([$key]); return $stmt->rowCount() === 0 ? [404, ["error" => "not_found"]] : [200, ["msg" => "ok"]]; }); }
 
function plugin_archive_year( PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = "POST", ): array { if (!$is_admin) { return [403, ["error" => "forbidden"]]; } if (!api_can_archive()) { return [400, ["error" => "not_available"]]; } $tbl = "tasks_" . date("Y"); return db_try("archive_year", function () use ($pdo, $tbl) { if ( $pdo ->query( "SELECT 1 FROM sqlite_master WHERE type='table' AND name='$tbl'", ) ->fetchColumn() ) { return [400, ["error" => "archive_already_exists"]]; } $pdo->exec("ALTER TABLE tasks RENAME TO $tbl"); ensure_tasks_table($pdo); return [200, ["msg" => "ok"]]; }); }
 $_preg = [ "id" => "config", "routes" => [ "config" => "plugin_config", "config/delete" => "plugin_config_delete", "archive_year" => "plugin_archive_year", ], "etag_routes" => ["config"], "write_routes" => ["config", "config/delete", "archive_year"], ]; $_pid = $_preg["id"] ?? ""; if ($_pid) { $plugins[$_pid] = $_preg; if (isset($_preg["routes"])) foreach ($_preg["routes"] as $_r => $_h) $plugin_routes[$_r] = $_h; if (isset($_preg["views"])) foreach ($_preg["views"] as $_vk => $_vfn) $plugin_views[$_vk] = $_vfn; if (isset($_preg["nav"])) $plugin_nav = array_merge($plugin_nav, $_preg["nav"]); if (isset($_preg["etag_routes"])) $plugin_etag_routes = array_merge($plugin_etag_routes, $_preg["etag_routes"]); if (isset($_preg["write_routes"])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg["write_routes"]); }
/* EOF ./plugins/config.php */



/* included from ./plugins/debug.php [[[*/
function app_log(string $line): void { if (!defined('JS_ERR_LOGFILE')) return; $f = JS_ERR_LOGFILE; file_put_contents($f, $line, file_exists($f) && filesize($f) > 1048576 ? 0 : FILE_APPEND); }
 
function plugin_debug_timer(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { $fetch = $d['fetch'] ?? ''; $render = $d['render'] ?? ''; $msg = $d['qs'] . "\tjs:" . $d['jstm'] . "ms\tfetch:" . $fetch . "ms\trender:" . $render . "ms\n"; file_put_contents(TV_TIMERS_LOGFILE, $msg, FILE_APPEND); return [200, ['msg' => 'ok']]; }
 
function timer_log(array $d): array { $msg = ($d['qs'] ?? '') . "\tjs:" . ($d['jstm'] ?? 0) . "ms\tfetch:" . ($d['fetch'] ?? '') . "ms\trender:" . ($d['render'] ?? '') . "ms\n"; file_put_contents(TV_TIMERS_LOGFILE, $msg, FILE_APPEND); return [200, ['msg' => 'ok']]; }
 
function plugin_debug_log(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array { if (!defined('APP_DEBUG') || !APP_DEBUG) return [200, ['msg' => 'ignored']]; app_log(sprintf("[%s] %s: %s | URL: %s\n", date('Y-m-d H:i:s'), strtoupper($d['type'] ?? 'UNKNOWN'), json_encode($d['payload'] ?? []), $d['url'] ?? 'unknown' )); return [200, ['msg' => 'logged']]; }
 $_preg = [ 'id' => 'debug', 'routes' => [ 'debug_log' => 'plugin_debug_log', 'debug_log/jstimer' => 'plugin_debug_timer', ], ]; $_pid = $_preg["id"] ?? ""; if ($_pid) { $plugins[$_pid] = $_preg; if (isset($_preg["routes"])) foreach ($_preg["routes"] as $_r => $_h) $plugin_routes[$_r] = $_h; if (isset($_preg["views"])) foreach ($_preg["views"] as $_vk => $_vfn) $plugin_views[$_vk] = $_vfn; if (isset($_preg["nav"])) $plugin_nav = array_merge($plugin_nav, $_preg["nav"]); if (isset($_preg["etag_routes"])) $plugin_etag_routes = array_merge($plugin_etag_routes, $_preg["etag_routes"]); if (isset($_preg["write_routes"])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg["write_routes"]); }
/* EOF ./plugins/debug.php */





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


/* included from src/api_handlers.php [[[*/
function api_tasks_today(PDO $pdo, int $uid, DateContext $dc): array { $today_date = $dc->today; $current_time = $dc->time; $yesterday = $dc->yesterday(); $stmt=$pdo->prepare("SELECT t.*, d.description 
FROM tasks t LEFT JOIN task_details d ON t.title=d.title 
WHERE user_id=? AND (task_date=? OR task_date=?)
ORDER BY t.title"); $stmt->execute([$uid,$yesterday, $today_date]); $btn=btnLabels(); $coworkers = coworkers_map($pdo, $uid, "(t.task_date=? OR t.task_date=?)", [$yesterday, $today_date]); $out=[]; foreach ($stmt->fetchAll() as $r) { if ($r['status'] == 2 && $r['task_date'] != $today_date) continue; [$start, $end]=timeShift($r['start_time'], $r['end_time']); if ($r['status'] == '1') $end=$current_time; elseif ($r['status'] == '2') $end=$r['end_time']; $key=$r['task_date']."\t".$r['title']; $out[]=[ 'id' => (int)$r['id'], 'title' => $r['title'], 'start_time' => $r['status'] == '0' ? $start : $r['start_time'], 'end_time' => $end, 'status' => (int)$r['status'], 'status_text'=> $btn[(int)$r['status']], 'notes' => (string)$r['notes'], 'description' => $r['description'], 'coworkers' => $coworkers[$key] ?? [] ]; } return [200, $out]; }
 
function api_tasks_month(PDO $pdo, int $uid, bool $is_admin, string $ym, int $target_uid, DateContext $dc): array { $today_date = $dc->today; $stmt=$pdo->prepare("SELECT rules_text FROM user_rules WHERE user_id=?"); $stmt->execute([$target_uid]); $rules_text=$stmt->fetchColumn() ?: ""; $status_texts=statusLabels(); $grouped=[]; $ym_first = $ym . '-01'; $prev_first = date('Y-m-d', strtotime($ym_first . ' -1 month')); $next_first = date('Y-m-d', strtotime($ym_first . ' +1 month')); $stmt=$pdo->prepare("SELECT id, user_id, title, start_time, end_time, status, task_date, notes FROM tasks WHERE user_id=? AND task_date >= ? AND task_date < ? ORDER BY task_date, start_time, title"); $stmt->execute([$target_uid, $prev_first, $next_first]); $all_rows=$stmt->fetchAll(); $coworkers = coworkers_map($pdo, $target_uid, "t.task_date >= ? AND t.task_date < ?", [$ym_first, $next_first]); $last_month_data = []; foreach ($all_rows as $row) { if ($row['task_date'] < $ym_first) { $last_month_data[] = array_values([ $row['title'] ,$row['task_date'] ,$row['start_time'] ,$row['end_time'] ,statusLabels()[$row['status']] ,$row['notes']]); continue; } $row['status_text']=$status_texts[(int)$row['status']] ?? '?'; $row['username']=''; $key=$row['task_date']."\t".$row['title']; $row['coworkers']=$coworkers[$key] ?? []; $grouped[$row['task_date']][]=$row; } $workers=$is_admin ? $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll() : []; return [200, [ 'target_uid' => $target_uid, 'rules_text' => $rules_text, 'grouped' => $grouped, 'ym' => $ym, 'prev_ym' => substr($prev_first, 0, 7), 'next_ym' => substr($next_first, 0, 7), 'today' => $today_date, 'last_month_data'=> $last_month_data, 'workers' => $workers, 'known_titles' => known_titles($pdo), 'full_weeks' => full_weeks_info($ym), 'is_admin' => $is_admin, ]]; }
 
function api_tasks_team(PDO $pdo, bool $is_admin, string $scope, DateContext $dc): array { $today_date = $dc->today; $current_month_first = $dc->month; if (!$is_admin) return [403, ['error' => 'forbidden']]; $is_month=($scope === 'month'); $status_texts=statusLabels(); $grouped=[]; if ($is_month) { $ym_first = $current_month_first; $next_first = date('Y-m-d', strtotime($ym_first . ' +1 month')); $stmt=$pdo->prepare("SELECT t.id, u.username, u.id as user_id, t.title, t.start_time, t.end_time, t.status, t.notes, t.task_date
			FROM tasks t JOIN users u ON t.user_id=u.id
			WHERE t.task_date >= ? AND t.task_date < ?
			ORDER BY t.task_date, u.username"); $stmt->execute([$ym_first, $next_first]); } else { $stmt=$pdo->prepare("SELECT t.id, u.username, u.id as user_id, t.title, t.start_time, t.end_time, t.status, t.notes, t.task_date
			FROM tasks t JOIN users u ON t.user_id=u.id
			WHERE t.task_date = ?
			ORDER BY t.start_time, u.username"); $stmt->execute([$today_date]); } foreach ($stmt->fetchAll() as $row) { $key=$is_month ? $row['task_date'] : ($row['start_time'] ?: '???'); $row['status_text']=$status_texts[(int)$row['status']] ?? '?'; $grouped[$key][]=$row; } return [200, [ 'grouped' => $grouped, 'workers' => workers_list($pdo), 'known_titles' => known_titles($pdo), 'is_month' => $is_month, 'today' => $today_date, ]]; }
 
function api_tasks_print(PDO $pdo, int $uid, bool $is_admin, ?string $task_id, DateContext $dc): array { $today_date = $dc->today; $sql="SELECT u.username, u.real_name, u.contact AS user_contact, t.title, t.task_date, t.start_time, t.end_time, t.notes, d.address, d.description, d.related_person
			FROM tasks t JOIN users u ON t.user_id=u.id
			LEFT JOIN task_details d ON t.title=d.title WHERE "; $params=[]; if ($task_id) { $sql .= "t.id=?"; $params[]=$task_id; if (!$is_admin) { $sql .= " AND t.user_id=?"; $params[]=$uid; } } else { if (!$is_admin) return [403, ['error' => 'forbidden']]; $sql .= "t.task_date=?"; $params[]=$today_date; } $sql .= " ORDER BY u.username, t.start_time"; $stmt=$pdo->prepare($sql); $stmt->execute($params); $print_data=[]; foreach ($stmt->fetchAll() as $row) { $key = ($row['real_name'] ?: $row['username']); $print_data[$key][]=$row; } global $cfg; $org = array_filter($cfg, fn($v, $k) => str_starts_with($k, 'org_'), ARRAY_FILTER_USE_BOTH); return [200, ['print_data' => $print_data, 'today' => $today_date, 'org' => $org]]; }
 
function api_tasks_status(PDO $pdo, array $d, int $uid): array { if (empty($d['id'])) return [400, ['error' => 'id is required']]; if ($err = validate_task($d, false, false)) return [400, ['error' => $err]]; $new_status=min((int)$d['status'] + 1, 2); $stmt=$pdo->prepare("UPDATE tasks SET start_time=?, end_time=?, status=?, notes=? WHERE id=? AND user_id=?"); $stmt->execute([$d['start_time'], $d['end_time'], $new_status, $d['notes'] ?? '', $d['id'], $uid]); if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']]; $labels=btnLabels(); return [200, ['status' => (string)$new_status, 'start_time' => $d['start_time'], 'end_time' => $d['end_time'], 'msg' => $labels[$new_status]]]; }
 
function api_tasks_save(PDO $pdo, array $d, int $uid, bool $is_admin): array { if ($err = validate_task($d)) return [400, ['error' => $err]]; $status=isset($d['status']) ? (int)$d['status'] : 0; return db_try('api_tasks_save', function() use ($pdo, $d, $uid, $is_admin, $status) { if (!empty($d['id'])) { $sql="UPDATE tasks SET title=?, task_date=?, start_time=?, end_time=?, status=?, notes=?"; $params=[$d['title'], $d['task_date'], $d['start_time'], $d['end_time'], $status, $d['notes'] ?? '']; if ($is_admin && !empty($d['user_id'])) { $sql .= ", user_id=?"; $params[]=(int)$d['user_id']; } $sql .= " WHERE id=?"; $params[]=$d['id']; if (!$is_admin) { $sql .= " AND user_id=?"; $params[]=$uid; } $pdo->prepare($sql)->execute($params); $new_id = (int)$d['id']; } else { $target_uid=$uid; if ($is_admin) { if (!empty($d['user_id'])) $target_uid=(int)$d['user_id']; elseif (!empty($d['worker_ids'])) $target_uid=is_array($d['worker_ids']) ? (int)$d['worker_ids'][0] : (int)$d['worker_ids']; } upsert_task($pdo, $target_uid, $d['title'], $d['task_date'], $d['start_time'], $d['end_time'], $status, $d['notes'] ?? ''); $stmt = $pdo->prepare("SELECT id FROM tasks WHERE user_id=? AND task_date=? AND title=?"); $stmt->execute([$target_uid, $d['task_date'], $d['title']]); $new_id = (int)$stmt->fetchColumn(); } return [200, ['msg' => 'ok', 'id' => $new_id]]; }, 'Worker already has a task with this title on this date.'); }
 
function api_tasks_delete(PDO $pdo, array $d, int $uid, bool $is_admin): array { if (empty($d['id'])) return [400, ['error' => 'id is required']]; $sql="DELETE FROM tasks WHERE id=? AND status < 2"; $params=[$d['id']]; if (!$is_admin) { $sql .= " AND user_id=?"; $params[]=$uid; } $stmt=$pdo->prepare($sql); $stmt->execute($params); if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']]; return [200, ['msg' => 'ok']]; }
 
function api_rules_generate(PDO $pdo, array $d, int $target_uid, string $req_ym): array { $rules_json=trim($d['rules_txt'] ?? ''); $ym_first = $req_ym . '-01'; $next_first = date('Y-m-d', strtotime($ym_first . ' +1 month')); $rules=json_decode($rules_json, true); if (!is_array($rules)) return [400, ['error' => 'Invalid rules JSON']]; $pdo->beginTransaction(); try { $pdo->prepare("INSERT INTO user_rules (user_id, rules_text) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET rules_text=excluded.rules_text") ->execute([$target_uid, $rules_json]); $pdo->prepare("DELETE FROM tasks WHERE user_id=? AND task_date >= ? AND task_date < ? AND status=0 AND source='rule'") ->execute([$target_uid, $ym_first, $next_first]); $insert_stmt=$pdo->prepare("INSERT OR IGNORE INTO tasks (user_id, task_date, title, start_time, end_time, status, notes, source) VALUES (?, ?, ?, ?, ?, 0, '', 'rule')"); $y=(int)substr($req_ym, 0, 4); $m=(int)substr($req_ym, 5, 2); $days_in_month=(int)date('t', mktime(0, 0, 0, $m, 1, $y)); $fw_info = full_weeks_info($req_ym); $iso_to_rel = []; foreach ($fw_info as $rel => $wk) $iso_to_rel[$wk['iso']] = $rel; for ($day=1; $day <= $days_in_month; $day++) { $ts=mktime(12, 0, 0, $m, $day, $y); $day_num=(int)date('N', $ts); $iso_week=(int)date('W', $ts); $rel_week=$iso_to_rel[$iso_week] ?? 0; if ($rel_week === 0) continue; foreach ($rules as $r) { if (empty($r['title']) || empty($r['days']) || empty($r['weeks']) || empty($r['start']) || empty($r['end'])) continue; $day_col=strtr($r['days'], ['E'=>'1','T'=>'2','K'=>'3','N'=>'4','R'=>'5','L'=>'6','P'=>'7']); $match=(strpos($day_col, (string)$day_num) !== false) && (strpos((string)$r['weeks'], (string)$rel_week) !== false); if ($match) $insert_stmt->execute([$target_uid, date('Y-m-d', $ts), $r['title'], $r['start'], $r['end']]); } } $pdo->commit(); return [200, ['msg' => 'ok']]; } catch (Exception $e) { $pdo->rollBack(); error_log('api_rules_generate: ' . $e->getMessage()); return [500, ['error' => 'Schedule generation failed']]; } }
 
function api_details_get(PDO $pdo): array { $all=$pdo->query("SELECT * FROM task_details ORDER BY title")->fetchAll(); $addresses = array_values(array_unique(array_filter(array_column($all, 'address')))); $contacts = array_values(array_unique(array_filter(array_column($all, 'related_person')))); $wal_status = $pdo->query("PRAGMA wal_checkpoint(PASSIVE);")->fetchAll(PDO::FETCH_ASSOC); if(array_sum($wal_status[0])==0) $db_status = "WAL OK"; else $db_status = "WAL checkpoint status: ". print_r($wal_status[0],true); $users = users_full_list($pdo); global $cfg; $config = array_map(fn($k,$v) => ['key'=>$k,'val'=>$v], array_keys($cfg), array_values($cfg)); return [200, [ 'details' => $all, 'known_addresses' => $addresses, 'known_contacts' => $contacts, 'db_status'=>$db_status, 'users'=>$users, 'config'=>$config ]]; }
 
function api_tasks_batch(PDO $pdo, array $d): array { if ($err = validate_task($d)) return [400, ['error' => $err]]; if (empty($d['worker_ids']) || !is_array($d['worker_ids'])) return [400, ['error' => 'worker_ids required']]; $pdo->beginTransaction(); $result = db_try('tasks/batch', function() use ($pdo, $d) { foreach ($d['worker_ids'] as $wid) { upsert_task($pdo, (int)$wid, $d['title'], $d['task_date'], $d['start_time'], $d['end_time'], 0, $d['notes'] ?? ''); } $pdo->commit(); return [200, ['msg' => 'ok', 'updated' => count($d['worker_ids'])]]; }); if ($result[0] !== 200 && $pdo->inTransaction()) $pdo->rollBack(); return $result; }
 
function api_users_list(PDO $pdo): array { return [200, users_full_list($pdo)]; }
 
function api_users_update(PDO $pdo, array $d): array { $id = (int)($d['id'] ?? 0); if ($id <= 0) return [400, ['error' => 'id is required']]; if ($id === 1) return [403, ['error' => 'admin_protected']]; $username = trim($d['username'] ?? ''); if ($username === '') return [400, ['error' => 'username is required']]; return db_try('users/update', function() use ($pdo, $d, $id, $username) { $sql = "UPDATE users SET username=?, real_name=?, contact=?"; $params = [$username, trim($d['real_name'] ?? ''), trim($d['contact'] ?? '')]; if (!empty($d['password'])) { $sql .= ", password=?"; $params[] = password_hash($d['password'], PASSWORD_DEFAULT); } $sql .= " WHERE id=?"; $params[] = $id; $stmt = $pdo->prepare($sql); $stmt->execute($params); if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']]; return [200, ['msg' => 'ok', 'id' => $id, 'username' => $username, 'real_name' => trim($d['real_name'] ?? ''), 'contact' => trim($d['contact'] ?? '')]]; }, 'username_exists'); }
 
function api_users_create(PDO $pdo, array $d): array { $a_u = trim($d['username'] ?? ''); $a_p = trim($d['password'] ?? ''); $a_rn = trim($d['real_name'] ?? ''); $a_c = trim($d['contact'] ?? ''); if ($a_u === '' || $a_p === '') return [400, ['error' => 'empty_credentials']]; return db_try('users', function() use ($pdo, $a_u, $a_p, $a_rn, $a_c) { if (insert_user($pdo, $a_u, $a_p,$a_rn,$a_c) === 0) return [400, ['error' => 'user_exists']]; $new_id = (int)$pdo->lastInsertId(); return [200, ['msg' => 'ok', 'id' => $new_id, 'username' => $a_u, 'real_name' => $a_rn, 'contact' => $a_c]]; }); }
 
function api_users_delete(PDO $pdo, array $d): array { $id = (int)($d['id'] ?? 0); if ($id <= 1) return [403, ['error' => 'admin_protected']]; return db_try('users/delete', function() use ($pdo, $id) { $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?"); $stmt->execute([$id]); return $stmt->rowCount() === 0 ? [404, ['error' => 'not_found']] : [200, ['msg' => 'ok']]; }); }
 
function api_users_password(PDO $pdo, array $d, int $uid): array { if (empty($d['new_password'])) return [400, ['error' => 'empty_password']]; if (empty($d['old_password'])) return [400, ['error' => 'old_password_required']]; $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?"); $stmt->execute([$uid]); $hash = $stmt->fetchColumn(); if (!$hash || !password_verify($d['old_password'], $hash)) return [403, ['error' => 'wrong_password']]; return db_try('users/password', function() use ($pdo, $d, $uid) { $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?"); $stmt->execute([password_hash($d['new_password'], PASSWORD_DEFAULT), $uid]); return $stmt->rowCount() === 0 ? [404, ['error' => 'user_not_found']] : [200, ['msg' => 'ok']]; }); }
 
function api_details_save(PDO $pdo, array $d): array { if (trim($d['title'] ?? '') === '') return [400, ['error' => 'title is required']]; return db_try('details', fn() => ( $pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)
		ON CONFLICT(title) DO UPDATE SET address=excluded.address, description=excluded.description, related_person=excluded.related_person") ->execute([trim($d['title']), $d['address'], $d['description'], $d['related_person']]) ) ? [200, ['msg' => 'ok']] : [500, ['error' => 'Database error']]); }
 
function api_details_delete(PDO $pdo, array $d): array { return db_try('details/delete', function() use ($pdo, $d) { $stmt = $pdo->prepare("DELETE FROM task_details WHERE title = ?"); $stmt->execute([$d['title']]); return $stmt->rowCount() === 0 ? [404, ['error' => 'not_found']] : [200, ['msg' => 'ok']]; }); }
/* EOF src/api_handlers.php */



// --- ?api= requests return JSON and exit


/* included from src/api.php [[[*/
$api = $_GET['api'] ?? null; if ($api !== null) { $method = $_SERVER['REQUEST_METHOD']; if ($method === 'POST') { $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) json_exit(['error' => 'invalid_token'], 403); } if ($api === 'manifest') { header('Content-Type: application/manifest+json'); echo json_encode(["name" => __('app_title'), "short_name" => __('short_name'), "start_url" => "?view=today", "display" => "standalone", "background_color" => "#eeeeee", "theme_color" => "#3498db", "icons" => [["src" => "icon.png", "sizes" => "192x192 512x512", "type" => "image/png", "purpose" => "any maskable"]] ]);exit; } if ($api === 'login' && $method === 'POST') { $ip = get_ip(); if (!rl_ok($pdo, $ip)) json_exit(['error' => 'too_many_attempts'], 429, 1); $d = json_input(); try { $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?"); $stmt->execute([trim($d['username'] ?? '')]); $user = $stmt->fetch(); if ($user && password_verify($d['password'] ?? '', $user['password'])) { rl_clear($pdo, $ip); session_regenerate_id(true); $_SESSION['logged_in'] = true; $_SESSION['uid'] = $user['id']; $_SESSION['username'] = $user['username']; json_exit(['uid' => $user['id'], 'username' => $user['username'], 'is_admin' => ($user['username'] === USER1)]); } rl_fail($pdo, $ip); json_exit(['error' => 'invalid_credentials'], 401, 1); } catch (Exception $e) { json_exit(['error' => 'Database error'], 500); } } if ($api === 'i18n') { global $i18n, $langi; $flat = []; foreach ($i18n as $k => $v) $flat[$k] = $v[$langi] ?? $k; json_exit($flat); } if ($api === 'backup') { if (!$logged_in || !$is_admin) json_exit(['error' => 'forbidden'], 403); header('Content-Description: File Transfer'); header('Content-Type: application/vnd.sqlite3'); header('Content-Disposition: attachment; filename="app_backup.SQLite"'); header('Content-Length: ' . filesize(DB_FILE)); readfile(DB_FILE); exit; } if (!$logged_in) json_exit(['error' => 'unauthorized'], 401); $d = ($method !== 'GET') ? json_input() : []; if (in_array($api, array_merge(['tasks/status','tasks/save','tasks/delete','tasks/batch','rules/generate','users','users/delete','users/update','details','details/delete'], $plugin_write_routes))) { clearstatcache(true, DB_FILE); } if ($method === 'GET' && in_array($api, array_merge(['tasks/today', 'tasks/month', 'tasks/team', 'details', 'users'], $plugin_etag_routes))) { clearstatcache(true, DB_FILE); $stat = filemtime(DB_FILE) . '-' . filesize(DB_FILE); $wal = DB_FILE . '-wal'; if (file_exists($wal)) $stat .= '-' . filemtime($wal) . '-' . filesize($wal); $etag = '"dbv-' . crc32($stat) . '"'; if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { http_response_code(304); exit; } header("ETag: $etag"); header("Cache-Control: private, must-revalidate"); } $dc = new DateContext($today_date ?? '', $current_time ?? ''); $_admin_routes = ['tasks/batch','users','users/delete','users/update','details','details/delete']; if (in_array($api, $_admin_routes) && !$is_admin) json_exit(['error' => 'forbidden'], 403); [$code, $body] = match($api) { 'me' => [200, ['uid' => $uid, 'username' => $u_name, 'is_admin' => $is_admin, 'today' => $today_date, 'time' => $current_time]], 'logout' => (function() { session_destroy(); return [200, ['msg' => 'ok']]; })(), 'tasks/today' => api_tasks_today($pdo, $uid, $dc), 'tasks/month' => api_tasks_month($pdo, $uid, $is_admin, $ym, ($is_admin && !empty($_GET['user_id'])) ? (int)$_GET['user_id'] : $uid, $dc), 'tasks/team' => api_tasks_team($pdo, $is_admin, $_GET['scope'] ?? 'today', $dc), 'tasks/print' => api_tasks_print($pdo, $uid, $is_admin, $_GET['task_id'] ?? null, $dc), 'tasks/status' => api_tasks_status($pdo, $d, $uid), 'tasks/save' => api_tasks_save($pdo, $d, $uid, $is_admin), 'tasks/delete' => api_tasks_delete($pdo, $d, $uid, $is_admin), 'tasks/batch' => api_tasks_batch($pdo, $d), 'rules/generate' => api_rules_generate($pdo, $d, ($is_admin && !empty($d['worker_id'])) ? (int)$d['worker_id'] : $uid, $d['ym'] ?? $ym), 'users' => match($method) { 'GET' => api_users_list($pdo), 'POST' => api_users_create($pdo, $d), default => [405, ['error' => 'method_not_allowed']], }, 'users/delete' => api_users_delete($pdo, $d), 'users/update' => api_users_update($pdo, $d), 'users/password' => api_users_password($pdo, $d, $uid), 'details' => match($method) { 'GET' => (function() use ($pdo) { [$c, $b] = api_details_get($pdo); $b['can_archive'] = function_exists('api_can_archive') ? api_can_archive() : false; return [$c, $b]; })(), 'POST' => api_details_save($pdo, $d), default => [405, ['error' => 'method_not_allowed']], }, 'details/delete' => api_details_delete($pdo, $d), default => isset($plugin_routes[$api]) ? ($plugin_routes[$api])($pdo, $d, $uid, $is_admin, $dc, $method) : [404, ['error' => 'unknown_endpoint']], }; if (defined('APP_DEBUG') && APP_DEBUG && !str_starts_with($api, 'debug_log')) { $api_ms = round((hrtime(true) - $time_start) / 1e6, 2); file_put_contents(TV_TIMERS_LOGFILE, '?' . $_SERVER['QUERY_STRING'] . "\tphp:" . $api_ms . "ms\tdb:" . ($time_db_ms ?? 0) . "ms\n", FILE_APPEND); } json_exit($body, $code); }
/* EOF src/api.php */



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


/* included from src/style.css [[[*/
:root{--safe-bottom: env(safe-area-inset-bottom, 0px);
--safe-top: env(safe-area-inset-top, 0px);
--font-base: sans-serif;
--font-print: sans-serif;
--font-b:700;
font-size: 16px;
--fs-xs: 0.7rem;
--fs-sm: 0.85rem;
--fs-base: 1rem;
--fs-lg: 1.1rem;
--fs-xl: 1.25rem;
--fs-2xl: 1.5rem;
--sp-1: 1px;
--sp-2: 1pt;
--sp-3: 4pt;
--sp-4: 0.5rem;
--radius: 2pt;
--c-dark: #333;
--c-muted: #555;
--c-muted-light: #7f8c8d;
--c-border: #ccc;
--c-silver: #bdc3c7;
--c-bg: #eee;
--c-surface: #fff;
--c-highlight: #e8f4f8;
--c-blue: #3498db;
--c-red: #e74c3c;
--c-amber: #f39c12;
--c-green: #2ecc71;
--c-yellow-bg: #E5DB67;
--c-yellow-border: #f1c40f;
--btn-blue-bg: var(--c-blue);
--btn-blue-border: #2980b9;
--btn-red-bg: #e86e60;
--btn-red-border: #c0392b;
--btn-orange-bg: #f5b041;
--btn-orange-border: #d68910;
--btn-orange-text: #3E1D00;
--btn-green-bg: #58d68d;
--btn-green-border: #28b463;
--btn-green-text: #1a5632;
--btn-silver-bg: var(--c-silver);
--btn-silver-border: #8AACC4;
--btn-silver-text: var(--c-dark);
--shadow-print: 0 0 var(--sp-4) rgba(0, 0, 0, 0.1);
}
:root.text-lg{font-size: 18px;
}
:root.text-xl{font-size: 20px;
}
* {box-sizing: border-box;
}
 body{-webkit-overflow-scrolling: touch;
background: var(--c-bg);
font-family: var(--font-base);
margin: 0 auto;
max-width: 600px;
padding: var(--sp-4);
}
nav a, .sub-nav a, button, summary, .card{user-select: none;
-moz-user-select: none;
-webkit-tap-highlight-color: transparent;
-webkit-user-select: none;
touch-action: manipulation;
}
input, button, select, textarea{font-size: var(--fs-lg);
border-radius: var(--radius);
}
input, select, textarea{width: 96%;
}
input[type="time"], input[type="date"], select[name="status"] {min-width:7rem;
width: auto;
}
input[type="time"], input[type="date"]{-webkit-tap-highlight-color: transparent;
background: var(--c-highlight);
}
textarea{height: 35vh;
border: var(--sp-1) solid var(--c-blue);
color: var(--c-blue);
white-space: pre;
font-family: 'Courier New', monospace;
}
table,tr,tr>textarea,tbody{width:100%;
}
 nav {display: flex;
flex-wrap: wrap;
gap: var(--sp-4);
margin-bottom: var(--sp-4);
}
 nav a{min-width: 100px;
padding: var(--sp-4);
flex: 1;
color: var(--c-surface);
background: var(--c-dark);
text-align: center;
text-decoration: none;
}
nav a.active {background: var(--c-blue);
font-weight: var(--font-b);
}
 .sub-nav {display: flex;
gap: var(--sp-3);
margin-bottom: var(--sp-4);
}
 .sub-nav a{padding: var(--sp-3);
flex: 1;
border-radius: var(--radius);
color: var(--c-dark);
background: var(--c-border);
text-align: center;
text-decoration: none;
}
.sub-nav a.active {color: var(--c-surface);
background: var(--c-dark);
}
 .user-info {width: 100%;
padding-bottom: var(--sp-4);
}
 .user-info b, .lang-toggle {color: var(--c-muted-light);
}
 .logout-btn {color: var(--c-red);
}
 .card{border-left: var(--sp-3) solid var(--c-dark);
border-radius: var(--radius) var(--radius) 0 0;
margin-bottom: var(--sp-4);
padding: 0 var(--sp-1) var(--sp-1) 0;
}
.card_header{padding: var(--sp-3) var(--sp-4);
font-weight: var(--font-b);
font-size: var(--fs-sm);
height:1.4rem;
display: block;
}
.card_body{margin: 0;
width: 100%;
padding: var(--sp-3);
background-color: rgba(255, 255, 255, 0.6);
}
#manager-task-form, #worker-task-form{background: var(--c-yellow-bg);
border-left-color: var(--c-yellow-border);
}
.vr-weeks,.vr-days {display: flex;
gap: var(--sp-1);
font-size: var(--fs-xs);
border: var(--sp-1) solid var(--c-border);
padding: var(--sp-3);
border-radius: var(--radius);
background: var(--c-surface);
color:var(--c-blue);
}
 .vr-weeks label,.vr-days label {color:var(--c-muted-light);
}
datalist select, .mobile-select{background: var(--c-highlight);
}
.t-notes, input[name="notes"], textarea[name="notes"]{height: auto;
box-sizing: border-box;
min-height: 1px;
margin-top: var(--sp-4);
border: var(--sp-1) dashed var(--c-silver);
font-size: var(--fs-sm);
font-style: italic;
color: var(--c-muted-light);
}
button[name="tegu"] {cursor: pointer;
font-weight: var(--font-b);
}
 .save_ta{width: 96%;
padding: var(--sp-4);
border: 0;
color: var(--c-surface);
background: var(--c-green);
cursor: pointer;
font-weight: var(--font-b);
}
.hint {position: relative;
cursor: help;
}
 .hint::before{content:'?';
color:var(--c-yellow-border);
background:var(--c-muted-light);
font-size:var(--fs-xs);
padding:2px;
}
.hint::after{content: attr(data-hint);
position: absolute;
bottom: 125%;
left:160%;
transform: translateX(-50%);
border: var(--sp-2) dotted var(--c-yellow-border);
background: var(--c-dark);
color: white;
padding:var(--sp-3);
border-radius: var(--radius);
font-size: var(--fs-sm);
white-space: nowrap;
opacity: 0;
visibility: hidden;
transition: opacity 0.2s;
pointer-events: none;
}
.hint:hover::after, .hint:focus::after {opacity: 1;
visibility: visible;
}
 .btn-sm{width: auto;
min-width: 2rem;
padding: var(--sp-3) var(--sp-4);
align-items: center;
justify-content: center;
border: var(--sp-2) solid var(--_btn-bc, var(--c-surface));
border-radius: var(--radius);
color: var(--_btn-c, inherit);
background: var(--_btn-bg, transparent);
cursor: pointer;
display: inline-flex;
font-size: var(--fs-lg);
line-height: 1.4;
font-weight: var(--font-b);
text-decoration: none;
}
.btn-blue{--_btn-c: var(--c-surface);
--_btn-bg: var(--btn-blue-bg);
--_btn-bc: var(--btn-blue-border);
}
.btn-red{--_btn-c: var(--c-surface);
--_btn-bg: var(--btn-red-bg);
--_btn-bc: var(--btn-red-border);
}
.btn-orange{--_btn-c: var(--btn-orange-text);
--_btn-bg: var(--btn-orange-bg);
--_btn-bc: var(--btn-orange-border);
}
.btn-green{--_btn-c: var(--btn-green-text);
--_btn-bg: var(--btn-green-bg);
--_btn-bc: var(--btn-green-border);
}
.btn-silver{--_btn-c: var(--c-dark);
--_btn-bg: var(--btn-silver-bg);
--_btn-bc: var(--btn-silver-border);
}
.btn-icon{color: var(--c-red);
background: none;
border: none;
cursor: pointer;
font-size: var(--fs-xl);
}
.loc_det {position: relative;
padding-right: var(--sp-4);
cursor: pointer;
}
 .loc_det button {position: absolute;
top: var(--sp-4);
right: var(--sp-4);
}
 .u-realname, .u-contact{font-size: var(--fs-sm);
color: var(--c-muted);
margin-right:var(--sp-4);
}
.d-descr{margin-top: var(--sp-3);
font-size: var(--fs-sm);
color: var(--c-muted);
white-space: pre-line;
}
#add-workers-wrap{width:7rem;
}
 #team-toolbar{margin-bottom:var(--sp-3);
}
 .team_month_nav{display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: var(--sp-4);
}
 .team_worker_sel{background:var(--c-highlight);
border-left-color:var(--c-blue);
}
 .week-header{margin: var(--sp-4) 0 var(--sp-1) 0;
padding: 0 0 0 var(--sp-4);
color: var(--c-muted);
font-size: var(--fs-xs);
}
.team_group {background: var(--c-border);
}
 .team-row{width: 100%;
padding: var(--sp-3) 0;
border-bottom: var(--sp-1) dashed var(--c-bg);
cursor: pointer;
}
.team-row:last-child {border-bottom: none;
}
 .team-row .t-user {font-size: var(--fs-sm);
}
 .team-row .t-title {min-width: 3rem;
font-weight: var(--font-b);
overflow: hidden;
text-overflow: ellipsis;
}
.team-row .t-time {font-size: var(--fs-sm);
}
 .team-row .t-coworkers {padding-left: var(--sp-1);
font-size: var(--fs-sm);
}
 .team-row .t-actions {white-space: nowrap;
}
 .t-coworkers {color: var(--c-blue);
font-size: var(--fs-sm);
}
 .t-status {font-weight: var(--font-b);
}
 .status-0{color: var(--c-red);
}
.status-1{color: var(--c-amber);
}
.status-2{color: var(--c-green);
}
.status-2b {border-color: var(--c-green);
opacity: 0.7;
}
 select[name="status"] {border-left: var(--sp-3) solid var(--c-border);
}
 select[name="status"].status-0{border-left-color: var(--c-red);
}
select[name="status"].status-1{border-left-color: var(--c-amber);
}
select[name="status"].status-2{border-left-color: var(--c-green);
}
.visual-rule-row{border-bottom: var(--sp-1) dashed var(--c-silver);
padding: var(--sp-4);
display: flex;
flex-wrap: wrap;
gap: var(--sp-4);
align-items: center;
background: rgba(255,255,255,0.7);
}
 .conf_row{display:flex;
gap:var(--sp-3);
align-items:center;
margin-bottom:var(--sp-3);
}
 .conf_row kbd {min-width:30%;
}
 .conf_row input[type='text']{flex:1;
display:inline;
}
 .conf_hint{font-size:var(--fs-xs);
color:var(--c-muted-light);
margin-top:var(--sp-3);
}
 .conf_add{margin-top:var(--sp-4);
}
 .conf_add input {display:inline;
}
 .worker-page {page-break-after: always;
}
 .worker-page h2{border-bottom: var(--sp-2) solid var(--c-dark);
display: block !important;
padding-bottom: var(--sp-3);
}
.task-box{border: var(--sp-1) solid var(--c-dark);
margin-bottom: var(--sp-4);
padding: var(--sp-4);
page-break-inside: avoid;
}
.task-time{font-size: var(--fs-xl);
font-weight: var(--font-b);
}
.task-title{font-size: var(--fs-2xl);
font-weight: var(--font-b);
margin: var(--sp-3) 0;
}
.task-descr {margin-top: var(--sp-4);
white-space: pre-line;
}
 .task-meta{color: var(--c-dark);
font-style: italic;
margin-bottom: var(--sp-4);
}
.signature-row {display: flex;
gap: var(--sp-4);
margin-top: var(--sp-4);
}
 .signature-col {flex: 1;
}
 .signature-line{width: 100%;
margin-top: var(--fs-2xl);
padding-top: var(--sp-3);
border-top: var(--sp-1) dashed var(--c-dark);
display: block;
text-align: center;
font-size: var(--fs-xs);
}
@media screen{.print-view-active nav, .print-view-active .sub-nav, .print-view-active .user-info, .print-view-active .card, .print-view-active .logout-btn, .print-view-active h3, .print-view-active #today-tasks-container, .print-view-active #team-tasks-container, .print-view-active template{display: none;
}
.print-view-active{padding: var(--sp-4);
background: var(--c-bg);
}
.print-view-active .worker-page{margin: 0 auto var(--sp-4);
max-width: 800px;
padding: var(--sp-4);
display: block;
background: var(--c-surface);
box-shadow: var(--shadow-print);
}
}
 @media print{template,h3, nav, .no_Print, .card,.btn-sm, #today-tasks-container, #manager-task-form, #team-tasks-container {display: none !important;
}
 body{max-width: 100%;
margin: 0;
padding: 0;
color: var(--c-dark);
background: var(--c-surface);
font-family: var(--font-print);
}
.worker-page{margin: 0;
padding: 0;
max-width: 100%;
box-shadow: none;
display: block !important;
}
@page {margin: 1cm;
}
}
.hidden{display: none !important;
}
.text-right{text-align: right;
}
.text-center{text-align: center;
}
.ml-auto{margin-left: auto;
}
#reassign-wrap, #add-workers-wrap{display: inline-block;
}
.month-label{ font-size: var(--fs-xl);
}
.h3-section{margin: var(--sp-4) 0;
}
.card-rules{background: var(--c-highlight);
border-left-color: var(--c-blue);
margin-bottom: var(--sp-4);
}
.card-secondary{margin-top: var(--sp-4);
border-left-color: var(--c-muted-light);
}
.card_header--flex{display: flex;
justify-content: space-between;
align-items: center;
}
.card_body--no-pad{padding: 0;
}
.card_body--p10{padding: var(--sp-4);
}
.btn-compact{ margin: 0;
padding: var(--sp-1) var(--sp-4);
}
.btn-inactive{cursor: not-allowed !important;
opacity: 0.5;
}
.btn-del-done{cursor: not-allowed !important;
opacity: 0.3;
}
.form-mt{margin-top: var(--sp-4);
}
.summary-muted{cursor: pointer;
color: var(--c-muted);
margin-bottom: var(--sp-4);
}
.summary-settings{cursor: pointer;
font-weight: bold;
color: var(--c-muted);
}
.textarea-debug{height: 20vh;
color: var(--c-green);
background: var(--c-dark);
font-family:monospace;
}
.textarea-sm{height: 10vh;
}
.vr-input-title{width: 110px;
flex-grow: 1;
}
.vr-input-time{ width: 6rem;
}
@keyframes flashHighlight{0%{background-color: var(--c-highlight);
}
100%{background-color: transparent;
}
}
 .highlight-flash{animation: flashHighlight 1.5s ease forwards;
}
.notes-draft-flash{animation: flashHighlight 0.6s ease forwards;
}
.t-coworkers:not(:empty)::before{content: '👥 ';
}
.t-notes:not(:empty)::before{content: '✎ ';
}
.sig-contact::before, .u-realname:not(:empty)::before, .d-contact:not(:empty)::before{content: '👤 ';
}
.d-address:not(:empty)::before, .task-meta:not(:empty)::before{content: '📍 ';
}
.sig-worker-contact:not(:empty)::before, .u-contact:not(:empty)::before{content: '📞 ';
}
.task-time::before, input[type="time"]::before{content: '⏰ ';
}
input[type="date"]::before{content: '🗓 ';
}
#worker-month-container, #team-tasks-container{min-height: 50vh;
}
#visual-rules-container{min-height: 3em;
}
/* EOF src/style.css */



<?php // Plugin CSS files
if (is_dir($plugin_dir)) foreach (glob($plugin_dir . '*.css') as $pcss) include $pcss;
?>


/* included from ./plugins/audit2.css [[[*/
#audit-shell{max-width: 600px;
margin: 0 auto;
}
#audit-toolbar{margin-bottom: var(--sp-4);
}
#audit-due-banner .aud-due-row{display: flex;
justify-content: space-between;
align-items: center;
background: var(--c-bg-alt);
border-left: 4px solid var(--c-primary);
margin-bottom: var(--sp-2);
padding: var(--sp-3);
}
.aud-item{padding: var(--sp-3);
margin-bottom: var(--sp-2);
border-bottom: 1px solid var(--c-border);
}
.aud-item:last-child{border-bottom: none;
}
.aud-task-row{display: flex;
flex-direction: column;
gap: var(--sp-2);
padding: var(--sp-3) 0;
border-bottom: 1px solid var(--c-border);
}
.aud-task-row label{display: flex;
align-items: flex-start;
gap: var(--sp-3);
cursor: pointer;
font-weight: var(--font-b);
}
.aud-task-row .aud-chk{width: 1.2rem;
height: 1.2rem;
margin-top: 2px;
}
.aud-task-row .aud-cmt{width: 100%;
min-height: 3rem;
font-size: 0.9rem;
padding: var(--sp-2);
border-radius: var(--radius);
border: 1px solid var(--c-border);
background: var(--c-bg-alt);
}
#audit-panel-templates input, #audit-panel-templates select, #audit-panel-templates textarea{display: block;
width: 100%;
margin-bottom: var(--sp-2);
}
#audit-print-container{padding: var(--sp-4);
background: #fff;
color: #000;
}
.aud-print-meta{margin-bottom: var(--sp-4);
padding-bottom: var(--sp-3);
border-bottom: 2px solid #000;
}
.aud-print-list{width: 100%;
border-collapse: collapse;
}
.aud-print-list td{padding: var(--sp-2);
border-bottom: 1px solid #ccc;
vertical-align: top;
}
.aud-print-check{width: 80px;
font-weight: bold;
}
.aud-print-name{font-weight: bold;
}
.aud-print-comment{font-weight: normal;
font-style: italic;
font-size: 0.9rem;
margin-top: 4px;
white-space: pre-wrap;
}
.hidden{display: none !important;
}
@media print{.no_Print, #audit-toolbar, .btn{display: none !important;
}
#audit-print-container{padding: 0;
}
}
/* EOF ./plugins/audit2.css */


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




/* included from src/init.js [[[*/
// ─── I18N ───
// Inlined by PHP in <head> — no separate fetch needed.
let i18n = (typeof _i18n_data !== 'undefined') ? _i18n_data : {};

/** Translate key → localized string. Mirrors PHP __() function. */
function __(k) { return i18n[k] || k; }

// ─── PRELOADED DATA ───
// Data fetch started in <head> by PHP, resolved here.
let _prefetch = (typeof _preload !== 'undefined') ? _preload : null;

// ─── URL STATE ───
const CURRENT_VIEW  = new URLSearchParams(location.search).get('view') || 'today';
const CURRENT_SCOPE = new URLSearchParams(location.search).get('scope') || 'today';
const CURRENT_YM	= new URLSearchParams(location.search).get('ym') || '';

// ─── TEXT SIZE ───
const TEXT_SIZES = ['', 'text-lg', 'text-xl'];
(function() { const s = localStorage.getItem('textSize');
	if (s) document.documentElement.classList.add(s);
})();

function cycleTextSize() {
	const root = document.documentElement;
	const cur = TEXT_SIZES.findIndex(s => s && root.classList.contains(s));
	const next = ((cur < 0 ? 0 : cur) + 1) % TEXT_SIZES.length;
	TEXT_SIZES.forEach(s => { if (s) root.classList.remove(s); });
	if (TEXT_SIZES[next]) root.classList.add(TEXT_SIZES[next]);
	localStorage.setItem('textSize', TEXT_SIZES[next]);
}

// ── HTML generation helpers ──

/** Escape string for safe HTML insertion. */
function escHtml(s) {
	// const d = document.createElement('div'); d.textContent = s; return d.innerHTML; // uses DOM
	return (''+s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}



/** Compile a {{tag}} template string into a fast function. One regex at load, zero per row. */
function compileTpl(html) {
	const parts = html.split(/\{\{(\w+)\}\}/);
	// parts = ['<tr...', 'id', '"> ...', 'title', '...']
	// even indices = literal strings, odd = key names
	return function(data) {
		let s = parts[0];
		for (let i = 1; i < parts.length; i += 2)
			s += (data[parts[i]] ?? '') + parts[i + 1];
		return s;
	};
}



/** Build <option> list HTML from [{id,username}] or string[]. */
function optionsHtml(items, valKey, txtKey, selectedVal, blankLabel) {
	let s = blankLabel ? '<option value="">' + escHtml(blankLabel) + '</option>' : '';
	items.forEach(item => {
		const v = valKey ? item[valKey] : item;
		const txt = txtKey ? item[txtKey] : item;
		const sel = (selectedVal && v == selectedVal) ? ' selected' : '';
		s += '<option value="' + escHtml(String(v)) + '"' + sel + '>' + escHtml(String(txt)) + '</option>';
	});
	return s;
}
const $ = i => document.getElementById(i);

/**
 * Unified lazy-loading via IntersectionObserver.
 * Renders shell divs immediately, fills content when scrolled near.
 * @param {string} containerId - target container element id
 * @param {Array} items - data items to render
 * @param {string} keyAttr - data-attribute name for lookup (e.g. 'id', 'title', 'date')
 * @param {Function} keyFn - item => string key value for the data-attribute
 * @param {Function} shellFn - item => shell HTML string (must include data-lazy="1" and data-{keyAttr})
 * @param {Function} fillFn - (el, item) => void, fills a shell element with content
 * @param {string} [emptyMsg] - message when items is empty
 * @returns {IntersectionObserver|null} - the observer (caller stores for teardown)
 */
function lazyRender(containerId, items, keyAttr, keyFn, shellFn, fillFn, emptyMsg) {
	const container = $(containerId);
	if (!container) return null;
	if (!items || !items.length) {
		container.innerHTML = emptyMsg ? '<p>' + escHtml(emptyMsg) + '</p>' : '';
		return null;
	}
	container.innerHTML = items.map(shellFn).join('');
	const obs = new IntersectionObserver(entries => {
		for (const entry of entries) {
			if (!entry.isIntersecting) continue;
			const el = entry.target;
			if (!el.dataset.lazy) continue;
			const match = items.find(x => String(keyFn(x)) === el.dataset[keyAttr]);
			if (match) { fillFn(el, match); el.removeAttribute('data-lazy'); }
			obs.unobserve(el);
		}
	}, { rootMargin: '200px' });
	container.querySelectorAll('[data-lazy]').forEach(el => obs.observe(el));
	return obs;
}
/* EOF src/init.js */


</script>
</head>
<body>
<?php 



/* included from src/views.php [[[*/
$is_month = ($scope === 'month'); 
function html_lngTgl($view='0'):void { global $scope,$lang,$i18ni; foreach (array_keys($i18ni) as $i){ if($lang != $i) echo sprintf('<a href="?view=%s&lang=%s%s" class="lang-toggle">%s</a> ', $view, $i, $scope?"&scope=$scope":'', strtoupper($i) ); } }
 
function html_hint($hint=''){ $hint=htmlspecialchars($hint); echo <<<HINT
tabindex="0" data-hint="$hint" role="tooltip" aria-label="$hint"
HINT;
}
 
function view_login(): void { ?>
<form class="card" id="login-form" onsubmit="doLogin(event)">
	<div class="text-right"><?= html_lngTgl() ?></div>
	<label><?= __('g_username') ?><br><input type="text" name="username" required autofocus></label><br>
	<label><?= __('g_pass') ?><br><input type="password" name="password" required></label><br>
	<button type="submit" class="save_ta"><?= __('btn_enter') ?></button>
</form>
<?php }
 
function view_change_password(): void { $force = isset($_GET['msg']) && $_GET['msg'] === 'force_change'; ?>
<form class="card" onsubmit="changePassword(event)">
<div class="card_header"><?= __('change_password') ?></div>
<div class="card_body">
<?php if ($force): ?>
<p class="status-0"><?= __('msg_force_change_required') ?></p>
<?php endif; ?>

<label><?= __('old_password') ?><br>
<input type="password" name="old_password" required minlength="4">
</label><br>
<label><?= __('new_password') ?><br>
<input type="password" name="new_password" required minlength="4">
</label><br>

<button type="submit" class="save_ta btn-green"><?= __('g_btn_save') ?></button>
</div>
</form>
<?php
}
 
function view_nav($view,$nav_items, $is_admin, $u_name): void { global $today_date; echo '<nav>'; foreach ($nav_items as $key => $label){ echo sprintf('<a href="?view=%s" %s>%s</a>', $key, $view===$key? 'class="active"' : '', $label ); } ?>
</nav>

<table class="user-info no_Print"><thead><tr>
<td><?= __('g_worker') ?> <b><a href="?view=user_info"><?= htmlspecialchars($u_name) ?></a></b></td>
<td><b><?= $today_date ?></b></td>
<th><button type="button" onclick="cycleTextSize()" class="lang-toggle no_Print" title="Text size"><small>A</small>A</button></th>
<th><?= html_lngTgl($view) ?>
<a href="#" onclick="doLogout(event)" class="logout-btn no_Print"><?= __('logout') ?></a></th> 
</tr></thead></table>
<?php }
 
function view_today(): void { ?>
<div id="today-tasks-container"></div>
<?php }
 
function view_rules(): void { global $ym, $is_admin; ?>
<div id="rules-shell" data-ym="<?= $ym ?>">

<div class="team_month_nav">
<!-- links filled by JS from API prev_ym/next_ym) -->
	<a id="nav-prev-ym" href="#" class="btn-sm btn-blue">&laquo;</a>
	<strong class="month-label"><?= $ym ?></strong> <button onclick="scrollToToday('worker-month-container')" class="btn-sm btn-silver"><?= __('nav_today') ?></button>
	<a id="nav-next-ym" href="#" class="btn-sm btn-blue">&raquo;</a>
</div>
<p class="text-center"><a href="#worker-task-form"><?= __('rules_title') ?></a>	•	<a href="#last_month_readonly"><?= __('H_last_month') ?></a>
</p>

<?php if ($is_admin) { ?>
<form class="card team_worker_sel" >
<!-- filled by JS -->
	<label class="card_header" for="rules-worker-select"><?= __('g_sel_worker') ?></label>
	<select id="rules-worker-select" class="card_body" onchange="switchRulesWorker(this.value)"></select>
</form>
<?php } ?>

<h3><?= __('nav_rules') ?></h3>

<!-- Visual Rules Editor -->
<div class="card card-rules">
	<div class="card_header card_header--flex">

<span>📝</span>
	<button type="button" onclick="addVisualRule()" class="btn-sm btn-silver btn-compact">+ <?= __('g_btn_add') ?></button>
	</div>
	<div id="visual-rules-container" class="card_body card_body--no-pad"></div>
	<div id="visual_rules_bottom"></div>
</div>

<details>
<summary class="summary-muted">⚙️ <?= __('jsonDebug') ?></summary>
<!-- synced with visual editor -->
	<textarea id="rules-textarea" name="rules_txt" placeholder="[]" class="textarea-debug"></textarea>
</details>
<br>

<button onclick="generateRules()" class="save_ta btn-blue"><?= __('rules_save') ?></button>
<p><i><?= __('rules_note') ?></i></p>

<!-- Editable month UI -->
<h3 class="h3-section"><?= __('rules_title') ?></h3>
<form class="card" onsubmit="saveTaskUI(event)" id="worker-task-form">
<div id="task-form-top" class="card_header"><?= __('g_click_to_edit') ?></div><div class="card_body">
	<?php if ($is_admin) { ?>
	<label><?= __('g_sel_worker') ?>: <select name="user_id" id="rules-task-worker-select"></select></label><br>
	<?php } ?>
	<?= html_task_fields() ?>
	<?= html_save_cancel('','', false) ?>
</div></form>

<div id="worker-month-container"></div>

<h3 id="last_month_readonly"><?= __('H_last_month') ?></h3>
<pre id="last-month-data"></pre>
</div>
<?php }
 
function view_user_info(): void { ?>
<details class="card card-secondary">
	<summary class="summary-settings"><?= __('change_password') ?></summary>
	<form onsubmit="changePassword(event)" class="form-mt">
		<input type="password" name="old_password" placeholder="<?= __('old_password') ?>" required minlength="4">
		<input type="password" name="new_password" placeholder="<?= __('new_password') ?>" required minlength="4">
		<button type="submit" class="btn-sm btn-blue"><?= __('g_btn_save') ?></button>
	</form>
</details>
<?php }
 
function view_print(): void { ?>
<div id="print-container"></div>
<?php }
 
function view_team_nav(): void { global $scope, $is_month; ?>
<div class="sub-nav no_Print">
	<a href="?view=team&scope=today" <?= $scope === 'today' ? 'class="active"' : '' ?>><?= __('team_today') ?></a>
	<a href="?view=team&scope=month" <?= $is_month ? 'class="active"' : '' ?>><?= __('team_month') ?></a>
	<a href="?view=team&scope=wobjects" <?= $scope === 'wobjects' ? 'class="active"' : '' ?>><?= __('team_wobj') ?></a>
	<a href="?view=team&scope=baastegijad" <?= $scope === 'baastegijad' ? 'class="active"' : '' ?>><?= __('team_base') ?></a>
</div>
<?php }
 
function view_team_tasks(): void { global $scope, $is_month, $today_date; view_team_nav(); ?>
<form class="card" onsubmit="saveTaskUI(event)" id="manager-task-form">
<div id="task-form-top" class="card_header"><?= __('add_act') ?></div><div class="card_body">
<?= html_task_fields($today_date) ?>

<table role="presentation"><tr><td>
	<label id="reassign-wrap" class="hidden"><?= __('g_sel_worker') ?> (<?= __('sel_reassign') ?>):
		<select name="user_id" id="reassign-select"><option value="">—<?= __('g_sel_worker') ?>—</option></select>
	</label>
	<label id="add-workers-wrap" class="hint" <?= html_hint(__('how_select_multiple')) ?> >
<select name="worker_ids" id="add-workers-select" multiple required size="4">
	<option value="">— <?= __('g_sel_worker') ?> —</option>
</select>
	</label></td>
	
<td><?= html_save_cancel(__('g_btn_add') . '/' . __('g_btn_save')) ?></td></tr></table>

</div></form>

<div id="team-toolbar" class="hidden no_Print">
	<button onclick="scrollToToday('team-tasks-container')" class="btn-sm btn-silver">📅 <?= __('nav_today') ?></button>
	<button onclick="downloadTeamCSV('<?= __('teamTasks_csv') ?>-<?= $today_date ?>.csv')" class="btn-sm btn-silver">⬇️ CSV</button>
	<a href="?view=print" target="_blank" class="btn-sm btn-silver">🖨 <?= __('print_todays') ?></a>
</div>
<div id="team-tasks-container"></div>
<?php }
 
function view_objloc_mgmt(): void { view_team_nav(); ?>
<!-- Location details editor -->
<form class="card" onsubmit="saveDetails(event)" id="details-form">
	<strong><?= __('ld_form_title') ?></strong><br>
	<input type="text" name="title" list="detail-titles" placeholder="<?= __('ld_ph_title') ?>" required oninput="populateDetails(this.value)">
	<datalist id="detail-titles"></datalist><br>
	<input type="text" name="address" placeholder="<?= __('ld_ph_address') ?>" list="known-addresses"><br>
	<datalist id="known-addresses"></datalist>
	<input type="text" name="related_person" placeholder="<?= __('ld_ph_contact') ?>" list="known-contacts"><br>
	<datalist id="known-contacts"></datalist>
	<br>
	<textarea name="description" placeholder="<?= __('ld_ph_desc') ?>" class="textarea-sm"></textarea><br>
	<button type="submit" class="btn-sm btn-green"><?= __('g_btn_add') ?> / <?= __('g_btn_save') ?></button>
</form>
<h3 class="h3-section"><?= __('ld_saved_title') ?></h3>
<div id="details-list-container"></div>
<?php }
 
function view_team_mgmt(): void { view_team_nav(); ?>
<p>
	<a href="#user-mgmt-form" ><?= __('um_um') ?></a> | 
	<a href="#db_backup_archive" ><?= __('db_backup') ?></a> | 
	<a href="#config-card" ><?= __('cfg_title') ?></a>
</p>

<!-- User add/edit form -->
<form class="card" onsubmit="saveUser(event)" id="user-mgmt-form">
	<div id="user-form-top" class="card_header"><?= __('um_add_new') ?></div>
	<div class="card_body">
	<input type="hidden" name="id" value="">
	<table role="presentation"><tr>
	<td><input type="text" name="username" placeholder="<?= __('g_username') ?>" required></td>
	<td><input type="text" name="password" placeholder="<?= __('g_pass') ?>"></td></tr>
	<tr><td><input type="text" name="real_name" placeholder="<?= __('g_real_name') ?>"></td>
	<td><input type="text" name="contact" placeholder="<?= __('g_contact_data') ?>"></td></tr>
	</table>
	<span id="user-pass-hint" class="hidden"><i><?= __('um_pass_hint') ?></i></span>
	<?= html_save_cancel() ?>
	</div>
</form>

<h3 class="h3-section"><?= __('um_um') ?></h3>
<div id="users-list-container"></div>

<!-- Database backup -->
<div class="card" id="db_backup_archive">
	<p><strong><a href="?api=backup"><?= __('db_backup') ?></a></strong></p>

<?= __('db_state') ?>: <kbd id="db-status"></kbd>

	<div id="archive-btn-slot">
	<button type="button" id="btn-archive" onclick="archiveYear(this)" 
	class="btn-red btn-inactive" disabled><?= __('backup_year') ?>
	</button>
	</div>
</div>

<!-- Config key-value store -->
<div class="card" id="config-card">
	<h3><?= __('cfg_title') ?></h3>
	<div id="config-rows" ></div>
	
	
	<table role="presentation" class="conf_add"><tr><td>
	<input type="text" id="cfg-new-key" placeholder="<?= __('cfg_ph_key') ?>">
		</td><td>
		<input type="text" id="cfg-new-val" placeholder="<?= __('cfg_ph_val') ?>" ></td><td>
		<button type="button" class="btn-sm btn-green" onclick="saveConfig()">+</button>
		</td></tr></table>
</div>
<?php }
 
function view_templates(): void { ?>
<script>
const _tplGroup = compileTpl(<?= json_encode( '<div class="card team_group" {{data_date}}>' .'<div class="group-header card_header">{{header}}</div>' .'<table role="presentation" class="group-items card_body card_body--p10">{{rows}}</table>' .'</div>') ?>);
const _tplRow = compileTpl(<?= json_encode( '<tr class="team-row no_Print" data-id="{{id}}" title="' . __('g_click_to_edit') . '">' .'<td><span class="t-user {{user_cls}}"><b>{{username}}</b></span><br>' .'<span class="t-title">{{title}}</span><br>' .'<span class="t-coworkers">{{coworkers}}</span></td>' .'<td><span class="t-time">{{time}}</span><br>' .'<span class="t-status status-{{status}}">{{status_text}}</span></td>' .'<td width="20%"><span class="t-actions">' .'<a class="btn-sm btn-silver print-link" href="?view=print&task_id={{id}}" target="_blank">🖨️</a>&nbsp;' .'<button class="{{del_cls}}" title="' . __('g_btn_delete') . '" {{del_dis}}>✖</button>' .'</span></td></tr>' .'<tr><td colspan="3" class="t-notes">{{notes}}</td></tr>') ?>);
const _tplTask = compileTpl(<?= json_encode( '<form class="card {{card_cls}}" onsubmit="updateTask(event)">' .'<table role="presentation"><tbody><tr><td>' .'<strong>{{title}}</strong><br>' .'<span class="t-coworkers">{{coworkers}}</span></td>' .'<td><input type="time" name="start_time" value="{{start_time}}">' .'<input type="time" name="end_time" value="{{end_time}}"></td>' .'<td width="20%">' .'<a class="btn-sm btn-silver print-link" href="?view=print&task_id={{id}}" target="_blank">🖨️</a>' .'<input type="hidden" name="status" value="{{status}}">' .'<input type="hidden" name="id" value="{{id}}">' .'<button type="submit" name="tegu" class="t-status btn-sm {{btn_cls}}" {{btn_dis}}>{{status_text}}</button></td></tr>' .'<tr><td colspan="3"><textarea name="notes" placeholder="' . __('g_ph_notes') . '">{{notes}}</textarea>{{description}}</td></tr>' .'</tbody></table></form>') ?>);
</script>

<template id="print-page-template">
<div class="worker-page">
	<div class="print-org-header"></div>
	<h2 class="p-heading"></h2>
</div>
</template>
<template id="print-task-template">
<div class="task-box">
	<div class="task-time"></div>
	<div class="task-title"></div>
	<div class="task-meta"></div>
	<div class="task-descr"></div>
	<div class="signature-row">
		<div class="signature-col"><span class="sig-worker"></span><br><span class="sig-worker-contact"></span><div class="signature-line"><?= __('print_signature_line') ?></div></div>
		<div class="signature-col"><span class="sig-contact"></span><div class="signature-line"><?= __('print_signature_line') ?></div></div>
	</div>
</div>
</template>

<template id="visual-rule-template">
<div class="visual-rule-row">
	<input type="text" class="vr-title vr-input-title" placeholder="<?= __('g_ph_location') ?>" required>
	<div class="vr-days">
	<?php  foreach(explode(' ',__('rules_days_sh')) as $wdi => $wd) { if($wdi>0) echo "	<label><input type=\"checkbox\" value=\"{$wdi}\">{$wd}
</label>
"; } ?>
		</div> 

	<div class="vr-weeks" >
	<span class="hint" <?= html_hint(__('rules_full_weeks')) ?> >
	<?= __('g_week') ?>
	</span>
	<?php foreach([1,2,3,4] as $w) echo "<label><input type='checkbox' value='$w'>$w</label>"; ?>
	</div>

	<input type="time" class="vr-start vr-input-time" required>
	<input type="time" class="vr-end vr-input-time" required>

	<button type="button" class="btn-icon btn-del-vr ml-auto" title="<?= __('g_btn_delete') ?>">✖</button>
</div>
</template>

<template id="config-row-template">
<div class="conf_row">
	<code class="c-key"></code>
	<input type="text" class="c-val">
	<button type="button" class="btn-sm btn-red btn-compact btn-del-cfg">✖</button>
</div>
</template>

<?php } function html_status_select(string $name='status'): string { $s = <<<SEL
<select name="$name">
<option value="0">%s</option>
<option value="1">%s</option>
<option value="2">%s</option></select>
SEL;
return sprintf($s, __('status_0'), __('status_1'), __('status_2') ); } function html_task_fields(string $date_value=''): string { return sprintf( '<input type="hidden" name="id" value="">
<label>
<input type="text" name="title" placeholder="%s" list="known-task-titles" required>
<datalist id="known-task-titles"></datalist></label>
<table role="presentation"><tr>
<td>%s:</td><td>%s</td>
<td>%s:</td><td><input type="date" name="task_date" value="%s" required></td></tr>

<tr><td>%s:</td><td><input type="time" name="start_time"></td>
<td>%s:</td><td><input type="time" name="end_time"></td></tr>
</table>
<br><textarea name="notes" placeholder="%s"></textarea>' , __('g_ph_location'), __('Status_sh'), html_status_select(), __('date_short'), $date_value, __('start_short'), __('end_short'), __('g_ph_notes') ); } function html_save_cancel(string $save_label='',string $clear_label='',bool $br=true): string { if (!$save_label) $save_label=__('g_btn_save'); if (!$clear_label) $clear_label=__('g_btn_clear'); return sprintf('<button type="submit" class="btn-sm btn-green">%s</button>%s<button type="button" 
	class="btn-sm btn-silver" 
	onclick="this.form.reset(); this.form.id.value=\'\';">%s</button>', $save_label, ($br?'<br>':''), $clear_label); } if (!$logged_in) { view_login(); } else { $nav_items = [ 'today' => __('nav_today'), 'rules' => __('nav_rules'), ...($is_admin? ['team' => __('nav_team')] : []) ]; foreach ($plugin_nav as $pnk => $pnv) { if (isset($plugins[$pnk]['admin_only']) && $plugins[$pnk]['admin_only'] && !$is_admin) continue; $nav_items[$pnk] = $pnv; } view_nav($view,$nav_items, $is_admin, $u_name); match($view) { 'today' => view_today(), 'rules' => view_rules(), 'change_password' => view_change_password(), 'user_info' => view_user_info(), 'print' => view_print(), 'team' => match($scope) { 'baastegijad' => view_team_mgmt(), 'wobjects'=> view_objloc_mgmt(), default => view_team_tasks(), }
, default => isset($plugin_views[$view]) ? ($plugin_views[$view])() : view_today(), }; view_templates(); }
/* EOF src/views.php */



?>
<script>


/* included from src/app.js [[[*/
/**
 * Töö Värk — Client Application
 *
 * ARCHITECTURE:
 *	All data flows through apiCall() to/from the REST API (?api=endpoint).
 *	fetches data and renders. Templates are cloned from HTML.
 *
 * INIT/REFRESH SPLIT:
 *	init*()	= bind event listeners (once) + call refresh*()
 *	refresh*() = fetch API data + render DOM (safe to call repeatedly)
 *	After a successful save: cooldown button + reset form + refresh*()
 */

// ─── CORE: API Communication
async function apiCall(endpoint, data = null, btnElement = null, waitText = '...', failText = '', extraParams = '') {
	if (btnElement) { btnElement.disabled = true; btnElement.innerText = waitText; }
	try {
		const opts = data !== null
			? { method: 'POST', body: JSON.stringify(data), headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN} }
			: { method: 'GET' };
		const res = await fetch('?api=' + endpoint + extraParams, opts);
		const ct = res.headers.get('content-type') || '';
		if (!ct.includes('application/json')) throw new Error(res.status + ' ' + res.statusText);
		const json = await res.json();
		if (json.error) throw new Error(json.error);
		return json;
	} catch (err) {
		alert(__('g_err_conn') + (err.message ? " " + err.message : ""));
		if (btnElement) { btnElement.disabled = false; btnElement.innerText = failText || __('g_btn_retry'); }
		return null;
	}
}

/** Shorthand: GET from API. Uses preloaded data on first call if available. Tracks fetch duration. */
let _fetchMs = 0;
async function apiGet(endpoint, params = '') {
	const t = performance.now();
	let data;
	if (_prefetch) {
		data = await _prefetch;
		_prefetch = null;
		if (!data || data.error) data = null;
	}
	if (!data) data = await apiCall(endpoint, null, null, '', '', params);
	_fetchMs += performance.now() - t;
	return data;
}


/**
 * Re-enable after cooldown.
 */
function btnCooldown(btn, label, ms) {
	if (!btn) return;
	btn.disabled = true;
	btn.innerText = label || __('g_btn_done');
	setTimeout(() => { btn.disabled = false; btn.innerText = label; }, ms || 2000);
}


// ─── AUTH

/** POST credentials, redirect to today view on success. */
async function doLogin(e) {
	e.preventDefault();
	const f = e.target, btn = f.querySelector('button');
	const res = await apiCall('login', {
		username: f.username.value, password: f.password.value
	}, btn, __('g_btn_wait'), __('g_btn_retry'));
	if (res) location.href = '?view=today';
}


/** Destroy session, redirect to login. */
function doLogout(e) {
	e.preventDefault();
	apiCall('logout', {}).then(() => location.href = '?');
}


/** Verify old password, set new one. */
async function changePassword(e) {
	e.preventDefault();
	const f = e.target, btn = f.querySelector('button');
	const res = await apiCall('users/password', {
		old_password: f.old_password.value,
		new_password: f.new_password.value
	}, btn, __('g_btn_wait'), __('g_btn_retry'));
	if (res) { f.reset(); location.href = '?view=today'; }
}


// ─── HELPERS

/** Populate a <select> with worker options. */
function fillWorkerSelect(el, workers, selectedId, blank) {
	if (el) el.innerHTML = optionsHtml(workers, 'id', 'username', selectedId, blank ? __('g_sel_worker') : '');
}


// ─── TODAY VIEW

/** Fetch today's tasks (+ yesterday's unfinished) and render cards. */
async function initTodayView() {
	const tasks = await apiGet('tasks/today');
	if (tasks) renderTasks(tasks);
}


/** Map status int → button colour class. */
const STATUS_BTN_CLASSES = ['btn-red', 'btn-orange', 'btn-green'];

/** Apply colour class + disabled state to a status button. */
function setStatusBtn(btn, status) {
	btn.classList.remove('btn-red', 'btn-orange', 'btn-green');
	btn.classList.add(STATUS_BTN_CLASSES[status] ?? 'btn-green');
	btn.disabled = (status >= 2);
	btn.classList.toggle('btn-inactive', status >= 2);
}


/** Render today tasks using compiled template. */
function renderTasks(tasks) {
	const container = $('today-tasks-container');
	if (!container) return;

	const parts = [];
	for (let i = 0; i < tasks.length; i++) {
		const t = tasks[i];
		const statusCls = STATUS_BTN_CLASSES[t.status] ?? 'btn-green';
		const dis = t.status >= 2;
		parts.push(_tplTask({
			id:		t.id,
			card_cls:	dis ? 'status-2b' : '',
			title:	escHtml(t.title),
			coworkers:	(t.coworkers && t.coworkers.length) ? escHtml(t.coworkers.join(', ')) : '',
			start_time:  escHtml(t.start_time),
			end_time:	escHtml(t.end_time),
			status:	t.status,
			status_text: escHtml(t.status_text),
			btn_cls:	 statusCls + (dis ? ' btn-inactive' : ''),
			btn_dis:	 dis ? 'disabled' : '',
			notes:		escHtml(t.notes || ''),
			description:		escHtml(t.description || ''),
		}));
	}
	container.innerHTML = parts.join('');

	// Restore locally-saved draft notes — survives reload, tab switch,
	// browser crash, accidental navigation. Draft wins over server value
	// when both exist (user's in-progress edit takes priority). Cleared on
	// successful status update.
	container.querySelectorAll('form[onsubmit*="updateTask"]').forEach(f => {
		const id = f.id && f.id.value;
		if (!id) return;
		const draft = _notesDraftLoad(id);
		if (draft !== null && f.notes && draft !== f.notes.value) {
			f.notes.value = draft;
			_notesDraftFlash(f.notes, __('g_notes_draft_restored') || '↻');
		}
	});
}


// ─── NOTES DRAFT PERSISTENCE (client-only, localStorage)
//
// Each today-view task has a notes textarea that the worker may fill in
// over the course of a shift — before advancing status. The value is only
// sent to the server on status change (updateTask). A page reload, tab
// close, or accidental navigation loses unsaved typing. localStorage under
// task_notes_draft_${id} protects against that without adding a server
// round-trip for every keystroke. Drafts clear on successful status update.

const _NOTES_DRAFT_PREFIX = 'task_notes_draft_';
const _notesDraftKey = id => _NOTES_DRAFT_PREFIX + id;
let _notesDraftTimer = null;

function _notesDraftLoad(id) {
	try { return localStorage.getItem(_notesDraftKey(id)); }
	catch { return null; }
}

function _notesDraftSave(id, value) {
	try {
		if (value === '' || value == null) localStorage.removeItem(_notesDraftKey(id));
		else localStorage.setItem(_notesDraftKey(id), value);
	} catch { /* quota or private mode — silently skip */ }
}

function _notesDraftClear(id) {
	try { localStorage.removeItem(_notesDraftKey(id)); } catch {}
}

/** Brief visual cue that a change was persisted locally. */
function _notesDraftFlash(el, msg) {
	if (!el) return;
	el.classList.add('notes-draft-flash');
	el.setAttribute('title', msg || '✓');
	clearTimeout(_notesDraftTimer);
	_notesDraftTimer = setTimeout(() => el.classList.remove('notes-draft-flash'), 600);
}

// Delegated input handler — fires on every keystroke in any task notes
// textarea. Debouncing is unnecessary: localStorage.setItem is synchronous
// and fast (microseconds for a single short string), and a debounce would
// mean the last few keystrokes aren't saved if the browser crashes.
document.addEventListener('input', e => {
	const ta = e.target;
	if (ta.tagName !== 'TEXTAREA' || ta.name !== 'notes') return;
	const form = ta.closest('form');
	if (!form || !form.id || !form.id.value) return;
	// Only apply to today-view task forms (updateTask handler) — skip the
	// admin task editor and rules editor, which have their own save flow.
	if (!form.getAttribute('onsubmit') || !form.getAttribute('onsubmit').includes('updateTask')) return;
	_notesDraftSave(form.id.value, ta.value);
	_notesDraftFlash(ta);
});


/**
 * Status progression: 0→1→2.
 * Captures actual start/end times and sends to API.
 */
async function updateTask(e) {
	e.preventDefault();
	const f = e.target, btn = f.querySelector('button');
	const now = new Date().toTimeString().slice(0, 5);
	if (f.status.value === '0' && !f.start_time.value) f.start_time.value = now;
	if (f.status.value === '1') f.end_time.value = now;
	btn.disabled = true;

	const res = await apiCall('tasks/status', {
		id: f.id.value, start_time: f.start_time.value,
		end_time: f.end_time.value, status: f.status.value, notes: f.notes.value
	}, btn, __('g_btn_wait'), __('g_btn_retry'));

	if (!res) return;
	// Server now has the notes — clear the local draft to avoid stale
	// restore on next render.
	_notesDraftClear(f.id.value);
	f.status.value = res.status;
	f.start_time.value = res.start_time;
	f.end_time.value = res.end_time;
	if (f.status.tagName === 'SELECT') syncStatusColor(f.status);
	btn.innerText = res.msg;
	const newStatus = parseInt(res.status);
	if (newStatus < 2) setTimeout(() => { btn.disabled = false; setStatusBtn(btn, newStatus); }, 2000);
	else { f.classList.add('status-2b'); setStatusBtn(btn, newStatus); }
}


/** Sync status select border colour to its current value. */
function syncStatusColor(sel) {
	sel.classList.remove('status-0', 'status-1', 'status-2');
	sel.classList.add('status-' + sel.value);
}

document.addEventListener('change', e => {
	if (e.target.matches('select[name="status"]')) syncStatusColor(e.target);
});


// ─── RULES VIEW

let rulesData = null; // Cached API response for current rules view

/** Bind events once — textarea sync, visual editor listeners. */
async function initRulesView() {
	const ta = $('rules-textarea');
	if (ta) ta.addEventListener('input', syncTextToVisual);

	const vrContainer = $('visual-rules-container');
	if (vrContainer) {
		vrContainer.addEventListener('input', syncVisualToText);
		vrContainer.addEventListener('change', syncVisualToText);
		vrContainer.addEventListener('click', e => {
			if (e.target.closest('.btn-del-vr')) {
				e.target.closest('.visual-rule-row').remove();
				syncVisualToText();
			}
		});
	}

	await refreshRulesView();

	// Fired after Today button navigated here from another month
	if (location.hash === '#today') {
		history.replaceState(null, '', location.pathname + location.search);
		scrollToToday('worker-month-container');
	}
}


/** Fetch month data + render. Safe to call repeatedly after saves. Triggers a full network fetch and total DOM wipe. */
async function refreshRulesView() {
	const ym = CURRENT_YM || new Date().toISOString().slice(0, 7);
	const uid_param = new URLSearchParams(location.search).get('user_id') || '';
	const params = '&ym=' + ym + (uid_param ? '&user_id=' + uid_param : '');

	rulesData = await apiGet('tasks/month', params);
	if (!rulesData) return;

// Fill month navigation from API
const prevLink = $('nav-prev-ym');
const nextLink = $('nav-next-ym');
const uidSuffix = uid_param ? '&user_id=' + uid_param : '';
if (prevLink) { prevLink.href = '?view=rules&ym=' + rulesData.prev_ym + uidSuffix; prevLink.textContent = '« ' + rulesData.prev_ym; }
if (nextLink) { nextLink.href = '?view=rules&ym=' + rulesData.next_ym + uidSuffix; nextLink.textContent = rulesData.next_ym + ' »'; }

	const ta = $('rules-textarea');
	if (ta) ta.value = rulesData.rules_text;
	syncTextToVisual();

	['rules-worker-select', 'rules-task-worker-select'].forEach(id => {
		const el = $(id);
		if (el && rulesData.workers.length) fillWorkerSelect(el, rulesData.workers, rulesData.target_uid, false);
	});

	fillDatalist('known-task-titles', rulesData.known_titles || []);

	const dateInput = document.querySelector('#worker-task-form [name=task_date]');
	if (dateInput) dateInput.value = rulesData.today;

	renderTeamTasks(rulesData.grouped, true, 'worker-month-container');
	// scrollToToday called from initRulesView, not here (refresh runs on every month nav)

	const lm = $('last-month-data');
	if (lm) {
		if (!rulesData.last_month_data || !rulesData.last_month_data.length) {
			lm.innerHTML = '<i>' + __('no_data_last_month') + '</i>';
		} else {
			lm.textContent = rulesData.last_month_data.map(r => r.join("\t")).join("\n");
		}
	}
}


/**
 * Populate a <datalist> (or mobile fallback <select>) with title suggestions.
 * On non-Chrome mobile browsers, native <datalist> is buggy — we replace it
 * with a <select> that syncs its value back to the text input.
 */
function fillDatalist(id, titles) {
	if (!titles || !titles.length) return;
	const isChrome = /Chrome/i.test(navigator.userAgent) && !/Edg|OPR|Brave/i.test(navigator.userAgent) && (navigator.vendor === "Google Inc.");//if it breaks, Chrome shows simple select instead
	const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

	if (isMobile && !isChrome) {
		const inputs = document.querySelectorAll('input[list="' + id + '"]');
		inputs.forEach(input => {
		input.removeAttribute('list');

		let select = input.nextElementSibling;
		if (!select || !select.classList.contains('mobile-select')) {
			select = document.createElement('select');
			select.className = 'mobile-select';
			input.parentNode.insertBefore(select, input.nextSibling);

			select.addEventListener('change', function() {
					if (this.value) {
				input.value = this.value;
			input.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});
			}

	let html = '<option value="">—' + __('g_ph_location') + '—</option>';
	html += titles.map(v => '<option value="' + escHtml(String(v)) + '">' + escHtml(String(v)) + '</option>').join('');
			select.innerHTML = html;
		});

	} else {
		const dl = $(id);
		if (dl) {
			dl.innerHTML = titles.map(v => '<option value="' + escHtml(String(v)) + '">').join('');
		}
	}
}


/** Admin: switch rules view to another worker */
function switchRulesWorker(uid) {
	const ym = CURRENT_YM || new Date().toISOString().slice(0, 7);
	location.href = '?view=rules&ym=' + ym + '&user_id=' + uid;
}


/** Save rules text and trigger schedule generation, then refresh task list. */
async function generateRules() {
	const ta = $('rules-textarea');
	const ym = CURRENT_YM || new Date().toISOString().slice(0, 7);
	const uid_param = new URLSearchParams(location.search).get('user_id') || '';

	const btn = document.querySelector('.save_ta.btn-blue');
	const origText = btn ? btn.textContent : '';
	const res = await apiCall('rules/generate', {
		rules_txt: ta.value,
		ym: ym,
		worker_id: uid_param || undefined
	}, btn, __('g_btn_wait'), __('g_btn_retry'));

	if (res && res.msg === 'ok') {
		await refreshRulesView();
		btnCooldown(btn, origText, 2000);
	}
}


// ─── TEAM TASKS VIEW

let teamData = null;

/** Bind events once — row click→form, form reset. */
async function initTeamView() {
// Edit/Add mode swap: clicking a row switches from multi-assign to single-reassign
	document.addEventListener('click', function(e) {
		const row = e.target.closest('.team-row');
		if (row && row.dataset.id) {
			$('add-workers-wrap').classList.add('hidden');
			$('add-workers-select').required = false;
			$('reassign-wrap').classList.remove('hidden');
			const userName = row.querySelector('.t-user b').textContent;
			const rs = $('reassign-select');
			Array.from(rs.options).forEach(opt => { if (opt.text === userName) opt.selected = true; });
		}
	});
// Reset form → back to multi-assign mode
	$('manager-task-form')?.addEventListener('reset', function() {
		$('add-workers-wrap').classList.remove('hidden');
		$('add-workers-select').required = true;
		$('reassign-wrap').classList.add('hidden');
		$('reassign-select').value = '';
	});

	await refreshTeamView();

	if (location.hash === '#today') {
		history.replaceState(null, '', location.pathname + location.search);
		scrollToToday('team-tasks-container');
	}
}


/** Fetch team data + render. Safe to call repeatedly after saves. */
async function refreshTeamView() {
	const params = '&scope=' + CURRENT_SCOPE + (CURRENT_YM ? '&ym=' + CURRENT_YM : '');
	teamData = await apiGet('tasks/team', params);
	if (!teamData) return;

	fillWorkerSelect($('add-workers-select'), teamData.workers, null, true);
	fillWorkerSelect($('reassign-select'), teamData.workers, null, true);

	fillDatalist('known-task-titles', teamData.known_titles || []);

// Render tasks
	if (Object.keys(teamData.grouped).length) {
		const toolbar = $('team-toolbar');
		if (toolbar) toolbar.classList.remove('hidden');
		renderTeamTasks(teamData.grouped, teamData.is_month, 'team-tasks-container');
		// scrollToToday called from initTeamView, not here
	} else {
		$('team-tasks-container').innerHTML = '<p>' + escHtml(__('no_tasks_period')) + '</p>';
	}
}


// ─── TEAM MANAGEMENT VIEW

let detailsCache = [];
let usersCache = [];

/** Bind events once, then fetch+render. */
async function initTeamMgmt() {
	initUserFormIdHelper();

// Delegated click handler for user rows (bound once, survives re-renders)
	$('users-list-container')?.addEventListener('click', function(e) {
		const card = e.target.closest('.user-row');
		if (!card) return;
		if (e.target.closest('.btn-del-user')) {
			deleteUser(e, card.dataset.id);
		} else {
			populateUserForm(card.dataset.id);
		}
	});

// Reset form → back to add mode
	$('user-mgmt-form')?.addEventListener('reset', function() {
		this.id.value = '';
		this.password.required = true;
		$('user-form-top').textContent = __('um_add_new');
		$('user-pass-hint').classList.add('hidden');
	});

	await refreshTeamMgmt();
}


/** Fetch users + DB status + archive flag + config in one round-trip.
 * Uses bundled ?api=details (returns users+config too). Safe to call repeatedly.
 * Inline config edits use the lighter standalone ?api=config GET instead. */
async function refreshTeamMgmt() {
	const data = await apiGet('details');
	if (!data) return;
	_applyDetailsData(data);
	
	usersCache = data.users || [];
	renderUserRows(usersCache);
	
	const dbEl = $('db-status');
	if (dbEl) dbEl.textContent = data.db_status || '';

// Archive button (only enabled Dec 21+)
	const btnArchive = $('btn-archive');
	if (btnArchive && data.can_archive) {
		btnArchive.disabled = false;
		btnArchive.classList.remove('btn-inactive');
	}

	if (data.config) renderConfig(data.config);
}


/** Render user rows as lazy shells. Click delegation in initTeamMgmt. */
let _userObs = null;





/** Fill a single user card's innerHTML from user data. */
function _fillUserCard(card, u) {
	let contactHtml = '';
	let pr = '';
if (u.contact) {
	const safeContact = escHtml(u.contact);
if (u.contact.includes('@')) {
	pr ='mailto:'+safeContact;
	} 
else if (/^\+?[0-9\s\-()]+$/.test(u.contact)) {
	pr ='tel:'+safeContact;
	}
	
	contactHtml = `<a href="${pr}">${safeContact}</a>`;
}

	card.innerHTML = '<button type="button" class="btn-icon btn-del-user">&times;</button>'
		+ '<strong class="u-username">' + escHtml(u.username) + '</strong>'
		+ (u.real_name ? '<br><span class="u-realname">' + escHtml(u.real_name) + '</span>' : '')
		+ (contactHtml ? '<span class="u-contact">' + contactHtml + '</span>' : '');

}


function renderUserRows(users) {
	if (_userObs) { _userObs.disconnect(); _userObs = null; }
	const nonAdmin = users.filter(u => u.username !== 'admin');
	_userObs = lazyRender('users-list-container', nonAdmin, 'id',
		u => u.id,
		u => '<div class="card loc_det user-row" data-id="' + u.id + '" data-lazy="1"></div>',
		_fillUserCard,
		__('um_no_users')
	);
}


/** Click row → fill form for editing. */
function populateUserForm(userId) {
	const u = usersCache.find(x => x.id == userId);
	if (!u) return;
	const f = $('user-mgmt-form');
	if (!f) return;
	f.id_field_value_set(u.id);
	f.username.value = u.username;
	f.password.value = '';
	f.password.required = false;
	f.real_name.value = u.real_name || '';
	f.contact.value = u.contact || '';
	$('user-form-top').textContent = __('um_edit_user') + ': ' + u.username;
	$('user-pass-hint').classList.remove('hidden');
	$('user-form-top').scrollIntoView({behavior: 'smooth'});
}
/** Workaround: form.id collides with the DOM element's own id. */
function initUserFormIdHelper() {
	const f = $('user-mgmt-form');
	if (f) f.id_field_value_set = function(v) { f.querySelector('[name=id]').value = v; };
}


/** Save user: create or update. Surgical DOM + state update. */
async function saveUser(e) {
	e.preventDefault();
	const f = e.target, btn = f.querySelector('button[type=submit]');
	const origText = btn.textContent;
	const editId = f.querySelector('[name=id]').value;
	const payload = {
		username: f.username.value.trim(),
		password: f.password.value,
		real_name: f.real_name.value.trim(),
		contact: f.contact.value.trim()
	};

	let res;
	if (editId) {
		payload.id = editId;
		res = await apiCall('users/update', payload, btn, __('g_btn_wait'), __('g_btn_retry'));
		if (res && res.msg === 'ok') {
// state update
			const idx = usersCache.findIndex(u => u.id == editId);
			if (idx !== -1) Object.assign(usersCache[idx], { username: res.username, real_name: res.real_name, contact: res.contact });
// DOM update
			const card = document.querySelector('.user-row[data-id="' + editId + '"]');
			if (card) {
				card.querySelector('.u-username').textContent = res.username;
				let rn = card.querySelector('.u-realname');
				if (res.real_name) {
					if (!rn) { rn = document.createElement('span'); rn.className = 'u-realname'; card.querySelector('.u-username').insertAdjacentElement('afterend', rn); rn.insertAdjacentHTML('beforebegin', '<br>'); }
					rn.textContent = res.real_name;
				} else if (rn) rn.remove();
				let ct = card.querySelector('.u-contact');
				if (res.contact) {
					if (!ct) { ct = document.createElement('span'); ct.className = 'u-contact'; card.appendChild(ct); }
					ct.textContent = res.contact;
				} else if (ct) ct.remove();
				flashRow(card);
			}
		}
	} else {
		if (!f.password.value.trim()) { alert(__('g_pass')); btn.disabled = false; return; }
		res = await apiCall('users', payload, btn, __('g_btn_wait'), __('g_btn_retry'));
		if (res && res.msg === 'ok') {
// Add to cache + re-render to get sorted position
			usersCache.push({ id: res.id, username: res.username, real_name: res.real_name, contact: res.contact });
			usersCache.sort((a, b) => a.username.localeCompare(b.username));
			renderUserRows(usersCache);
			const newCard = document.querySelector('.user-row[data-id="' + res.id + '"]');
			if (newCard) {
				if (newCard.dataset.lazy) _fillUserCard(newCard, usersCache.find(x => x.id == res.id));
				flashRow(newCard);
			}
		}
	}
	if (res && res.msg === 'ok') {
		f.reset();
		btnCooldown(btn, origText, 2000);
	}
}


/** Delete a user. Surgical removal from cache + DOM. */
async function deleteUser(e, userId) {
	e.stopPropagation();
	const u = usersCache.find(x => x.id == userId);
	if (!confirm(__('um_del_confirm') + '\n\n' + (u ? u.username : ''))) return;
	const res = await apiCall('users/delete', { id: userId });
	if (res && res.msg === 'ok') {
		usersCache = usersCache.filter(x => x.id != userId);
		const card = document.querySelector('.user-row[data-id="' + userId + '"]');
		if (card) card.remove();

		const f = $('user-mgmt-form');
		if (f && f.querySelector('[name=id]').value == userId) f.reset();
	}
}


/** Year-end archival: rename tasks table. */
async function archiveYear(btn) {
	if (btn && btn.disabled) return;
	if (!confirm(__('um_Archive_year'))) return;
	const res = await apiCall('archive_year', {});
	if (res && res.msg === 'ok') await refreshTeamMgmt();
}


/**
 * Render location detail cards as lazy shells.
 * Click delegation handled in initObjLocMgmt() — this only builds DOM.
 */
let _detailObs = null;

/** Fill a single detail card's innerHTML from detail data. */
function _fillDetailCard(card, det) {
	card.innerHTML = '<button type="button" class="btn-icon btn-del-detail">&times;</button>'
		+ '<strong class="d-title">' + escHtml(det.title) + '</strong>'
		+ (det.address ? '<br><span class="d-address">' + escHtml(det.address) + '</span>' : '')
		+ (det.related_person ? '<br><span class="d-contact">' + escHtml(__('ld_ph_contact') + ': ' + det.related_person) + '</span>' : '')
		+ (det.description ? '<div class="d-descr">' + escHtml(det.description) + '</div>' : '');
}

function renderDetailCards(details) {
	if (_detailObs) { _detailObs.disconnect(); _detailObs = null; }
	_detailObs = lazyRender('details-list-container', details, 'title',
		d => d.title,
		d => '<div class="card loc_det" data-title="' + escHtml(d.title) + '" data-lazy="1"></div>',
		_fillDetailCard,
		__('ld_db_empty')
	);
}


// ─── CONFIG KEY-VALUE STORE

const CFG_EXAMPLES = ['org_name', 'org_address', 'org_phone', 'org_email', 'org_person'];

/** Render config rows with inline edit + delete. */
function renderConfig(rows) {
	const container = $('config-rows');
	if (!container) return;
	container.innerHTML = '';
	const tpl = $('config-row-template');

	rows.forEach(r => {
		const clone = tpl.content.cloneNode(true);
		const row = clone.querySelector('.conf_row');
		row.dataset.cfgKey = r.key;
		clone.querySelector('.c-key').textContent = r.key;
		const input = clone.querySelector('.c-val');
		input.value = r.val;
		input.onchange = function() { saveConfig(r.key, this.value); };
		clone.querySelector('.btn-del-cfg').onclick = function() { deleteConfig(r.key); };
		container.appendChild(clone);
	});

	const existing = rows.map(r => r.key);
	const missing = CFG_EXAMPLES.filter(k => !existing.includes(k));
	if (missing.length) container.insertAdjacentHTML('beforeend',
		'<div class="conf_hint">💡 ' + missing.join(', ') + '</div>');
}

/** Save a config key-value pair. Called from + button or inline edit.
 * Refreshes via standalone ?api=config (lightweight) — not the bundled details endpoint. */
async function saveConfig(key, val) {
	if (!key) {
		const kEl = $('cfg-new-key');
		const vEl = $('cfg-new-val');
		key = kEl.value.trim(); val = vEl.value.trim();
		if (!key) return;
	}
	const res = await apiCall('config', { key, val });
	if (res && res.msg === 'ok') {
		const kEl = $('cfg-new-key');
		const vEl = $('cfg-new-val');
		if (kEl) kEl.value = '';
		if (vEl) vEl.value = '';
		const cfgData = await apiGet('config');
		if (cfgData) renderConfig(cfgData);
	}
}

/** Delete a config key. Refreshes via standalone ?api=config. */
async function deleteConfig(key) {
	if (!confirm(__('g_del_confirm') + '\n\n' + key)) return;
	const res = await apiCall('config/delete', { key });
	if (res && res.msg === 'ok') {
		const cfgData = await apiGet('config');
		if (cfgData) renderConfig(cfgData);
	}
}


// ─── OBJECT / LOCATION MANAGEMENT VIEW

/** Bind events once — detail card delegation, then fetch+render. */
async function initObjLocMgmt() {
// Delegated click handler for detail cards (bound once, survives re-renders)
	$('details-list-container')?.addEventListener('click', function(e) {
		const card = e.target.closest('[data-title]');
		if (!card) return;
		if (e.target.closest('.btn-del-detail')) {
			deleteDetails(e, card.dataset.title);
		} else {
			populateDetails(card.dataset.title);
			$('details-form')?.scrollIntoView({behavior: 'smooth'});
					
		}
	});

	await refreshObjLocMgmt();
}


/** Shared: populate details cache + datalists from API response. */
function _applyDetailsData(data) {
	detailsCache = data.details || [];
	fillDatalist('detail-titles', detailsCache.map(d => d.title));
	fillDatalist('known-addresses', data.known_addresses || []);
	fillDatalist('known-contacts', data.known_contacts || []);
}

/** Fetch details + render cards. No user/DB/archive management. */
async function refreshObjLocMgmt() {
	const data = await apiGet('details');
	if (!data) return;
	_applyDetailsData(data);
	renderDetailCards(detailsCache);
}


// ─── PRINT VIEW

/** Fetch print data and render work sheets, then trigger browser print dialog. */
async function initPrintView() {
	const taskId = new URLSearchParams(location.search).get('task_id') || '';
	const params = taskId ? '&task_id=' + taskId : '';
	const data = await apiGet('tasks/print', params);
	if (!data) return;

	const container = $('print-container');
	if (!container) return;

	const pageTpl = $('print-page-template');
	const taskTpl = $('print-task-template');

	if (!data.print_data || !Object.keys(data.print_data).length) {
		const page = pageTpl.content.cloneNode(true);
		page.querySelector('.p-heading').textContent = __('no_tasks_today');
		container.appendChild(page);
		return;
	}

	for (const [wn, tasks] of Object.entries(data.print_data)) {
		const page = pageTpl.content.cloneNode(true);
		const dt = tasks[0]?.task_date || data.today;
		page.querySelector('.p-heading').textContent = wn + ' | ' + dt;
		const pageDiv = page.querySelector('.worker-page');

// Org header from config
const orgHeader = pageDiv.querySelector('.print-org-header');
		if (orgHeader && data.org && Object.keys(data.org).length) {
const orgKeys = ['org_name','org_address','org_phone','org_email','org_person'];
const parts = orgKeys
	.filter(k => data.org[k])
	.map(k => k === 'org_name' ? '<strong>'+escHtml(data.org[k])+'</strong>' : escHtml(data.org[k]));
			orgHeader.innerHTML = parts.join(' · ');
		}

		const workerContact = tasks[0]?.user_contact || '';

		tasks.forEach(tk => {
			const box = taskTpl.content.cloneNode(true);
			box.querySelector('.task-time').textContent = tk.start_time + ' - ' + tk.end_time;
			box.querySelector('.task-title').textContent = tk.title;

			const meta = box.querySelector('.task-meta');
			if (tk.address) meta.textContent = tk.address;
			else meta.remove();

			const descr = box.querySelector('.task-descr');
			if (tk.description || tk.notes) descr.textContent = [tk.description, tk.notes].filter(Boolean).join('\n');
			else descr.remove();

			box.querySelector('.sig-worker').textContent = __('g_worker') + ': ' + wn;
			const sigWC = box.querySelector('.sig-worker-contact');
			if (sigWC) sigWC.textContent = workerContact || '';
			box.querySelector('.sig-contact').textContent = __('ld_ph_contact') + ': ' + (tk.related_person || '');

			pageDiv.appendChild(box);
		});
		container.appendChild(page);
	}

	document.body.classList.add('print-view-active');
	window.print();
}


// ─── SHARED: Task Save (worker form + admin form) ───

/** Scroll to a row and flash-highlight it. */
function flashRow(row, block = 'center') {
	if (!row) return;
	row.scrollIntoView({ behavior: 'smooth', block });
	row.classList.add('highlight-flash');
	setTimeout(() => row.classList.remove('highlight-flash'), 1500);
}
/** falls back to nearest future date. Eagerly fills lazy shells before scrolling. */
function scrollToToday(containerId) {
	const container = $(containerId);
	if (!container) return;
	const today = new Date().toISOString().slice(0, 10);
	const todayYM = today.slice(0, 7);
	const displayedYM = CURRENT_YM || todayYM;

	// Wrong month → navigate to today's month, hash triggers scroll after reload
	if (displayedYM !== todayYM) {
		const uid_param = new URLSearchParams(location.search).get('user_id') || '';
		const view = CURRENT_VIEW || 'rules';
		location.href = '?view=' + view + '&ym=' + todayYM + (uid_param ? '&user_id=' + uid_param : '') + '#today';
		return;
	}

	let target = container.querySelector('[data-date="' + today + '"]');
	if (!target) {
		for (const el of container.querySelectorAll('[data-date]')) {
			if (el.dataset.date >= today) { target = el; break; }
		}
	}
	if (!target) return;

	// Eager fill: if target is still lazy, populate rows before scroll
	if (target.dataset.lazy) {
		const src = [teamData, rulesData].find(s => s?.grouped?.[target.dataset.date]);
		if (src) {
			const tbl = target.querySelector('.card_body--p10');
			if (tbl) tbl.innerHTML = _buildRows(src.grouped[target.dataset.date]);
			target.removeAttribute('data-lazy');
			if (_lazyObs) _lazyObs.unobserve(target);
		}
	}

	flashRow(target, 'start');
}




/**
 * Handle task save from either worker-task-form or manager-task-form.
 * Manager form: new task → tasks/batch (multi-worker), edit → tasks/save (single).
 * Worker form: always tasks/save.
 *
 * Saves a task from either the Manager or Worker form.
 * - Edits: Surgically updates the DOM and scrolls to it (Zero-flicker).
 * - Inserts: Refreshes the view, finds the new title, and scrolls to it.
 */
async function saveTaskUI(e) {
	e.preventDefault();
	
	const form = e.target;
	const btnSubmit = form.querySelector('button[type="submit"]');
	const origBtnText = btnSubmit.textContent;
	
// UI Feedback (prevent double-clicks)
	btnSubmit.disabled = true;
	btnSubmit.textContent = __('g_btn_wait');

	try {
		const formData = new FormData(form);
		const data = Object.fromEntries(formData.entries());

// Handle multiple worker selection (Batch assignment in Team View)
		const workerSelect = form.querySelector('#add-workers-select');
		let isBatch = false;
		if (workerSelect && workerSelect.multiple) {
			const selected = Array.from(workerSelect.selectedOptions).map(o => o.value).filter(v => v);
			if (selected.length > 0) {
				data.worker_ids = selected;
				isBatch = selected.length > 1;
			}
		}

		const endpoint = isBatch ? 'tasks/batch' : 'tasks/save';
		const res = await apiCall(endpoint, data);
		if (!res) { btnSubmit.textContent = origBtnText; btnSubmit.disabled = false; return; }

// Clear form early so it feels fast
		form.reset();
		form.querySelector('[name=id]').value = '';

// SURGICAL DOM UPDATE (Edit Mode)
		if (data.id && !isBatch) {
			const row = document.querySelector(`.team-row[data-id="${data.id}"]`);
			if (row) {
// Update primary row
				const titleEl = row.querySelector('.t-title');
				const timeEl = row.querySelector('.t-time');
				if (titleEl) titleEl.textContent = data.title;
				if (timeEl) timeEl.textContent = `${data.start_time || ''} - ${data.end_time || ''}`;

// Update Status text + CSS class
				const statusEl = row.querySelector('.t-status');
				if (statusEl) {
					statusEl.classList.remove('status-0', 'status-1', 'status-2');
					statusEl.classList.add('status-' + data.status);
					statusEl.textContent = __('status_' + data.status);
				}

// Update Notes
				const notesEl = row.querySelector('.t-notes') || (row.nextElementSibling && row.nextElementSibling.querySelector('.t-notes'));
				if (notesEl) notesEl.textContent = data.notes || '';
				flashRow(row);

// UPDATE UNDERLYING JAVASCRIPT STATE
// Mutating cached data guarantees the form is filled with fresh data
		[teamData, rulesData].forEach(src => {
			if (src?.grouped) for (const key in src.grouped) {
				const task = src.grouped[key].find(t => t.id == data.id);
				if (task) {
					Object.assign(task, data);
					task.status_text = __('status_' + data.status);
				}
			}
		});	}
	}


// FULL REFRESH (New Tasks or Batch Mode)
		else {
	if (typeof CURRENT_VIEW !== 'undefined') {
	if (CURRENT_VIEW === 'team' && typeof initTeamView === 'function') await initTeamView();
	else if (CURRENT_VIEW === 'rules' && typeof initRulesView === 'function') await initRulesView();
			}

// Give the DOM a moment to render, then find and scroll to the new item
		setTimeout(() => {
			for (let el of document.querySelectorAll('.t-title')) {
				if (el.textContent === data.title) {
					flashRow(el.closest('.team-row'));
					break;
				}
			}
		}, 150); }

// Success Feedback on Button
		btnSubmit.textContent = __('g_btn_done');
		setTimeout(() => {
			btnSubmit.textContent = origBtnText;
			btnSubmit.disabled = false;
		}, 1000);

	} catch (err) {
		console.error("Save Error:", err);
		alert(err.message || 
		__('g_err_conn'));
		btnSubmit.textContent = origBtnText;
		btnSubmit.disabled = false;
	}
}


// ─── SHARED: Team Task Renderer

/** ISO 8601 week number from 'YYYY-MM-DD' string. */
function isoWeek(dateStr) {
	const d = new Date(dateStr + 'T12:00:00');
	d.setDate(d.getDate() + 3 - (d.getDay() + 6) % 7);
	const jan4 = new Date(d.getFullYear(), 0, 4);
	return 1 + Math.round(((d - jan4) / 86400000 - 3 + (jan4.getDay() + 6) % 7) / 7);
}


/**
 * Render grouped task data into a container using team templates.
 * Used by both Rules view (month tasks) and Team view (today/month).
 * Inserts ISO week separators in month mode.
 *
 * LAZY LOADING (month mode only):
 * Phase 1 — render all group shells (header + empty table) so [data-date]
 *		   elements exist immediately. scrollToToday() always works.
 * Phase 2 — IntersectionObserver fills rows when a group scrolls near viewport.
 * Today scope renders eagerly (small dataset).
 */
// Task lookup map for delegated click handlers
const _taskMap = {};
let _lazyObs = null;

/** Build row HTML for a task array and register in _taskMap. */
function _buildRows(tasks) {
	const rows = [];
	for (let i = 0; i < tasks.length; i++) {
		const t = tasks[i];
		_taskMap[t.id] = t;
		rows.push(_tplRow({
			id:		  t.id,
			user_cls:	t.username ? '' : 'hidden',
			username:	escHtml(t.username || ''),
			title:	   escHtml(t.title),
			coworkers:   (t.coworkers && t.coworkers.length) ? escHtml(t.coworkers.join(', ')) : '',
			time:		escHtml(t.start_time + '–' + t.end_time),
			status:	  t.status,
			status_text: escHtml(t.status_text),
			del_cls:	 'btn-sm btn-red btn-del' + (t.status == 2 ? ' btn-del-done' : ''),
			del_dis:	 t.status == 2 ? 'disabled' : '',
			notes:	   escHtml(t.notes || ''),
		}));
	}
	return rows.join('');
}

function renderTeamTasks(groupedData, isMonth, containerId) {
	const container = $(containerId);
	if (!container) return;

// Tear down previous observer
	if (_lazyObs) { _lazyObs.disconnect(); _lazyObs = null; }

	const parts = [];
	let prevWeek = 0;
	const lazyKeys = []; // date keys needing lazy fill

	for (const [key, tasks] of Object.entries(groupedData)) {
		if (isMonth) {
			const wk = isoWeek(key);
			if (wk !== prevWeek) {
				prevWeek = wk;
				parts.push('<div class="week-header">' + escHtml(__('g_week') + ' ' + wk) + '</div>');
			}
		}

		if (isMonth) {
// Phase 1: shell only — empty table body, data-date present
			lazyKeys.push(key);
			parts.push(_tplGroup({
				data_date: 'data-date="' + escHtml(key) + '" data-lazy="1"',
				header:	escHtml(key),
				rows:	'',
			}));
		} else {
// Today scope: eager render (few tasks)
			parts.push(_tplGroup({
				data_date: '',
				header:	escHtml(__('start') + ' ' + key),
				rows:	  _buildRows(tasks),
			}));
		}
	}

	container.innerHTML = parts.join('');

// observe lazy shells
	if (isMonth && lazyKeys.length) {
		_lazyObs = new IntersectionObserver(entries => {
			for (const entry of entries) {
				if (!entry.isIntersecting) continue;
				const el = entry.target;
				const dateKey = el.dataset.date;
				if (!el.dataset.lazy || !groupedData[dateKey]) continue;
				const tbl = el.querySelector('.card_body--p10');
				if (tbl) tbl.innerHTML = _buildRows(groupedData[dateKey]);
				el.removeAttribute('data-lazy');
				_lazyObs.unobserve(el);
			}
		}, { rootMargin: '200px' }); // pre-fill 200px before visible

		container.querySelectorAll('[data-lazy]').forEach(el => _lazyObs.observe(el));
	}

// Single delegated listener per container
	container.onclick = function(e) {
		if (e.target.closest('.print-link')) return;

		const delBtn = e.target.closest('.btn-del');
		if (delBtn && !delBtn.disabled) {
			const row = delBtn.closest('.team-row');
			if (!row) return;
			if (!confirm(__('g_del_confirm'))) return;
			(async () => {
				const res = await apiCall('tasks/delete', {id: row.dataset.id}, delBtn, __('g_btn_wait'), 'err');
				if (res && res.msg === 'ok') row.remove();
			})();
			return;
		}

		const row = e.target.closest('.team-row');
		if (!row || !row.dataset.id) return;
		const t = _taskMap[row.dataset.id];
		if (!t) return;
		const f = $('worker-task-form') || $('manager-task-form');
		if (!f) return;
		f.querySelector('[name=id]').value = t.id;
		f.title.value = t.title;
		f.task_date.value = t.task_date;
		f.start_time.value = t.start_time;
		f.end_time.value = t.end_time;
		if (f.status) { f.status.value = t.status;
		if (f.status.tagName === 'SELECT') syncStatusColor(f.status); }
		if (f.notes) f.notes.value = t.notes || '';
		if (f.user_id && t.user_id) f.user_id.value = t.user_id;
		if (f.worker_ids && t.user_id) Array.from(f.worker_ids.options).forEach(opt => opt.selected = (opt.value == t.user_id));
		$('task-form-top')?.scrollIntoView({behavior: 'smooth'});
	};
}


// ─── SHARED: Location Details

/** Fill the details form with cached data for the given title. */
function populateDetails(title) {
	if (!detailsCache || !detailsCache.length) return;
	const f = $('details-form');
	const match = detailsCache.find(d => d.title === title);
	f.title.value = match?.title || '';
	f.address.value = match?.address || '';
	f.related_person.value = match?.related_person || '';
	f.description.value = match?.description || '';
}

/** Upsert a location/object detail record. */
async function saveDetails(e) {
	e.preventDefault();
	const f = e.target, btn = f.querySelector('button');
	const origText = btn.textContent;
	const res = await apiCall('details', {
		title: f.title.value, address: f.address.value,
		description: f.description.value, related_person: f.related_person.value
	}, btn, __('g_btn_wait'), __('g_btn_retry'));
	if (res && res.msg === 'ok') {
		f.reset();
		await (CURRENT_SCOPE === 'wobjects' ? refreshObjLocMgmt() : refreshTeamMgmt());
		btnCooldown(btn, origText, 2000);
	}
}

/** Confirm-delete a location detail by title. */
async function deleteDetails(e, title) {
	e.stopPropagation();
	if (!confirm(__('g_del_confirm') + "\n\n" + title)) return;
	const res = await apiCall('details/delete', {title}, null, '', '');
	if (res && res.msg === 'ok') await (CURRENT_SCOPE === 'wobjects' ? refreshObjLocMgmt() : refreshTeamMgmt());
}


// ─── VISUAL RULES EDITOR
// Two-way sync between the Visual Editor UI and the raw textarea.
// The backend always receives raw text — the visual layer is purely frontend.

let isSyncingRules = false;

/** Parse raw textarea → populate visual rule rows (template clones). */
function syncTextToVisual() {
	if (isSyncingRules) return;
	isSyncingRules = true;

	const ta = $('rules-textarea');
	const container = $('visual-rules-container');
	const tpl = $('visual-rule-template');
	if (!ta || !container || !tpl) { isSyncingRules = false; return; }

	container.innerHTML = '';
	let rules = [];
	try { rules = JSON.parse(ta.value || '[]'); } catch(e) { /* invalid JSON, skip */ }
	if (!Array.isArray(rules)) rules = [];

	rules.forEach(rule => {
		if (!rule.title) return;

		const clone = tpl.content.cloneNode(true);
		const row = clone.querySelector('.visual-rule-row');

		row.querySelector('.vr-title').value = rule.title;

// Convert Estonian day letters (ETKNRLP) to numbers (1234567) if present
		let daysStr = (rule.days || '').toUpperCase()
			.replace(/E/g, '1').replace(/T/g, '2').replace(/K/g, '3')
			.replace(/N/g, '4').replace(/R/g, '5').replace(/L/g, '6').replace(/P/g, '7');

		row.querySelectorAll('.vr-days input').forEach(cb => {
			cb.checked = daysStr.includes(cb.value);
		});

		row.querySelectorAll('.vr-weeks input').forEach(cb => {
			cb.checked = (rule.weeks || '').includes(cb.value);
		});

		row.querySelector('.vr-start').value = rule.start || '';
		row.querySelector('.vr-end').value = rule.end || '';

		container.appendChild(clone);
	});

	isSyncingRules = false;
	updateWeekHints();
}


/** Update week checkbox labels with date ranges from API full_weeks data. */
function updateWeekHints() {
	const fw = rulesData && rulesData.full_weeks;
	document.querySelectorAll('.vr-weeks').forEach(div => {
		div.querySelectorAll('label').forEach(lbl => {
			const cb = lbl.querySelector('input');
			if (!cb) return;
			const w = cb.value;
			const info = fw && fw[w];
			const txt = info ? w + ' (' + info.from + '\u2013' + info.to + ')' : w;
			const textNode = lbl.childNodes[lbl.childNodes.length - 1];
			if (textNode.nodeType === 3) textNode.textContent = txt;
		});
	});
}


/** Read visual rule rows → write back JSON to textarea. */
function syncVisualToText() {
	let jsonArray = [];

	document.querySelectorAll('.visual-rule-row').forEach(row => {
		let title = row.querySelector('.vr-title').value.trim();
		if (!title) return;

		let days = Array.from(row.querySelectorAll('.vr-days input:checked')).map(cb => cb.value).join('');
		let weeks = Array.from(row.querySelectorAll('.vr-weeks input:checked')).map(cb => cb.value).join('');
		let start = row.querySelector('.vr-start').value || '';
		let end = row.querySelector('.vr-end').value || '';

		jsonArray.push({ title, days, weeks, start, end });
	});

// Update JSON textarea
	$('rules-textarea').value = JSON.stringify(jsonArray, null, 2);
}


/** Add a new blank rule row to the visual editor. */
function addVisualRule() {
	const ta = $('rules-textarea');
	if (ta) {
		let rules = [];
		try { rules = JSON.parse(ta.value || '[]'); } catch(e) { rules = []; }
		if (!Array.isArray(rules)) rules = [];
		rules.push({ title: __('New_Task'), days: '1', weeks: '1234', start: '08:00', end: '16:00' });
		ta.value = JSON.stringify(rules, null, 2);
		syncTextToVisual();

// Wait for DOM update, then scroll to the new row
		setTimeout(() => {
			const bottomAnchor = $('visual_rules_bottom');
			if (bottomAnchor) {
				bottomAnchor.scrollIntoView({ behavior: 'smooth', block: 'end' });
			}
		}, 50);
	}
}



// ─── CSV EXPORT

/** Download current team view data as semicolon-separated CSV (UTF-8 BOM for Excel). */
function downloadTeamCSV(filename) {
	if (!teamData || !teamData.grouped) return;
	let csv = "\uFEFF";
	for (const [, tasks] of Object.entries(teamData.grouped)) {
		tasks.forEach(t => {
			csv += [t.username, t.title, t.task_date, t.start_time, t.end_time, t.status]
				.map(v => '"' + (v || '').toString().replace(/"/g, '""') + '"')
				.join(";") + "\r\n";
		});
	}
	const url = URL.createObjectURL(new Blob([csv], {type: 'text/csv;charset=utf-8;'}));
	const link = Object.assign(document.createElement('a'), {href: url, download: filename});
	document.body.appendChild(link); link.click(); document.body.removeChild(link);
}



// ─── AUTO-INIT
// Fetch i18n first (pre-auth endpoint), then dispatch based on current view.

document.addEventListener('DOMContentLoaded', async () => {
	if ($('login-form')) return; // Login page — no view init needed

	switch (CURRENT_VIEW) {
		case 'today':	 await initTodayView(); break;
		case 'rules':	 await initRulesView(); break;
		case 'user_info': break; // Static form, no data fetch needed
		case 'print':	 await initPrintView(); break;
		case 'team':
			if (CURRENT_SCOPE === 'baastegijad') await initTeamMgmt();
			else if (CURRENT_SCOPE === 'wobjects') await initObjLocMgmt();
			else await initTeamView();
			break;
	};

if (typeof _t0 !== 'undefined') {
	apiCall('debug_log/jstimer',
	{
		qs: window.location.search,
		jstm:(performance.now() - _t0).toFixed(0),
		fetch: _fetchMs.toFixed(0),
		render: (performance.now() - _t0 - _fetchMs).toFixed(0)
	});
}

});
/* EOF src/app.js */



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



/* included from ./plugins/audit2.js [[[*/
<?php
/* Plugin JS — PHP injects i18n keys into the shared i18n map that __() reads. */
global $_aud_i18n, $langi;
if (isset($_aud_i18n) && is_array($_aud_i18n)) {
	$_flat = [];
	foreach ($_aud_i18n as $k => $v) $_flat[$k] = $v[$langi] ?? ($v[0] ?? $k);
	echo "Object.assign(i18n, " . json_encode($_flat, JSON_UNESCAPED_UNICODE) . ");\n";
}
?>
/**
 * PLUGIN: audit2 — client side.
 * Tabs: runs | templates (admin) | report (admin) | access (admin) | run-detail
 * Draft: localStorage `audit_draft_${run_id}` → { results, ts }.
 * Written on every change, cleared on commit. PHP never sees drafts.
 */

// ─── STATE
let _auditIsAdmin	= false;
let _auditCurrentUid = 0;
let _auditTemplates  = [];

// ─── DRAFT UTILS
const _audLsKey = id => 'audit_draft_' + id;
function _audLoadDraft(id) {
	try { const o = JSON.parse(localStorage.getItem(_audLsKey(id))); return o && Array.isArray(o.results) ? o.results : null; }
	catch { return null; }
}
function _audSaveDraft(id, results) {
	try { localStorage.setItem(_audLsKey(id), JSON.stringify({ results, ts: Date.now() })); } catch {}
}
function _audClearDraft(id) { try { localStorage.removeItem(_audLsKey(id)); } catch {} }

// ─── PANEL SWITCHER
function _audShow(panelId) {
	['audit-panel-runs','audit-panel-templates','audit-panel-report','audit-panel-access','audit-panel-run']
		.forEach(id => { const el = $(id); if (el) el.classList.toggle('hidden', id !== panelId); });
	document.querySelectorAll('#audit-toolbar a').forEach(a =>
		a.classList.toggle('active', a.dataset.audTab === panelId.replace('audit-panel-', '')));
}


// ─── INIT

async function initAuditView() {
	const me = await apiCall('me');
	if (!me) return;
	_auditIsAdmin	= !!me.is_admin;
	_auditCurrentUid = me.uid | 0;

	if (_auditIsAdmin)
		document.querySelectorAll('.aud-admin-only').forEach(el => el.classList.remove('hidden'));

	document.querySelectorAll('#audit-toolbar a').forEach(a => {
		a.addEventListener('click', e => {
			e.preventDefault();
			const tab = a.dataset.audTab;
			if (tab === 'runs')	  { _audShow('audit-panel-runs');	  refreshAuditRuns(); }
			if (tab === 'templates') { _audShow('audit-panel-templates'); refreshAuditTemplates(); }
			if (tab === 'report')	{ _audShow('audit-panel-report');	refreshAuditReport(); }
			if (tab === 'access')	{ _audShow('audit-panel-access');	refreshAuditAccess(); }
		});
	});

	await refreshAuditDue();
	await refreshAuditRuns();
}


// ─── DUE BANNER

async function refreshAuditDue() {
	const due	= await apiCall('audit_run/due');
	const banner = $('audit-due-banner');
	if (!banner) return;
	if (!due || due.error) {
		if (due && due.error === 'forbidden')
			$('audit-shell').innerHTML = '<p>' + escHtml(__('aud_no_access')) + '</p>';
		return;
	}
	if (!due.length) { banner.classList.add('hidden'); banner.innerHTML = ''; return; }
	banner.classList.remove('hidden');
	banner.innerHTML = '<div class="card card-secondary"><div class="card_header">' + escHtml(__('aud_due')) + '</div>' +
		'<div class="card_body">' + due.map(d => {
			const lbl = d.overdue ? __('aud_overdue') : __('aud_due');
			const cls = d.overdue ? 'status-0' : 'status-1';
			return '<div class="aud-due-row"><span class="' + cls + '">' + escHtml(lbl) + ':</span> ' +
				'<b>' + escHtml(d.title) + '</b> ' +
				'<button class="btn-sm btn-blue" onclick="auditStart(' + d.template_id + ')">' + escHtml(__('aud_start_new')) + '</button></div>';
		}).join('') + '</div></div>';
}

async function auditStart(template_id) {
	const res = await apiCall('audit_run/create', { template_id });
	if (res && res.id) await openAuditRun(res.id);
}


// ─── RUNS LIST

async function refreshAuditRuns() {
	const panel = $('audit-panel-runs');
	if (!panel) return;
	panel.innerHTML = '<p>…</p>';
	const data = await apiCall('audit_run');
	const runs = Array.isArray(data) ? data : [];
	if (!runs.length) { panel.innerHTML = '<p>' + escHtml(__('aud_no_runs')) + '</p>'; return; }
	const rows = runs.map(r => {
		const status = r.committed_at
			? (r.has_issues ? '<span class="status-0">⚠ ' + escHtml(__('aud_issues')) + '</span>' : '<span class="status-2">✓</span>')
			: '<span class="status-1">' + escHtml(__('aud_draft')) + '</span>';
		const pct = r.total_count ? Math.round(100 * r.done_count / r.total_count) : 0;
		return '<tr class="team-row" data-aud-run="' + r.id + '">' +
			'<td><b>' + escHtml(r.template_title || '') + '</b><br>' +
			'<small>' + escHtml(r.username || '') + ' · ' + escHtml(r.run_date) + '</small></td>' +
			'<td>' + status + '<br><small>' + r.done_count + '/' + r.total_count + ' (' + pct + '%)</small></td>' +
			'</tr>';
	}).join('');
	panel.innerHTML = '<div class="card"><div class="card_header">' + escHtml(__('aud_runs')) + '</div>' +
		'<table role="presentation" class="card_body card_body--p10">' + rows + '</table></div>';
	panel.querySelectorAll('[data-aud-run]').forEach(tr =>
		tr.addEventListener('click', () => openAuditRun(+tr.dataset.audRun)));
}


// ─── RUN DETAIL

async function openAuditRun(run_id) {
	_audShow('audit-panel-run');
	const panel = $('audit-panel-run');
	panel.innerHTML = '<p>…</p>';
	const res = await apiCall('audit_run', null, null, '', '', '&id=' + run_id);
	if (!res || res.error) { panel.innerHTML = '<p>err</p>'; return; }

	const isCommitted = !!res.committed_at;
	const tpl		 = res.template || {};
	const subtasks	= tpl.subtasks_ordered || [];

	let state;
	if (isCommitted) {
		state = res.results.length ? res.results : subtasks.map(() => ({ done: 0, comment: '' }));
	} else {
		const draft = _audLoadDraft(run_id);
		state = (draft && draft.length === subtasks.length) ? draft : subtasks.map(() => ({ done: 0, comment: '' }));
	}

	const hdr = '<div class="card_header card_header--flex">' +
		'<a href="#" class="btn-sm btn-silver" id="aud-back">' + escHtml(__('aud_back')) + '</a>' +
		'<b>' + escHtml(tpl.title || '') + '</b>' +
		'<span class="t-user"><small>' + escHtml(res.run_date) + '</small></span></div>';

	const rows = subtasks.map((s, idx) => {
		const checked = state[idx] && state[idx].done ? 'checked' : '';
		const comment = state[idx] ? state[idx].comment : '';
		const dis	 = isCommitted ? 'disabled' : '';
		return '<div class="aud-item">' +
			'<label><input type="checkbox" data-aud-idx="' + idx + '" ' + checked + ' ' + dis + '> ' + escHtml(s.name) + '</label>' +
			'<textarea data-aud-cmt="' + idx + '" placeholder="' + escHtml(__('aud_comment')) + '" ' + dis + '>' + escHtml(comment) + '</textarea>' +
			'</div>';
	}).join('');

	let footer;
	if (isCommitted) {
		footer = '<p class="status-2"><i>' + escHtml(__('aud_read_only')) + '</i><br>' +
			escHtml(__('aud_committed_at')) + ': ' + escHtml(res.committed_at || '') + '</p>' +
			'<a class="btn-sm btn-silver no_Print" href="?view=audit_print&run_id=' + run_id + '" target="_blank">' + escHtml(__('aud_print')) + '</a>';
	} else {
		footer = '<p id="aud-draft-msg" class="hint"></p>' +
			'<button class="save_ta btn-green" id="aud-commit-btn">' + escHtml(__('aud_commit')) + '</button>';
	}

	panel.innerHTML = '<form class="card" onsubmit="event.preventDefault()">' + hdr +
		'<div class="card_body">' + rows + footer + '</div></form>';

	$('aud-back').addEventListener('click', e => {
		e.preventDefault();
		_audShow('audit-panel-runs');
		refreshAuditRuns();
	});

	if (!isCommitted) {
		panel.querySelectorAll('[data-aud-idx]').forEach(cb =>
			cb.addEventListener('change', () => _audPersistDraft(run_id, state, panel)));
		panel.querySelectorAll('[data-aud-cmt]').forEach(ta =>
			ta.addEventListener('input', () => _audPersistDraft(run_id, state, panel)));
		$('aud-commit-btn').addEventListener('click', ev => commitAuditRun(ev, run_id, state));
	}
}

function _audPersistDraft(run_id, state, panel) {
	panel.querySelectorAll('[data-aud-idx]').forEach(cb => {
		const i = +cb.dataset.audIdx;
		state[i] = state[i] || { done: 0, comment: '' };
		state[i].done = cb.checked ? 1 : 0;
	});
	panel.querySelectorAll('[data-aud-cmt]').forEach(ta => {
		const i = +ta.dataset.audCmt;
		state[i] = state[i] || { done: 0, comment: '' };
		state[i].comment = ta.value;
	});
	_audSaveDraft(run_id, state);
	const msg = $('aud-draft-msg');
	if (msg) msg.textContent = '✓ ' + __('aud_saved_locally');
}

async function commitAuditRun(ev, run_id, state) {
	const btn = ev.currentTarget;
	const res = await apiCall('audit_commit', { run_id, results: state }, btn, __('g_btn_wait'), __('g_btn_retry'));
	if (!res) return;
	_audClearDraft(run_id);
	btnCooldown(btn, __('g_btn_done'), 1500);
	await refreshAuditRuns();
	await refreshAuditDue();
	await openAuditRun(run_id);
}


// ─── TEMPLATES (admin)

async function refreshAuditTemplates() {
	const panel = $('audit-panel-templates');
	if (!panel) return;
	panel.innerHTML = '<p>…</p>';
	const data = await apiCall('audit_template');
	_auditTemplates = Array.isArray(data) ? data : [];
	panel.innerHTML = _audTemplateFormHtml(null) +
		(_auditTemplates.length
			? _auditTemplates.map(t => _audTemplateCardHtml(t)).join('')
			: '<p>' + escHtml(__('aud_no_templates')) + '</p>');
	_audBindTemplateForms(panel);
}

function _audTemplateFormHtml(t) {
	const id	   = t ? t.id : '';
	const interval = t ? t.interval : 'week_end';
	const active   = t ? t.active : 1;
	const intOpts  = [['task_done', __('aud_int_task_done')], ['week_end', __('aud_int_week_end')], ['month_end', __('aud_int_month_end')]]
		.map(([v, l]) => '<option value="' + v + '"' + (interval === v ? ' selected' : '') + '>' + escHtml(l) + '</option>').join('');
	const subRows  = t ? t.subtasks.map((s, i) => _audSubRowHtml(s, i)).join('') : '';
	const header   = t ? (__('aud_templates') + ': ' + t.title) : __('aud_new_template');
	const delBtn   = t ? '<button type="button" class="btn-sm btn-red btn-compact" data-aud-del="' + id + '">' + escHtml(__('aud_delete')) + '</button>' : '';
	return '<form class="card aud-tpl-form" data-aud-tpl="' + id + '">' +
		'<div class="card_header">' + escHtml(header) + '</div>' +
		'<div class="card_body">' +
		'<input type="hidden" name="id" value="' + id + '">' +
		'<label>' + escHtml(__('aud_title')) + '<br><input type="text" name="title" value="' + (t ? escHtml(t.title) : '') + '" required></label><br>' +
		'<label>' + escHtml(__('aud_target')) + '<br><input type="text" name="target" value="' + (t && t.target ? escHtml(t.target) : '') + '" placeholder="' + escHtml(__('aud_target_any')) + '"></label><br>' +
		'<label>' + escHtml(__('aud_interval')) + '<br><select name="interval">' + intOpts + '</select></label><br>' +
		'<label><input type="checkbox" name="active" value="1"' + (active ? ' checked' : '') + '> ' + escHtml(__('aud_active')) + '</label>' +
		'<div class="aud-sub-hdr"><b>' + escHtml(__('aud_subtasks')) + '</b> ' +
		'<button type="button" class="btn-sm btn-silver btn-compact" data-aud-add-sub>' + escHtml(__('aud_add_subtask')) + '</button></div>' +
		'<div class="aud-subtasks">' + subRows + '</div>' +
		'<button type="submit" class="save_ta btn-green">' + escHtml(__('aud_save')) + '</button> ' + delBtn +
		'</div></form>';
}

function _audSubRowHtml(name, i) {
	return '<div class="aud-sub-row">' +
		'<button type="button" class="btn-sm btn-silver btn-compact" data-aud-sub-up>↑</button>' +
		'<button type="button" class="btn-sm btn-silver btn-compact" data-aud-sub-dn>↓</button>' +
		'<input type="text" name="subtask[]" value="' + escHtml(name || '') + '" required>' +
		'<button type="button" class="btn-sm btn-red btn-compact" data-aud-sub-del>✖</button>' +
		'</div>';
}

function _audTemplateCardHtml(t) {
	const statusCls = t.active ? 'status-2' : 'status-0';
	const statusLbl = t.active ? __('aud_active') : __('aud_disabled');
	return '<details class="card aud-tpl-card">' +
		'<summary class="card_header"><b>' + escHtml(t.title) + '</b> ' +
		'<small>· ' + escHtml(t.interval) + (t.target ? ' · ' + escHtml(t.target) : '') + '</small> ' +
		'<span class="' + statusCls + '">' + escHtml(statusLbl) + '</span></summary>' +
		'<div class="card_body"><ul class="aud-preview-list">' + t.subtasks.map(s => '<li>' + escHtml(s) + '</li>').join('') + '</ul>' +
		'<button type="button" class="btn-sm btn-blue btn-compact" data-aud-edit="' + t.id + '">✎</button></div></details>';
}

function _audBindTemplateForms(panel) {
	panel.querySelectorAll('.aud-tpl-form').forEach(form => {
		form.addEventListener('submit', e => { e.preventDefault(); saveAuditTemplate(form); });
		form.querySelector('[data-aud-add-sub]')?.addEventListener('click', () => {
			const host = form.querySelector('.aud-subtasks');
			const div  = document.createElement('div');
			div.innerHTML = _audSubRowHtml('', host.children.length);
			host.appendChild(div.firstChild);
			_audBindSubRows(form);
		});
		form.querySelector('[data-aud-del]')?.addEventListener('click', function() { disableAuditTemplate(+this.dataset.audDel); });
		_audBindSubRows(form);
	});
	panel.querySelectorAll('[data-aud-edit]').forEach(btn => {
		btn.addEventListener('click', () => {
			const t = _auditTemplates.find(x => x.id === +btn.dataset.audEdit);
			if (!t) return;
			const wrap = document.createElement('div');
			wrap.innerHTML = _audTemplateFormHtml(t);
			btn.closest('details').replaceWith(wrap.firstChild);
			_audBindTemplateForms(panel);
		});
	});
}

function _audBindSubRows(form) {
	form.querySelectorAll('.aud-sub-row').forEach(row => {
		row.querySelector('[data-aud-sub-del]')?.addEventListener('click', () => row.remove());
		row.querySelector('[data-aud-sub-up]')?.addEventListener('click', () => { const p = row.previousElementSibling; if (p) row.parentNode.insertBefore(row, p); });
		row.querySelector('[data-aud-sub-dn]')?.addEventListener('click', () => { const n = row.nextElementSibling;	 if (n) row.parentNode.insertBefore(n, row); });
	});
}

async function saveAuditTemplate(form) {
	const fd	   = new FormData(form);
	const subtasks = [...form.querySelectorAll('input[name="subtask[]"]')].map(i => i.value.trim()).filter(Boolean);
	const payload  = {
		id: fd.get('id') || '', title: fd.get('title') || '', target: fd.get('target') || '',
		interval: fd.get('interval') || 'week_end',
		active: form.querySelector('input[name="active"]').checked ? 1 : 0,
		subtasks,
	};
	const btn = form.querySelector('button[type="submit"]');
	const res = await apiCall('audit_template', payload, btn, __('g_btn_wait'), __('g_btn_retry'));
	if (!res) return;
	btnCooldown(btn, __('g_btn_done'), 1500);
	await refreshAuditTemplates();
}

async function disableAuditTemplate(id) {
	if (!confirm('?')) return;
	const res = await apiCall('audit_template/delete', { id });
	if (res) await refreshAuditTemplates();
}


// ─── REPORT (admin)

async function refreshAuditReport() {
	const panel = $('audit-panel-report');
	if (!panel) return;
	panel.innerHTML = '<p>…</p>';
	const data = await apiCall('audit_report');
	if (!data) { panel.innerHTML = '<p>err</p>'; return; }
	const tplBlocks = (data.by_template || []).map(t =>
		'<div class="card"><div class="card_header"><b>' + escHtml(t.template_title) + '</b> — ' + escHtml(__('aud_subtask_passrate')) + '</div>' +
		'<table role="presentation" class="card_body card_body--p10">' +
		t.items.map(it => '<tr><td>' + escHtml(it.name) + '</td><td><b>' + it.rate + '%</b></td><td><small>' + it.done + '/' + it.total + '</small></td></tr>').join('') +
		'</table></div>'
	).join('');
	const workerRows = (data.by_worker || []).map(w => {
		const rate = w.runs ? Math.round(100 * (w.runs - w.issues) / w.runs) : 0;
		return '<tr><td>' + escHtml(w.username || '') + '</td><td><b>' + rate + '%</b></td><td><small>' + (w.runs - w.issues) + '/' + w.runs + '</small></td></tr>';
	}).join('');
	panel.innerHTML = tplBlocks +
		'<div class="card"><div class="card_header"><b>' + escHtml(__('aud_worker_compl')) + '</b></div>' +
		'<table role="presentation" class="card_body card_body--p10">' + workerRows + '</table></div>';
}


// ─── ACCESS (admin)

async function refreshAuditAccess() {
	const panel = $('audit-panel-access');
	if (!panel) return;
	panel.innerHTML = '<p>…</p>';
	const data = await apiCall('audit_access');
	if (!data) { panel.innerHTML = '<p>err</p>'; return; }
	const rows = data.map(u =>
		'<tr><td>' + escHtml(u.real_name || u.username) + '</td>' +
		'<td><input type="checkbox" data-uid="' + u.id + '"' + (u.has_access ? ' checked' : '') + '></td></tr>'
	).join('');
	panel.innerHTML = '<div class="card"><div class="card_header"><b>' + escHtml(__('aud_access_title')) + '</b></div>' +
		'<table role="presentation" class="card_body card_body--p10">' + rows + '</table></div>';
	panel.querySelectorAll('[data-uid]').forEach(cb =>
		cb.addEventListener('change', () => apiCall('audit_access', { user_id: +cb.dataset.uid, grant: cb.checked ? 1 : 0 })));
}


// ─── TASK DONE HOOK

window.auditOnTaskDone = async function(task_id) {
	try {
		const res = await apiCall('audit_run/check_task', { task_id });
		if (res && res.run_id) refreshAuditDue();
	} catch {}
};


// ─── PRINT VIEW

async function initAuditPrintView() {
	const run_id = new URLSearchParams(location.search).get('run_id') || '';
	if (!run_id) return;
	const data = await apiGet('audit_run/print', '&run_id=' + encodeURIComponent(run_id));
	if (!data || data.error) return;
	const container = $('audit-print-container');
	const pageTpl   = $('print-page-template');
	if (!container || !pageTpl) return;

	const page		= pageTpl.content.cloneNode(true);
	const auditor	 = data.auditor || {};
	const auditorName = auditor.real_name || auditor.username || '';
	const tpl		 = data.template || {};
	const run		 = data.run || {};
	const runDate	 = run.run_date || data.today || '';

	page.querySelector('.p-heading').textContent = (tpl.title || '') + ' | ' + runDate;
	const pageDiv = page.querySelector('.worker-page');

	const orgHeader = pageDiv.querySelector('.print-org-header');
	if (orgHeader && data.org && Object.keys(data.org).length) {
		const parts = ['org_name','org_address','org_phone','org_email','org_person']
			.filter(k => data.org[k])
			.map(k => k === 'org_name' ? '<strong>' + escHtml(data.org[k]) + '</strong>' : escHtml(data.org[k]));
		orgHeader.innerHTML = parts.join(' · ');
	}

	const total  = Array.isArray(run.results) ? run.results.length : 0;
	let   passed = 0;
	if (Array.isArray(run.results)) run.results.forEach(r => { if (r && r.done) passed++; });

	const meta = document.createElement('div');
	meta.className = 'aud-print-meta';
	meta.innerHTML =
		'<div><b>' + escHtml(__('aud_print_auditor')) + ':</b> ' + escHtml(auditorName) +
			(auditor.contact ? ' <small>' + escHtml(auditor.contact) + '</small>' : '') + '</div>' +
		'<div><b>' + escHtml(__('aud_print_date')) + ':</b> ' + escHtml(runDate) + '</div>' +
		'<div><b>' + escHtml(__('aud_print_committed')) + ':</b> ' + escHtml(run.committed_at || '') + '</div>' +
		'<div><b>' + escHtml(__('aud_print_summary')) + ':</b> ' + escHtml(passed + '/' + total) + '</div>';
	pageDiv.appendChild(meta);

	const subs	= (tpl.subtasks_ordered || []).map(s => s.name);
	const results = Array.isArray(run.results) ? run.results : [];
	const table   = document.createElement('table');
	table.className = 'aud-print-list';
	table.setAttribute('role', 'presentation');
	table.innerHTML = subs.map((name, i) => {
		const r   = results[i] || { done: 0, comment: '' };
		const cls = r.done ? 'status-2' : 'status-0';
		return '<tr class="aud-print-item">' +
			'<td class="aud-print-check ' + cls + '">' + escHtml(r.done ? __('aud_print_pass') : __('aud_print_fail')) + '</td>' +
			'<td class="aud-print-name">' + escHtml(name) + '</td>' +
			'<td class="aud-print-comment">' + (r.comment ? escHtml(r.comment) : '') + '</td></tr>';
	}).join('');
	pageDiv.appendChild(table);

	const sig = document.createElement('div');
	sig.className = 'signature-row';
	sig.innerHTML =
		'<div class="signature-col">' +
		'<span class="sig-worker">' + escHtml(__('aud_print_auditor')) + ': ' + escHtml(auditorName) + '</span><br>' +
		'<span class="sig-worker-contact">' + escHtml(auditor.contact || '') + '</span>' +
		'<div class="signature-line">' + escHtml(__('print_signature_line')) + '</div></div>' +
		'<div class="signature-col"><span class="sig-contact"></span>' +
		'<div class="signature-line">' + escHtml(__('print_signature_line')) + '</div></div>';
	pageDiv.appendChild(sig);

	container.appendChild(page);
	document.body.classList.add('print-view-active');
	window.print();
}


// ─── DISPATCH
document.addEventListener('DOMContentLoaded', () => {
	if (typeof CURRENT_VIEW === 'undefined') return;
	if (CURRENT_VIEW === 'audit')			initAuditView();
	else if (CURRENT_VIEW === 'audit_print') initAuditPrintView();
});

/* EOF ./plugins/audit2.js */

