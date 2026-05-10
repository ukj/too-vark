<?php
declare(strict_types=1);

/**
 * PLUGIN: audit2 — checklists with access control (admin + selected workers).
 *
 * Access model: admin always allowed; workers need a row in audit_access.
 * Handlers follow P11: explicit params, [code, body] return.
 */

// ─── LOCAL I18N
$_aud_i18n = [
	'nav_audit'		 => ['Audits',				 'Auditid'],
	'aud_runs'			=> ['Runs',				 'Auditid'],
	'aud_templates'	 => ['Templates',			'Šabloonid'],
	'aud_report'		=> ['Report',				 'Aruanne'],
	'aud_access'		=> ['Access',				 'Juurdepääs'],
	'aud_no_runs'		 => ['No audits yet.',		 'Auditeid pole veel.'],
	'aud_no_templates'	=> ['No templates yet.',	'Šabloone pole veel.'],
	'aud_draft'		 => ['Draft',				'Mustand'],
	'aud_issues'		=> ['Issues',				 'Probleemid'],
	'aud_due'			 => ['Due',					'Tähtajaks'],
	'aud_overdue'		 => ['Overdue',				'Tähtaeg möödas'],
	'aud_start_new'	 => ['Start audit',			'Alusta auditit'],
	'aud_back'			=> ['‹ Back',				 '‹ Tagasi'],
	'aud_commit'		=> ['Commit audit',		 'Kinnita audit'],
	'aud_read_only'	 => ['Committed — read only.', 'Kinnitatud — kirjutuskaitstud.'],
	'aud_committed_at'	=> ['Committed at',		 'Kinnitatud'],
	'aud_print'		 => ['🖨 Print',			 '🖨 Prindi'],
	'aud_comment'		 => ['Comment',				'Kommentaar'],
	'aud_saved_locally' => ['Saved locally',		'Salvestatud kohapeal'],
	'aud_save'			=> ['Save template',		'Salvesta šabloon'],
	'aud_delete'		=> ['Disable',				'Keela'],
	'aud_new_template'	=> ['New template',		 'Uus šabloon'],
	'aud_title'		 => ['Title',				'Pealkiri'],
	'aud_target'		=> ['Target task',			'Sihtülesanne'],
	'aud_target_any'	=> ['— any —',				'— kõik —'],
	'aud_interval'		=> ['Interval',			 'Intervall'],
	'aud_int_task_done' => ['On task completion',	 'Ülesande lõpetamisel'],
	'aud_int_week_end'	=> ['Weekly',				 'Nädalane'],
	'aud_int_month_end' => ['Monthly',				'Kuine'],
	'aud_subtasks'		=> ['Checklist items',		'Kontrollnimekiri'],
	'aud_add_subtask'	 => ['+ item',				 '+ punkt'],
	'aud_active'		=> ['Active',				 'Aktiivne'],
	'aud_disabled'		=> ['Disabled',			 'Välja lülitatud'],
	'aud_no_access'	 => ['No audit access.',	 'Auditile juurdepääs puudub.'],
	'aud_access_title'	=> ['Audit access',		 'Auditi juurdepääs'],
	'aud_subtask_passrate'=> ['Item pass rate',		 'Punkti edukus'],
	'aud_worker_compl'	=> ['Worker compliance',	'Töötaja vastavus'],
	'aud_print_auditor' => ['Auditor',				'Auditeerija'],
	'aud_print_date'	=> ['Audit date',			 'Auditi kuupäev'],
	'aud_print_committed' => ['Committed',			'Kinnitatud'],
	'aud_print_summary' => ['Summary',				'Kokkuvõte'],
	'aud_print_pass'	=> ['✓ OK',				 '✓ Korras'],
	'aud_print_fail'	=> ['✗ Issue',				'✗ Probleem'],
];

function __aud(string $key): string {
	global $_aud_i18n, $langi;
	$li = $langi ?? 0;
	return $_aud_i18n[$key][$li] ?? ($_aud_i18n[$key][0] ?? $key);
}



// ─── HELPERS

