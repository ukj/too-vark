<?php
/**
 * Demo Database Seeder — 3-year stress-test edition.
 *
 * Generates ~50 000 tasks across a full 3-year window for load testing
 * on phone, laptop, remote VPS.
 *
 * Two-pass generation:
 *	Pass A — Rule engine (same logic as api_rules_generate, full weeks only)
 *	Pass B — Daily fill: every worker gets 1-3 extra tasks from the title
 *			pool on every calendar day, ensuring dense data even on
 *			partial-week days the rule engine skips.
 *
 * Expected: ~50 000 tasks, 6-10 MB database, 2-5s to generate.
 *
 * Usage:
 *	php tests/demo-db.php			# 3 years
 *	YEARS=5 php tests/demo-db.php	# 5 years (~85k tasks)
 */
declare(strict_types=1);
setlocale(LC_ALL, 'et_EE.UTF-8');
date_default_timezone_set('Europe/Tallinn');
if (php_sapi_name() !== 'cli') header('Content-Type: text/plain; charset=utf-8');

$t0 = microtime(true);

define('DATA_DIR', __DIR__ . '/..');
define('DB_FILE',  DATA_DIR . '/app-demo.sqlite');
define('ORG_NAME', 'Koristusfirma OÜ');
define('USER1',	'admin');

$YEARS = (int)(getenv('YEARS') ?: 3);

// Clean start
if (file_exists(DB_FILE)) unlink(DB_FILE);
foreach ([DB_FILE . '-wal', DB_FILE . '-shm'] as $_f) if (file_exists($_f)) unlink($_f);

include_once __DIR__ . '/../src/helpers.php';
include_once __DIR__ . '/../src/database.php';

echo "Seeding {$YEARS}-year demo database\n\n";


// ── 1. CONFIG ──
$org_config = [
	'org_name'	=> 'Koristusfirma OÜ',
	'org_address' => 'Pärnu mnt 15, 10141 Tallinn',
	'org_phone'	=> '+372 600 1234',
	'org_email'	=> 'info@koristusfirma.ee',
	'org_person'  => 'Margit Tamm (juhataja)',
];
$cfg_ins = $pdo->prepare("INSERT INTO config (key, val) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET val = excluded.val");
foreach ($org_config as $k => $v) $cfg_ins->execute([$k, $v]);


// ── 2. USERS ── admin seeded by database.php; 15 workers below
$worker_names = array('Tiina Mägi','Raivo Kask','Liis Tamm','Andres Sepp','Moonika Lepp','Taavi Pärn','Külli Saar','Marko Rand','Ene Valk','Peeter Kukk','Demo User','Kersti Mets','Jüri Laas','Helen Rebane','Meelis Ots','Riina Mägiste','Aivo Suurkask','Mai-Liis Tammik','Andrus Vask','Lille Sepp','Arvi Juurikas','Kulli Rahn','Markko Ränd','Eneli Salk','Peeter Pere','Kasutaja Proovib','Kerli Tumemets','Gregor Aas','Helin Ilves','Mirko Ott','Liina Mäger','Raavo Kaasik','Liisa Tammeleht','Andreus Tehnik','Vilja Moon','Tarvi Pähn','Kulla Seesaar','Margo Paal','Enni Valge','Mark Suur','Ilmar Ranna','Kerstin Võsa','Valmar Juur','Valmer Tuul','Hele Rebane','Mehis Otsa',);
$workers=array();
$workers_i=1;
foreach ($worker_names as $wn){
$worker_username = strtolower(
substr_replace(
	str_replace(['-',' '],'',$wn),'',
	strpos(
		str_replace('-','',$wn)
				,' ')+1
	)
	);

$workers[]=array(
	$worker_username,
	$worker_username.'123',
	$wn,
	'+372 511 0'.str_pad(strval($workers_i), 3,'0')
	);
$workers_i++;
}
	
foreach ($workers as [$u, $p, $rn, $c]) insert_user($pdo, $u, $p, $rn, $c);
$uid_map = $pdo->query("SELECT username, id FROM users")->fetchAll(PDO::FETCH_KEY_PAIR);