function _aud_intervals(): array { return ['task_done', 'week_end', 'month_end']; }

/** Worker has audit access: admin always yes; others need a row in audit_access. */
function _aud_can(bool $is_admin, int $uid, PDO $pdo): bool {
	if ($is_admin) return true;
	$s = $pdo->prepare("SELECT 1 FROM audit_access WHERE user_id=?");
	$s->execute([$uid]);
	return (bool)$s->fetchColumn();
}

/** Parse subtasks input (array or JSON string) → trimmed string[], or null on bad input. */
function _aud_parse_subtasks(mixed $raw): ?array {
	if (is_string($raw)) {
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) return null;
		$raw = $decoded;
	}
	if (!is_array($raw)) return null;
	$out = [];
	foreach ($raw as $s) {
		if (!is_string($s)) return null;
		$t = trim($s);
		if ($t !== '') $out[] = $t;
	}
	return $out;
}

/** Fetch template + ordered subtasks. Returns null if missing. */
function _aud_template_by_id(PDO $pdo, int $id): ?array {
	$s = $pdo->prepare("SELECT id, title, target, interval, subtasks, active FROM audit_templates WHERE id=?");
	$s->execute([$id]);
	$t = $s->fetch();
	if (!$t) return null;
	$sub = $pdo->prepare("SELECT id, sort_order, name FROM audit_subtasks WHERE template_id=? ORDER BY sort_order, id");
	$sub->execute([$id]);
	$t['subtasks_ordered'] = $sub->fetchAll();
	$t['id']	 = (int)$t['id'];
	$t['active'] = (int)$t['active'];
	return $t;
}

/** Delete + re-insert audit_subtasks rows for a template. */
function _aud_sync_subtasks(PDO $pdo, int $template_id, array $names): void {
	$pdo->prepare("DELETE FROM audit_subtasks WHERE template_id=?")->execute([$template_id]);
	$ins = $pdo->prepare("INSERT INTO audit_subtasks (template_id, sort_order, name) VALUES (?,?,?)");
	foreach ($names as $i => $n) $ins->execute([$template_id, $i, $n]);
}

/** ISO week Monday–Sunday bounds for a Y-m-d date. */
function _aud_week_bounds(string $ymd): array {
	$ts= strtotime($ymd);
	$dow = (int)date('N', $ts);
	$mon = date('Y-m-d', strtotime($ymd . ' -' . ($dow - 1) . ' days'));
	return [$mon, date('Y-m-d', strtotime($mon . ' +6 days'))];
}

/** Calendar-month first/last day for a Y-m-d. */
function _aud_month_bounds(string $ymd): array {
	return [date('Y-m-01', strtotime($ymd)), date('Y-m-t', strtotime($ymd))];
}



// ─── TEMPLATES

function plugin_audit_template(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	return match($method) {
		'GET' => _aud_templates_list($pdo, $is_admin),
		'POST'=> _aud_template_save($pdo, $d, $is_admin),
		default => [405, ['error' => 'method_not_allowed']],
	};
}

function _aud_templates_list(PDO $pdo, bool $is_admin): array {
	$sql = "SELECT id, title, target, interval, subtasks, active FROM audit_templates";
	if (!$is_admin) $sql .= " WHERE active=1";
	$sql .= " ORDER BY title";
	$out = [];
	foreach ($pdo->query($sql)->fetchAll() as $r) {
		$decoded = json_decode($r['subtasks'] ?? '[]', true);
		$out[] = [
			'id'	 => (int)$r['id'],
			'title'	=> $r['title'],
			'target' => $r['target'],
			'interval' => $r['interval'],
			'subtasks' => is_array($decoded) ? $decoded : [],
			'active' => (int)$r['active'],
		];
	}
	return [200, $out];
}

function _aud_template_save(PDO $pdo, array $d, bool $is_admin): array {
	if (!$is_admin) return [403, ['error' => 'forbidden']];
	$title	= trim($d['title'] ?? '');
	$target = trim($d['target'] ?? '');
	$interval = $d['interval'] ?? '';
	$subtasks = _aud_parse_subtasks($d['subtasks'] ?? []);
	if ($title === '')								 return [400, ['error' => 'title required']];
	if (!in_array($interval, _aud_intervals(), true)) return [400, ['error' => 'invalid_interval']];
	if ($subtasks === null)							return [400, ['error' => 'invalid_subtasks']];
	if (!$subtasks)									return [400, ['error' => 'subtasks required']];

	$js	 = json_encode(array_values($subtasks), JSON_UNESCAPED_UNICODE);
	$active = isset($d['active']) ? (int)!!$d['active'] : 1;

	return db_try('audit_template/save', function() use ($pdo, $d, $title, $target, $interval, $subtasks, $js, $active) {
		if (!empty($d['id'])) {
			$id = (int)$d['id'];
			$pdo->prepare("UPDATE audit_templates SET title=?, target=?, interval=?, subtasks=?, active=? WHERE id=?")
				->execute([$title, $target !== '' ? $target : null, $interval, $js, $active, $id]);
		} else {
			$pdo->prepare("INSERT INTO audit_templates (title, target, interval, subtasks, active) VALUES (?,?,?,?,?)")
				->execute([$title, $target !== '' ? $target : null, $interval, $js, $active]);
			$id = (int)$pdo->lastInsertId();
		}
		_aud_sync_subtasks($pdo, $id, $subtasks);
		return [200, ['msg' => 'ok', 'id' => $id]];
	});
}

/** Soft-disable a template (active=0). Admin only. Hard-delete would cascade runs. */
function plugin_audit_template_delete(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	if (!$is_admin) return [403, ['error' => 'forbidden']];
	$id = (int)($d['id'] ?? 0);
	if ($id <= 0) return [400, ['error' => 'id required']];
	return db_try('audit_template/delete', function() use ($pdo, $id) {
		$pdo->prepare("UPDATE audit_templates SET active=0 WHERE id=?")->execute([$id]);
		return [200, ['msg' => 'ok']];
	});
}



// ─── RUNS

function plugin_audit_run(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']];
	if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']];

	if (!empty($_GET['id'])) return _aud_run_get($pdo, (int)$_GET['id'], $uid, $is_admin);

	$where = []; $params = [];
	if (!$is_admin)				 { $where[] = 'r.user_id=?';	 $params[] = $uid; }
	elseif (!empty($_GET['user_id'])) { $where[] = 'r.user_id=?';	 $params[] = (int)$_GET['user_id']; }
	if (!empty($_GET['template_id'])) { $where[] = 'r.template_id=?'; $params[] = (int)$_GET['template_id']; }
	if (!empty($_GET['from']))		{ $where[] = 'r.run_date>=?';	$params[] = $_GET['from']; }
	if (!empty($_GET['to']))		{ $where[] = 'r.run_date<=?';	$params[] = $_GET['to']; }

	$sql = "SELECT r.id, r.template_id, r.run_date, r.user_id, r.results, r.has_issues, r.committed_at,
				t.title AS template_title, u.username
			FROM audit_runs r
			LEFT JOIN audit_templates t ON t.id=r.template_id
			LEFT JOIN users u ON u.id=r.user_id";
	if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
	$sql .= ' ORDER BY r.run_date DESC, r.id DESC LIMIT 200';
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	$out = [];
	foreach ($stmt->fetchAll() as $r) {
		$results = json_decode($r['results'] ?? '[]', true);
		$results = is_array($results) ? $results : [];
		$done	= count(array_filter($results, fn($it) => !empty($it['done'])));
		$out[] = [
			'id'			 => (int)$r['id'],
			'template_id'	=> (int)$r['template_id'],
			'template_title' => $r['template_title'],
			'run_date'	 => $r['run_date'],
			'user_id'		=> (int)$r['user_id'],
			'username'	 => $r['username'],
			'has_issues'	 => (int)$r['has_issues'],
			'committed_at' => $r['committed_at'],
			'done_count'	 => $done,
			'total_count'	=> count($results),
		];
	}
	return [200, $out];
}