// ── 3. TASK DETAILS ──
$details = [
	['Kontoripuhastus',		   'Liivalaia 13, Tallinn',		   'Bürooruumide igapäevane koristus',			   'Toomas Veski',  '18:00', '22:00'],
	['Trepikojakoristus',	   'Endla 7, Tallinn',			   'Ühiskasutatavad ruumid, sh postkastide ala',   'Maie Nurk',    '08:30', '09:30'],
	['Aknapesu',			   'Pärnu mnt 67, Tallinn',		   'Välisfassaadi aknad 3. korrus',				   'Rein Hanson',   '10:00', '13:00'],
	['Põrandahooldus',		   'Viru 2, Tallinn',			   'Marmorpõranda lakkimine ja poleerumine',	   'Lea Joost',	'11:00', '14:00'],
	['Vaibapuhastus',		'Mustamäe tee 4, Tallinn',	   'Tööstustolmuimeja + vaibashampoon',			   'Kaido Lill',    '08:00', '11:00'],
	['Sanitaarruumide hooldus','Narva mnt 5, Tallinn',		   'WC-d ja dušširuumid, desinfektsioon',		   'Siret Oja',     '09:00', '11:00'],
	['Prügivedu',			   'Ülemiste City, Tallinn',	   'Konteinerite tühjendamine ja vahetus',		   'Ants Koit',     '07:00', '09:00'],
	['Lumepuhastus',		   'Kadrioru park, Tallinn',	   'Kõnniteed ja sissesõidutee',				   'Merle Puur',    '06:00', '08:00'],
	['Aiahooldus',			   'Nõmme, Tallinn',			   'Muru niitmine + põõsaste lõikamine',		   'Urmas Hall',    '09:00', '12:00'],
	['Erikoristus',			   'Ülemiste järve kallas, Tallinn','Üritusejärgne koristus, ~200 külalist',	   'Pille Rand',    '14:00', '18:00'],
	['Saali koristus',		   'Saku Suurhall, Tallinn',	   'Üritusala põhjalik koristus',				   'Mart Kuusk',    '12:00', '16:00'],
	['Fassaadipesu',		   'Tornimäe 5, Tallinn',		   'Klaaspinna pesu tõstukiga, 12 korrust',		   'Ivo Sepp',      '08:00', '13:00'],
	['Parklakoristus',		   'Kristiine keskus, Tallinn',	   'Maa-alune parkla, pühkimine + pesu',		   'Anne Põder',    '06:00', '08:00'],
	['Tööstuskoristus',		   'Betooni 12, Tallinn',		   'Tootmishalli koristus, tööstuslahustid',	   'Karl Mänd',     '08:00', '12:00'],
	['Suurpesu',			   'Tartu mnt 83, Tallinn',		   'Täielik sügavpesu, sh paneelid ja ventilatsioon','Liina Kivi',  '08:00', '16:00'],
	['Köögipuhastus',		   'Rotermanni 8, Tallinn',		   'Sööklaköögi rasvapuhastus ja desinfektsioon',  'Malle Kask',   '07:00', '09:00'],
	['Garaaživedu',			   'Lasnamäe 32, Tallinn',		   'Garaaži pühkimine ja prahi kogumine',		   'Raul Tamm',     '07:00', '09:00'],
	['Katusepuhastus',		   'Tehnika 21, Tallinn',		   'Katuse samblaeemaldus ja vihmaveerennid',	   'Jaan Puust',    '09:00', '13:00'],
	['Terrassipesu',		   'Pirita tee 10, Tallinn',	   'Terrassikivide survepesu ja õlitamine',		   'Kati Nurm',     '10:00', '14:00'],
	['Laokoristus',			   'Peterburi tee 46, Tallinn',	   'Laoruumide koristus ja riiulite pühkimine',	   'Olev Mäe',      '13:00', '15:00'],
];
$td_ins = $pdo->prepare("INSERT OR IGNORE INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)");
foreach ($details as $row) $td_ins->execute([$row[0],$row[1], $row[2], $row[3]]);

// Title list for daily fill (Pass B)
$all_titles = array_column($details, 0);
$title_cnt  = count($all_titles);


// ── 4. USER RULES  (auto-generated) ──
// Each worker gets 2–10 rules.
// Each rule fires on 1–3 days/week (work days) with 1–3 free days guaranteed per week.
// start/end times are drawn from $details for the matching title.

// Build a lookup: title -> [start, end]
$detail_times = [];
foreach ($details as $d) $detail_times[$d[0]] = ['start' => $d[4], 'end' => $d[5]];

$all_day_codes  = ['E','T','K','N','R','L']; // Mon-Sat (P=Sun skipped for cleaning firm)
$all_week_codes = ['1','2','3','4'];
$all_titles_arr = array_column($details, 0);
$title_pool_cnt = count($all_titles_arr);

mt_srand(7); // deterministic seed for rule generation