function _aud_run_get(PDO $pdo, int $run_id, int $uid, bool $is_admin): array {
	$stmt = $pdo->prepare("SELECT id, template_id, run_date, user_id, results, has_issues, committed_at FROM audit_runs WHERE id=?");
	$stmt->execute([$run_id]);
	$r = $stmt->fetch();
	if (!$r) return [404, ['error' => 'not_found']];
	if (!$is_admin && (int)$r['user_id'] !== $uid) return [403, ['error' => 'forbidden']];
	$results = json_decode($r['results'] ?? '[]', true);
	return [200, [
		'id'		 => (int)$r['id'],
		'template_id'=> (int)$r['template_id'],
		'template'	 => _aud_template_by_id($pdo, (int)$r['template_id']),
		'run_date'	 => $r['run_date'],
		'user_id'	=> (int)$r['user_id'],
		'results'	=> is_array($results) ? $results : [],
		'has_issues' => (int)$r['has_issues'],
		'committed_at' => $r['committed_at'],
	]];
}

function plugin_audit_run_create(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']];
	if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']];
	$tpl_id = (int)($d['template_id'] ?? 0);
	if ($tpl_id <= 0) return [400, ['error' => 'template_id required']];
	$tpl = _aud_template_by_id($pdo, $tpl_id);
	if (!$tpl || !$tpl['active']) return [404, ['error' => 'template_not_found']];
	$target_uid = ($is_admin && !empty($d['user_id'])) ? (int)$d['user_id'] : $uid;
	return db_try('audit_run/create', function() use ($pdo, $tpl_id, $target_uid, $dc) {
		$pdo->prepare("INSERT INTO audit_runs (template_id, run_date, user_id, results, has_issues) VALUES (?,?,?,?,0)")
			->execute([$tpl_id, $dc->today, $target_uid, '[]']);
		return [200, ['msg' => 'ok', 'id' => (int)$pdo->lastInsertId()]];
	});
}

/**
 * Post-task-completion hook — called by client when a task reaches status=2.
 * Creates a draft run if an active task_done template matches the task title.
 * Exact-title target preferred; falls back to NULL (global) template.
 */
function plugin_audit_check_task(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']];
	$task_id = (int)($d['task_id'] ?? 0);
	if ($task_id <= 0) return [400, ['error' => 'task_id required']];
	$stmt = $pdo->prepare("SELECT title, user_id, status FROM tasks WHERE id=?");
	$stmt->execute([$task_id]);
	$task = $stmt->fetch();
	if (!$task) return [404, ['error' => 'task_not_found']];
	if (!$is_admin && (int)$task['user_id'] !== $uid) return [403, ['error' => 'forbidden']];
	if ((int)$task['status'] !== 2) return [200, ['msg' => 'not_done', 'run_id' => null]];

	$t = $pdo->prepare("SELECT id FROM audit_templates WHERE active=1 AND interval='task_done' AND target=? LIMIT 1");
	$t->execute([$task['title']]);
	$tpl_id = (int)$t->fetchColumn();
	if (!$tpl_id) {
		$t2 = $pdo->prepare("SELECT id FROM audit_templates WHERE active=1 AND interval='task_done' AND target IS NULL LIMIT 1");
		$t2->execute();
		$tpl_id = (int)$t2->fetchColumn();
	}
	if (!$tpl_id) return [200, ['msg' => 'no_template', 'run_id' => null]];

	$ex = $pdo->prepare("SELECT id FROM audit_runs WHERE template_id=? AND user_id=? AND run_date=? AND committed_at IS NULL LIMIT 1");
	$ex->execute([$tpl_id, (int)$task['user_id'], $dc->today]);
	if ($rid = (int)$ex->fetchColumn()) return [200, ['msg' => 'existing', 'run_id' => $rid]];

	return db_try('audit/check_task', function() use ($pdo, $tpl_id, $task, $dc) {
		$pdo->prepare("INSERT INTO audit_runs (template_id, run_date, user_id, results, has_issues) VALUES (?,?,?,?,0)")
			->execute([$tpl_id, $dc->today, (int)$task['user_id'], '[]']);
		return [200, ['msg' => 'created', 'run_id' => (int)$pdo->lastInsertId()]];
	});
}

/** Templates overdue for this user: no committed run in the current week/month window. */
function plugin_audit_due(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']];
	if (!_aud_can($is_admin, $uid, $pdo)) return [403, ['error' => 'forbidden']];
	[$wkStart] = _aud_week_bounds($dc->today);
	[$moStart] = _aud_month_bounds($dc->today);
	$target_uid = ($is_admin && !empty($_GET['user_id'])) ? (int)$_GET['user_id'] : $uid;
	$stmt = $pdo->prepare("SELECT t.id, t.title, t.interval,
			(SELECT MAX(r.run_date) FROM audit_runs r
			 WHERE r.template_id=t.id AND r.user_id=? AND r.committed_at IS NOT NULL) AS last_run
		FROM audit_templates t WHERE t.active=1 AND t.interval IN ('week_end','month_end') ORDER BY t.title");
	$stmt->execute([$target_uid]);
	$out = [];
	foreach ($stmt->fetchAll() as $r) {
		$last = $r['last_run'];
		$window = $r['interval'] === 'week_end' ? $wkStart : $moStart;
		if (!$last || $last < $window) $out[] = [
			'template_id' => (int)$r['id'],
			'title'	 => $r['title'],
			'interval'	=> $r['interval'],
			'last_run'	=> $last,
			'overdue'	 => $last && $last < $window,
		];
	}
	return [200, $out];
}

function plugin_audit_commit(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'POST'): array {
	if ($method !== 'POST') return [405, ['error' => 'method_not_allowed']];
	$run_id = (int)($d['run_id'] ?? 0);
	if ($run_id <= 0)					return [400, ['error' => 'run_id required']];
	if (!is_array($d['results'] ?? null)) return [400, ['error' => 'results must be array']];

	$chk = $pdo->prepare("SELECT user_id, committed_at FROM audit_runs WHERE id=?");
	$chk->execute([$run_id]);
	$row = $chk->fetch();
	if (!$row)									 return [404, ['error' => 'not_found']];
	if (!$is_admin && (int)$row['user_id'] !== $uid) return [403, ['error' => 'forbidden']];
	if ($row['committed_at'])						 return [400, ['error' => 'already_committed']];

	$issues = 0; $clean = [];
	foreach ($d['results'] as $r) {
		if (!is_array($r)) continue;
		$done = !empty($r['done']) ? 1 : 0;
		if (!$done) $issues++;
		$clean[] = ['done' => $done, 'comment' => is_string($r['comment'] ?? null) ? trim((string)$r['comment']) : ''];
	}

	return db_try('audit_commit', function() use ($pdo, $run_id, $clean, $issues, $dc) {
		$pdo->prepare("UPDATE audit_runs SET results=?, has_issues=?, committed_at=? WHERE id=? AND committed_at IS NULL")
			->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $issues > 0 ? 1 : 0, $dc->today . ' ' . $dc->time, $run_id]);
		return [200, ['msg' => 'ok', 'has_issues' => $issues > 0 ? 1 : 0]];
	});
}