$rules_per_user = [];
foreach ($workers as [$uname]) {
	$rule_count_for_user = mt_rand(2, 10);

	// Decide which weekdays are FREE (1–3 free days per week)
	$free_day_count = mt_rand(1, 3);
	$shuffled_days  = $all_day_codes;
	shuffle($shuffled_days);
	$free_days = array_slice($shuffled_days, 0, $free_day_count);
	$work_days = array_values(array_diff($all_day_codes, $free_days)); // remaining work days

	if (empty($work_days)) $work_days = ['E']; // safety: at least 1 work day

	$user_rules = [];
	$used_titles = [];

	for ($ri = 0; $ri < $rule_count_for_user; $ri++) {
		// Pick a title not yet used by this worker (cycle if exhausted)
		$attempts = 0;
		do {
			$title = $all_titles_arr[mt_rand(0, $title_pool_cnt - 1)];
			$attempts++;
		} while (in_array($title, $used_titles, true) && $attempts < 30);
		$used_titles[] = $title;

		// 1–3 work days for this rule (subset of work_days)
		$days_cnt = mt_rand(1, min(3, count($work_days)));
		$day_pick = $work_days;
		shuffle($day_pick);
		$rule_days = implode('', array_slice($day_pick, 0, $days_cnt));

		// Weeks pattern: '1234' most often, sometimes a subset
		$week_options = ['1234','13','24','23','3','4'];
		$weeks = $week_options[mt_rand(0, count($week_options) - 1)];

		$times = $detail_times[$title];
		$user_rules[] = [
			'title' => $title,
			'days'  => $rule_days,
			'weeks' => $weeks,
			'start' => $times['start'],
			'end'   => $times['end'],
		];
	}

	$rules_per_user[$uname] = $user_rules;
}

mt_srand(42); // restore main seed used by task generation

$rules_ins = $pdo->prepare("INSERT INTO user_rules (user_id, rules_text) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET rules_text = excluded.rules_text");
foreach ($rules_per_user as $uname => $rules) {
	$uid = $uid_map[$uname] ?? null;
	if ($uid === null) continue;
	$rules_ins->execute([(int)$uid, json_encode($rules, JSON_UNESCAPED_UNICODE)]);
}





// ── 5. GENERATE TASKS ──
echo "Generating tasks...\n";

$today	= date('Y-m-d');
$today_ts = strtotime($today);
$start_date = date('Y-m-d', strtotime("-{$YEARS} years", $today_ts));
$end_date	= date('Y-m-d', strtotime('+2 months', $today_ts));

$notes_pool = [
	'Klient hilines 15 min', 'Lisatöö: pesu ka WC-s', 'Uus vahend proovimisel',
	'Võti jäi valvuri kätte', 'Automaatpesu katki', 'Asendas kolleeg (haigus)',
	'Klient palus lisaaega', 'Standardne päev', 'Vaip väga must, lisaaeg',
	'Sulailm, lihtsam töö', 'Uus klient, esimene kord', 'Võti probleemid, hilines',
	'Sügavpesu tehtud', 'Pooleli, jätkub homme', 'Klient jälgis tööd',
	'Lisatöö: akende sisepesu', 'Hea tagasiside', 'Transport hilines',
	'Ilm halb, raske päev', 'Masinaviga, parandatud', 'Kõik korras',
	'Koristusaine otsas', 'Lift katki, trepist', 'Alarm ei töötanud',
];




$notes_cnt = count($notes_pool);

// Time slots for daily-fill tasks (Pass B)
$time_slots = [
	['06:00','08:00'], ['07:00','09:00'], ['07:30','09:30'],
	['08:00','10:00'], ['08:30','10:30'], ['09:00','11:00'],
	['09:00','12:00'], ['10:00','12:00'], ['10:00','13:00'],
	['11:00','14:00'], ['12:00','15:00'], ['13:00','16:00'],
	['14:00','17:00'], ['14:00','18:00'], ['15:00','18:00'],
];

/*
Fatal error</b>:  Uncaught PDOException: SQLSTATE[HY000]: General error: 1 table task_details has no column named start_time in /storage/emulated/0/htdocs/tooajad-S4-rest/tests/demo-db.php:106
Stack trace:
#0 /storage/emulated/0/htdocs/tooajad-S4-rest/tests/demo-db.php(106): PDO-&gt;prepare('INSERT OR IGNOR...')
#1 {main}
  thrown in <b>/storage/emulated/0/htdocs/tooajad-S4-rest/tests/demo-db.php</b> on line <b>106</b><br />
*/


$slots_cnt = count($time_slots);

mt_srand(42);

$ins = $pdo->prepare("INSERT OR IGNORE INTO tasks (user_id, task_date, title, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");

// Pre-resolve rules
$resolved = [];
foreach ($rules_per_user as $uname => $rules) {
	$uid = (int)($uid_map[$uname] ?? 0);
	if (!$uid) continue;
	foreach ($rules as &$r) {
		$r['_uid'] = $uid;
		$r['_days'] = strtr($r['days'], ['E'=>'1','T'=>'2','K'=>'3','N'=>'4','R'=>'5','L'=>'6','P'=>'7']);
	}
	unset($r);
	$resolved[$uname] = $rules;
}

// All worker UIDs (exclude admin)
$worker_uids = [];
foreach ($uid_map as $name => $id) { if ($name !== USER1) $worker_uids[] = (int)$id; }
$wcount = count($worker_uids);

$task_count = 0;
$rule_count = 0;
$fill_count = 0;

$day_cur = new DateTime($start_date);
$day_end = new DateTime($end_date);

// Full-week cache per month
$cache_ym = '';
$iso_to_rel = [];

$pdo->beginTransaction();
$batch = 0;
$last_ym = '';

while ($day_cur <= $day_end) {
	$y = (int)$day_cur->format('Y');
	$m = (int)$day_cur->format('m');
	$d = (int)$day_cur->format('d');
	$ym = $day_cur->format('Y-m');

	// Rebuild full-week map on month change
	if ($ym !== $cache_ym) {
		$cache_ym = $ym;
		$dim = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
		$wdc = [];
		for ($di = 1; $di <= $dim; $di++) {
			$w = (int)date('W', mktime(12, 0, 0, $m, $di, $y));
			$wdc[$w] = ($wdc[$w] ?? 0) + 1;
		}
		$full = array_values(array_keys(array_filter($wdc, fn($c) => $c === 7)));
		$iso_to_rel = [];
		foreach ($full as $i => $iw) $iso_to_rel[$iw] = $i + 1;
	}

	$ts		= mktime(12, 0, 0, $m, $d, $y);
	$date_str = date('Y-m-d', $ts);
	$day_num  = (int)date('N', $ts);
	$iso_week = (int)date('W', $ts);
	$rel_week = $iso_to_rel[$iso_week] ?? 0;
	$is_past  = ($date_str < $today);
	$is_today = ($date_str === $today);
	$dn		= (string)$day_num;

	// ── PASS A: Rule engine (full weeks only, same as app) ──
	if ($rel_week > 0) {
		$rw = (string)$rel_week;
		foreach ($resolved as $rules) {
			foreach ($rules as $r) {
				if (strpos($r['_days'], $dn) === false) continue;
				if (strpos($r['weeks'], $rw) === false) continue;

				if ($is_past) {
					$roll = mt_rand(1, 100);
					$status = $roll <= 92 ? 2 : ($roll <= 97 ? 1 : 0);
				} elseif ($is_today) {
					$roll = mt_rand(1, 100);
					$status = $roll <= 40 ? 0 : ($roll <= 75 ? 1 : 2);
				} else {
					$status = 0;
				}
				$notes = '';
				if (($status === 2 && mt_rand(1,100) <= 8) || ($status === 1 && mt_rand(1,100) <= 15))
					$notes = $notes_pool[mt_rand(0, $notes_cnt - 1)];

				$st = $r['start']; $en = $r['end'];
				if ($status >= 1 && $is_past) {
					$sh = mt_rand(-30, 30) * 60;
					$s_ts = strtotime($r['start']) + $sh;
					$st = date('H:i', $s_ts);
					if ($status === 2)
						$en = date('H:i', $s_ts + (strtotime($r['end']) - strtotime($r['start'])) + mt_rand(-20,20)*60);
				}
				$ins->execute([$r['_uid'], $date_str, $r['title'], $st, $en, $status, $notes]);
				$task_count++; $rule_count++;
			}
		}
	}

	// ── PASS B: Daily fill — every worker gets 1-3 extra tasks ──
	// Workdays: 2-3 tasks per worker.  Weekends: 0-1.
	// This runs on ALL days (including partial-week days the rule engine skips).
	$tasks_per_worker = ($day_num <= 5) ? mt_rand(2, 3) : mt_rand(0, 1);

	foreach ($worker_uids as $wuid) {
		// Pick N random titles for this worker on this day
		$picked = [];
		for ($ti = 0; $ti < $tasks_per_worker; $ti++) {
			$idx = mt_rand(0, $title_cnt - 1);
			if (isset($picked[$idx])) continue; // skip duplicate title same day
			$picked[$idx] = true;
			$title = $all_titles[$idx];
			$slot  = $time_slots[mt_rand(0, $slots_cnt - 1)];

			if ($is_past) {
				$roll = mt_rand(1, 100);
				$status = $roll <= 90 ? 2 : ($roll <= 96 ? 1 : 0);
			} elseif ($is_today) {
				$roll = mt_rand(1, 100);
				$status = $roll <= 45 ? 0 : ($roll <= 80 ? 1 : 2);
			} else {
				$status = 0;
			}

			$notes = '';
			if ($is_past && mt_rand(1, 100) <= 6)
				$notes = $notes_pool[mt_rand(0, $notes_cnt - 1)];

			// INSERT OR IGNORE: if rule engine already created this (user, date, title), skip
			$ins->execute([$wuid, $date_str, $title, $slot[0], $slot[1], $status, $notes]);
			$task_count++; $fill_count++;
		}
	}

	// Commit every ~200 days
	$batch++;
	if ($batch >= 200) {
		$pdo->commit();
		$pdo->beginTransaction();
		$batch = 0;
	}

	// Progress (once per month)
	if ($ym !== $last_ym) {
		$last_ym = $ym;
		echo "  {$ym}  tasks so far: {$task_count}\n";
	}

	$day_cur->modify('+1 day');
}