function plugin_audit_print(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']];
	$run_id = (int)($_GET['run_id'] ?? 0);
	if ($run_id <= 0) return [400, ['error' => 'run_id required']];
	$stmt = $pdo->prepare("SELECT r.*, u.username, u.real_name, u.contact AS user_contact
		FROM audit_runs r LEFT JOIN users u ON u.id=r.user_id WHERE r.id=?");
	$stmt->execute([$run_id]);
	$row = $stmt->fetch();
	if (!$row)									 return [404, ['error' => 'not_found']];
	if (!$is_admin && (int)$row['user_id'] !== $uid) return [403, ['error' => 'forbidden']];
	if (!$row['committed_at'])						return [400, ['error' => 'not_committed']];
	$results = json_decode($row['results'] ?? '[]', true);
	if (!is_array($results)) $results = [];
	global $cfg;
	$org = array_filter($cfg ?? [], fn($v, $k) => str_starts_with($k, 'org_'), ARRAY_FILTER_USE_BOTH);
	return [200, [
		'run'	=> ['id' => (int)$row['id'], 'run_date' => $row['run_date'], 'committed_at' => $row['committed_at'], 'has_issues' => (int)$row['has_issues'], 'results' => $results],
		'template' => _aud_template_by_id($pdo, (int)$row['template_id']),
		'auditor'=> ['username' => $row['username'] ?? '', 'real_name' => $row['real_name'] ?? '', 'contact' => $row['user_contact'] ?? ''],
		'org'	=> $org,
		'today'	=> $dc->today,
	]];
}



// ─── REPORT (admin)

/** Aggregate pass rates per subtask + per-worker compliance. */
function plugin_audit_report(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	if ($method !== 'GET') return [405, ['error' => 'method_not_allowed']];
	if (!$is_admin) return [403, ['error' => 'forbidden']];
	$where = ['r.committed_at IS NOT NULL']; $params = [];
	if (!empty($_GET['from']))		{ $where[] = 'r.run_date>=?';	$params[] = $_GET['from']; }
	if (!empty($_GET['to']))		{ $where[] = 'r.run_date<=?';	$params[] = $_GET['to']; }
	if (!empty($_GET['template_id'])) { $where[] = 'r.template_id=?'; $params[] = (int)$_GET['template_id']; }
	$stmt = $pdo->prepare("SELECT r.template_id, r.user_id, r.results, r.has_issues, t.title AS template_title, u.username
		FROM audit_runs r LEFT JOIN audit_templates t ON t.id=r.template_id LEFT JOIN users u ON u.id=r.user_id
		WHERE " . implode(' AND ', $where));
	$stmt->execute($params);
	$by_tpl = []; $by_worker = [];
	foreach ($stmt->fetchAll() as $r) {
		$tid= (int)$r['template_id'];
		$uidr = (int)$r['user_id'];
		$by_worker[$uidr] ??= ['user_id' => $uidr, 'username' => $r['username'], 'runs' => 0, 'issues' => 0];
		$by_worker[$uidr]['runs']++;
		if ((int)$r['has_issues']) $by_worker[$uidr]['issues']++;
		$results = json_decode($r['results'] ?? '[]', true);
		if (!is_array($results)) continue;
		$by_tpl[$tid] ??= ['template_id' => $tid, 'template_title' => $r['template_title'], 'items' => []];
		foreach ($results as $idx => $item) {
			$by_tpl[$tid]['items'][$idx] ??= ['done' => 0, 'total' => 0];
			$by_tpl[$tid]['items'][$idx]['total']++;
			if (!empty($item['done'])) $by_tpl[$tid]['items'][$idx]['done']++;
		}
	}
	foreach ($by_tpl as $tid => &$entry) {
		$tpl = _aud_template_by_id($pdo, $tid);
		$names = $tpl ? array_column($tpl['subtasks_ordered'], 'name') : [];
		$items = [];
		foreach ($entry['items'] as $idx => $stat)
			$items[] = ['idx' => $idx, 'name' => $names[$idx] ?? ('#' . ($idx + 1)),
				'done' => $stat['done'], 'total' => $stat['total'],
				'rate' => $stat['total'] ? round(100 * $stat['done'] / $stat['total']) : 0];
		usort($items, fn($a, $b) => $a['rate'] <=> $b['rate']);
		$entry['items'] = $items;
	}
	unset($entry);
	return [200, ['by_template' => array_values($by_tpl), 'by_worker' => array_values($by_worker)]];
}



// ─── ACCESS (admin)

/** GET → all users with has_access flag; POST {user_id, grant:0|1} → toggle. */
function plugin_audit_access(PDO $pdo, array $d, int $uid, bool $is_admin, DateContext $dc, string $method = 'GET'): array {
	if (!$is_admin) return [403, ['error' => 'forbidden']];
	if ($method === 'GET') {
		$stmt = $pdo->query("SELECT u.id, u.username, u.real_name, (a.user_id IS NOT NULL) AS has_access
			FROM users u LEFT JOIN audit_access a ON a.user_id=u.id ORDER BY u.username");
		return [200, $stmt->fetchAll()];
	}
	if ($method === 'POST') {
		$target = (int)($d['user_id'] ?? 0);
		if ($target <= 1) return [400, ['error' => 'invalid_user']];
		$grant = !empty($d['grant']);
		return db_try('audit_access', function() use ($pdo, $target, $grant) {
			if ($grant) $pdo->prepare("INSERT OR IGNORE INTO audit_access (user_id) VALUES (?)")->execute([$target]);
			else		$pdo->prepare("DELETE FROM audit_access WHERE user_id=?")->execute([$target]);
			return [200, ['msg' => 'ok']];
		});
	}
	return [405, ['error' => 'method_not_allowed']];
}



// ─── VIEWS

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



// ─── REGISTRATION

$_preg = [
	'id'			 => 'audit',
	'schema_version' => 2,
	'schema' => [
		"CREATE TABLE IF NOT EXISTS audit_templates (
			id	 INTEGER PRIMARY KEY,
			title	TEXT NOT NULL,
			target TEXT,
			interval TEXT NOT NULL,
			subtasks TEXT NOT NULL,
			active INTEGER DEFAULT 1
		)",
		"CREATE TABLE IF NOT EXISTS audit_runs (
			id		 INTEGER PRIMARY KEY,
			template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
			run_date	 TEXT NOT NULL,
			user_id	INTEGER REFERENCES users(id),
			results	TEXT NOT NULL,
			has_issues INTEGER DEFAULT 0,
			committed_at TEXT
		)",
		"CREATE TABLE IF NOT EXISTS audit_subtasks (
			id		INTEGER PRIMARY KEY,
			template_id INTEGER REFERENCES audit_templates(id) ON DELETE CASCADE,
			sort_order INTEGER NOT NULL,
			name		TEXT NOT NULL
		)",
		"CREATE TABLE IF NOT EXISTS audit_access (
			user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE
		)",
		"CREATE INDEX IF NOT EXISTS idx_audit_runs_tmpl ON audit_runs(template_id, run_date)",
		"CREATE INDEX IF NOT EXISTS idx_audit_runs_issues ON audit_runs(has_issues) WHERE has_issues=1",
	],
	'routes' => [
		'audit_template'		=> 'plugin_audit_template',
		'audit_template/delete' => 'plugin_audit_template_delete',
		'audit_run'			 => 'plugin_audit_run',
		'audit_run/create'	=> 'plugin_audit_run_create',
		'audit_run/check_task'=> 'plugin_audit_check_task',
		'audit_run/due'		 => 'plugin_audit_due',
		'audit_run/print'	 => 'plugin_audit_print',
		'audit_commit'		=> 'plugin_audit_commit',
		'audit_report'		=> 'plugin_audit_report',
		'audit_access'		=> 'plugin_audit_access',
	],
	'views' => [
		'audit'	 => 'view_audit',
		'audit_print' => 'view_audit_print',
	],
	'nav'		=> ['audit' => __aud('nav_audit')],
	'etag_routes'=> ['audit_template', 'audit_run', 'audit_run/due', 'audit_run/print', 'audit_report', 'audit_access'],
	'write_routes' => ['audit_template', 'audit_template/delete', 'audit_run/create', 'audit_run/check_task', 'audit_commit', 'audit_access'],
];

$_pid = $_preg['id'] ?? '';
if ($_pid) {
	$plugins[$_pid] = $_preg;
	if (isset($_preg['routes']))	 foreach ($_preg['routes']	 as $_r=> $_h) $plugin_routes[$_r]= $_h;
	if (isset($_preg['views']))		foreach ($_preg['views']		as $_vk => $_vfn) $plugin_views[$_vk]= $_vfn;
	if (isset($_preg['nav']))		$plugin_nav		= array_merge($plugin_nav,		$_preg['nav']);
	if (isset($_preg['etag_routes']))$plugin_etag_routes= array_merge($plugin_etag_routes,$_preg['etag_routes']);
	if (isset($_preg['write_routes'])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg['write_routes']);
}