$pdo->commit();

echo "  done\n";


// ── 6. SUMMARY ──
$pdo->query("PRAGMA wal_checkpoint(TRUNCATE);");
clearstatcache(true, DB_FILE);

$t_total  = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$d_count  = (int)$pdo->query("SELECT COUNT(*) FROM task_details")->fetchColumn();
$u_count  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$c_count  = (int)$pdo->query("SELECT COUNT(*) FROM config")->fetchColumn();
$r_count  = (int)$pdo->query("SELECT COUNT(*) FROM user_rules")->fetchColumn();

$status_dist = $pdo->query("SELECT status, COUNT(*) as cnt FROM tasks GROUP BY status ORDER BY status")->fetchAll();
$year_dist	= $pdo->query("SELECT substr(task_date,1,4) as y, COUNT(*) as cnt FROM tasks GROUP BY y ORDER BY y")->fetchAll();
$month_dist  = $pdo->query("SELECT substr(task_date,1,7) as m, COUNT(*) as cnt FROM tasks GROUP BY m ORDER BY m")->fetchAll();
$db_size	 = filesize(DB_FILE);
$elapsed	 = round(microtime(true) - $t0, 2);

$labels = ['Pending', 'In Progress', 'Done'];

echo "\n--- RESULT ---\n";
echo "  Time		 : {$elapsed}s\n";
echo "  DB size	: " . round($db_size / 1024 / 1024, 2) . " MB\n";
echo "  users		: $u_count  (1 admin + " . ($u_count - 1) . " workers)\n";
echo "  TASKS		: $t_total  (rules: $rule_count  daily-fill: $fill_count  ignored dupes: " . ($task_count - $t_total) . ")\n";
echo "  task_details : $d_count\n";
echo "  config		: $c_count\n";
echo "  user_rules	: $r_count\n";
echo "  date range	: $start_date -> $end_date\n";

echo "\n  Status:\n";
foreach ($status_dist as $s)
	printf("	%d %-12s %6d  (%4.1f%%)\n", $s['status'], $labels[$s['status']] ?? '?', $s['cnt'], $t_total > 0 ? $s['cnt']/$t_total*100 : 0);

echo "\n  Per year:\n";
foreach ($year_dist as $row)
	printf("	%s : %6d\n", $row['y'], $row['cnt']);

echo "\n  Per month (first 4 / last 4):\n";
foreach (array_slice($month_dist, 0, 4) as $row) printf("	%s : %5d\n", $row['m'], $row['cnt']);
if (count($month_dist) > 8) echo "	...\n";
foreach (array_slice($month_dist, -4) as $row) printf("	%s : %5d\n", $row['m'], $row['cnt']);

echo "\n  Logins:  admin / admin	|	user / user123\n";

echo "\n  Users:\n";
foreach ($pdo->query("SELECT id, username, real_name, contact FROM users ORDER BY id") as $r)
	printf("	[%2d] %-10s  %-18s  %s\n", $r['id'], $r['username'], $r['real_name'], $r['contact']);

echo "\n  Rules:\n";
foreach ($pdo->query("SELECT u.username, ur.rules_text FROM user_rules ur JOIN users u ON u.id=ur.user_id ORDER BY u.username") as $r) {
	$rl = json_decode($r['rules_text'], true);
	printf("	%-10s  %d rule(s): %s\n", $r['username'], count($rl), implode(', ', array_column($rl, 'title')));
}
