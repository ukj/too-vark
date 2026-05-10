<?php
// ─── API HANDLER FUNCTIONS
// PDO + params, return [int $code, array $body].
// Called by api.php router AND by tests/run.php directly.
// Depends on helpers.php (validate_task, upsert_task, statusLabels, btnLabels, timeShift, __).

/** Worker's today view — includes yesterday's unfinished. */
function api_tasks_today(PDO $pdo, int $uid, DateContext $dc): array {
	$today_date = $dc->today;
	$current_time = $dc->time;
	$yesterday = $dc->yesterday();

//	$stmt=$pdo->prepare("SELECT * FROM tasks WHERE user_id=? AND (task_date=? OR task_date=?) ORDER BY title");
	
$stmt=$pdo->prepare("SELECT t.*, d.description 
FROM tasks t LEFT JOIN task_details d ON t.title=d.title 
WHERE user_id=? AND (task_date=? OR task_date=?)
ORDER BY t.title");
	
	$stmt->execute([$uid,$yesterday, $today_date]);
	$btn=btnLabels();

	$coworkers = coworkers_map($pdo, $uid, "(t.task_date=? OR t.task_date=?)", [$yesterday, $today_date]);

	$out=[];
	foreach ($stmt->fetchAll() as $r) {
		if ($r['status'] == 2 && $r['task_date'] != $today_date) continue;
		[$start, $end]=timeShift($r['start_time'], $r['end_time']);
		if ($r['status'] == '1') $end=$current_time;
		elseif ($r['status'] == '2') $end=$r['end_time'];
		$key=$r['task_date']."\t".$r['title'];
		$out[]=[
		'id'		=> (int)$r['id'],
		'title'		=> $r['title'],
		'start_time' => $r['status'] == '0' ? $start : $r['start_time'],
		'end_time'	=> $end,
		'status'	=> (int)$r['status'],
		'status_text'=> $btn[(int)$r['status']],
		'notes'		=> (string)$r['notes'],
		'description' => $r['description'],
		'coworkers'	=> $coworkers[$key] ?? []
		];
	}
	return [200, $out];
}


/** Month tasks + rules + meta for one user. */
function api_tasks_month(PDO $pdo, int $uid, bool $is_admin, string $ym, int $target_uid, DateContext $dc): array {
	$today_date = $dc->today;
	
	$stmt=$pdo->prepare("SELECT rules_text FROM user_rules WHERE user_id=?");
	$stmt->execute([$target_uid]);
	$rules_text=$stmt->fetchColumn() ?: "";

	$status_texts=statusLabels();
	$grouped=[];
	$ym_first = $ym . '-01';
	$prev_first = date('Y-m-d', strtotime($ym_first . ' -1 month'));
	$next_first = date('Y-m-d', strtotime($ym_first . ' +1 month'));

// Single query: fetch both last month and current month, split in PHP
//$t=hrtime(true);//DB_TIME
	$stmt=$pdo->prepare("SELECT id, user_id, title, start_time, end_time, status, task_date, notes FROM tasks WHERE user_id=? AND task_date >= ? AND task_date < ? ORDER BY task_date, start_time, title");
	$stmt->execute([$target_uid, $prev_first, $next_first]);
	$all_rows=$stmt->fetchAll();
//if(function_exists('timer_log'))timer_log(['qs'=>'SQL:tasks_month','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME

	//$t=hrtime(true);//DB_TIME
	$coworkers = coworkers_map($pdo, $target_uid, "t.task_date >= ? AND t.task_date < ?", [$ym_first, $next_first]);
//if(function_exists('timer_log'))timer_log(['qs'=>'SQL:coworkers_month','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME

	$last_month_data = [];
	foreach ($all_rows as $row) {
		if ($row['task_date'] < $ym_first) {
			$last_month_data[] = array_values([
$row['title']
,$row['task_date']
,$row['start_time']
,$row['end_time']
,statusLabels()[$row['status']]
,$row['notes']]);
//$last_month_data[] = array_values([$row['id  $row['user_id'],$row['title']]);
	continue;
		}
		$row['status_text']=$status_texts[(int)$row['status']] ?? '?';
		$row['username']='';
		$key=$row['task_date']."\t".$row['title'];
		$row['coworkers']=$coworkers[$key] ?? [];
		$grouped[$row['task_date']][]=$row;
	}

	$workers=$is_admin
		? $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll()
		: [];

	return [200, [
		'target_uid'	=> $target_uid,
		'rules_text'	=> $rules_text,
		'grouped'		=> $grouped,
		'ym'			=> $ym,
		'prev_ym'		=> substr($prev_first, 0, 7),
		'next_ym'		=> substr($next_first, 0, 7),
		'today'			=> $today_date,
		'last_month_data'=> $last_month_data,
		'workers'		=> $workers,
		'known_titles' => known_titles($pdo),
		'full_weeks'	=> full_weeks_info($ym),
		'is_admin'		=> $is_admin,
	]];
}


/** Admin team overview — grouped by time (today) or date (month). */
function api_tasks_team(PDO $pdo, bool $is_admin, string $scope, DateContext $dc): array {
	$today_date = $dc->today;
	$current_month_first = $dc->month;
	if (!$is_admin) return [403, ['error' => 'forbidden']];
	$is_month=($scope === 'month');

	$status_texts=statusLabels();
	$grouped=[];
//$t=hrtime(true);//DB_TIME
	if ($is_month) {
		$ym_first = $current_month_first;
		$next_first = date('Y-m-d', strtotime($ym_first . ' +1 month'));
		$stmt=$pdo->prepare("SELECT t.id, u.username, u.id as user_id, t.title, t.start_time, t.end_time, t.status, t.notes, t.task_date
			FROM tasks t JOIN users u ON t.user_id=u.id
			WHERE t.task_date >= ? AND t.task_date < ?
			ORDER BY t.task_date, u.username");
		$stmt->execute([$ym_first, $next_first]);
	} else {
		$stmt=$pdo->prepare("SELECT t.id, u.username, u.id as user_id, t.title, t.start_time, t.end_time, t.status, t.notes, t.task_date
			FROM tasks t JOIN users u ON t.user_id=u.id
			WHERE t.task_date = ?
			ORDER BY t.start_time, u.username");
		$stmt->execute([$today_date]);
	}
//if(function_exists('timer_log'))timer_log(['qs'=>'SQL:tasks_team','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME

	foreach ($stmt->fetchAll() as $row) {
		$key=$is_month ? $row['task_date'] : ($row['start_time'] ?: '???');
		$row['status_text']=$status_texts[(int)$row['status']] ?? '?';
		$grouped[$key][]=$row;
	}

	return [200, [
		'grouped'		=> $grouped,
		'workers'		=> workers_list($pdo),
		'known_titles' => known_titles($pdo),
		'is_month'		=> $is_month,
		'today'			=> $today_date,
	]];
}


/** Print data — joins tasks with details. */
function api_tasks_print(PDO $pdo, int $uid, bool $is_admin, ?string $task_id, DateContext $dc): array {
	$today_date = $dc->today;
//$t=hrtime(true);//DB_TIME
	$sql="SELECT u.username, u.real_name, u.contact AS user_contact, t.title, t.task_date, t.start_time, t.end_time, t.notes, d.address, d.description, d.related_person
			FROM tasks t JOIN users u ON t.user_id=u.id
			LEFT JOIN task_details d ON t.title=d.title WHERE ";
	$params=[];
	if ($task_id) {
		$sql .= "t.id=?";
		$params[]=$task_id;
		if (!$is_admin) { $sql .= " AND t.user_id=?"; $params[]=$uid; }
	} else {
		if (!$is_admin) return [403, ['error' => 'forbidden']];
		$sql .= "t.task_date=?";
		$params[]=$today_date;
	}
	$sql .= " ORDER BY u.username, t.start_time";
	$stmt=$pdo->prepare($sql);
	$stmt->execute($params);
	$print_data=[];
	foreach ($stmt->fetchAll() as $row) {
		$key = ($row['real_name'] ?: $row['username']);
		$print_data[$key][]=$row;
	}
//if(function_exists('timer_log'))timer_log(['qs'=>'SQL:api_tasks_print','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME

// Org config for print header (from global $cfg loaded at startup)
	global $cfg;
	$org = array_filter($cfg, fn($v, $k) => str_starts_with($k, 'org_'), ARRAY_FILTER_USE_BOTH);
	return [200, ['print_data' => $print_data, 'today' => $today_date, 'org' => $org]];
}


/** Status progression: 0→1→2. */
function api_tasks_status(PDO $pdo, array $d, int $uid): array {
	if (empty($d['id'])) return [400, ['error' => 'id is required']];
	if ($err = validate_task($d, false, false)) return [400, ['error' => $err]];
	$new_status=min((int)$d['status'] + 1, 2);
	$stmt=$pdo->prepare("UPDATE tasks SET start_time=?, end_time=?, status=?, notes=? WHERE id=? AND user_id=?");
	$stmt->execute([$d['start_time'], $d['end_time'], $new_status, $d['notes'] ?? '', $d['id'], $uid]);
	if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']];
	$labels=btnLabels();
	return [200, ['status' => (string)$new_status, 'start_time' => $d['start_time'], 'end_time' => $d['end_time'], 'msg' => $labels[$new_status]]];
}


/** Create or update a single task. */
function api_tasks_save(PDO $pdo, array $d, int $uid, bool $is_admin): array {
	if ($err = validate_task($d)) return [400, ['error' => $err]];
	$status=isset($d['status']) ? (int)$d['status'] : 0;
	
	return db_try('api_tasks_save', function() use ($pdo, $d, $uid, $is_admin, $status) {
		if (!empty($d['id'])) {
			$sql="UPDATE tasks SET title=?, task_date=?, start_time=?, end_time=?, status=?, notes=?";
			$params=[$d['title'], $d['task_date'], $d['start_time'], $d['end_time'], $status, $d['notes'] ?? ''];
			if ($is_admin && !empty($d['user_id'])) { $sql .= ", user_id=?"; $params[]=(int)$d['user_id']; }
			$sql .= " WHERE id=?";
			$params[]=$d['id'];
			if (!$is_admin) { $sql .= " AND user_id=?"; $params[]=$uid; }
			$pdo->prepare($sql)->execute($params);
			$new_id = (int)$d['id'];
		} else {
			$target_uid=$uid;
			if ($is_admin) {
				if (!empty($d['user_id'])) $target_uid=(int)$d['user_id'];
				elseif (!empty($d['worker_ids'])) $target_uid=is_array($d['worker_ids']) ? (int)$d['worker_ids'][0] : (int)$d['worker_ids'];
			}
			upsert_task($pdo, $target_uid, $d['title'], $d['task_date'], $d['start_time'], $d['end_time'], $status, $d['notes'] ?? '');

			$stmt = $pdo->prepare("SELECT id FROM tasks WHERE user_id=? AND task_date=? AND title=?");			
			$stmt->execute([$target_uid, $d['task_date'], $d['title']]);
			$new_id = (int)$stmt->fetchColumn();
		}
		return [200, ['msg' => 'ok', 'id' => $new_id]];
	}, 'Worker already has a task with this title on this date.');
}


/** Delete task with status<2 guard. */
function api_tasks_delete(PDO $pdo, array $d, int $uid, bool $is_admin): array {
	if (empty($d['id'])) return [400, ['error' => 'id is required']];
	$sql="DELETE FROM tasks WHERE id=? AND status < 2";
	$params=[$d['id']];
	if (!$is_admin) { $sql .= " AND user_id=?"; $params[]=$uid; }
	$stmt=$pdo->prepare($sql);
	$stmt->execute($params);
	if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']];
	return [200, ['msg' => 'ok']];
}


/** Save rules + auto-cleanup + generate schedule. */
function api_rules_generate(PDO $pdo, array $d, int $target_uid, string $req_ym): array {
	$rules_json=trim($d['rules_txt'] ?? '');
	$ym_first = $req_ym . '-01';
	$next_first = date('Y-m-d', strtotime($ym_first . ' +1 month'));

	$rules=json_decode($rules_json, true);
	if (!is_array($rules)) return [400, ['error' => 'Invalid rules JSON']];

	//$t=hrtime(true);//DB_TIME
	$pdo->beginTransaction();
	try {
		$pdo->prepare("INSERT INTO user_rules (user_id, rules_text) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET rules_text=excluded.rules_text")
			->execute([$target_uid, $rules_json]);
		$pdo->prepare("DELETE FROM tasks WHERE user_id=? AND task_date >= ? AND task_date < ? AND status=0 AND source='rule'")
			->execute([$target_uid, $ym_first, $next_first]);

		$insert_stmt=$pdo->prepare("INSERT OR IGNORE INTO tasks (user_id, task_date, title, start_time, end_time, status, notes, source) VALUES (?, ?, ?, ?, ?, 0, '', 'rule')");

		$y=(int)substr($req_ym, 0, 4);
		$m=(int)substr($req_ym, 5, 2);
		$days_in_month=(int)date('t', mktime(0, 0, 0, $m, 1, $y));

// Reuse shared full-week helper → build ISO-to-relative mapping
		$fw_info = full_weeks_info($req_ym);
		$iso_to_rel = [];
		foreach ($fw_info as $rel => $wk) $iso_to_rel[$wk['iso']] = $rel;

		for ($day=1; $day <= $days_in_month; $day++) {
			$ts=mktime(12, 0, 0, $m, $day, $y);
			$day_num=(int)date('N', $ts);
			$iso_week=(int)date('W', $ts);

			$rel_week=$iso_to_rel[$iso_week] ?? 0;
			if ($rel_week === 0) continue; // partial week — skip

			foreach ($rules as $r) {
				if (empty($r['title']) || empty($r['days']) || empty($r['weeks']) || empty($r['start']) || empty($r['end'])) continue;

				$day_col=strtr($r['days'], ['E'=>'1','T'=>'2','K'=>'3','N'=>'4','R'=>'5','L'=>'6','P'=>'7']);
				$match=(strpos($day_col, (string)$day_num) !== false) && (strpos((string)$r['weeks'], (string)$rel_week) !== false);
				if ($match) $insert_stmt->execute([$target_uid, date('Y-m-d', $ts), $r['title'], $r['start'], $r['end']]);
			}
		}
		$pdo->commit();
		//if(function_exists('timer_log'))timer_log(['qs'=>'SQL:rules_gen','jstm'=>0,'fetch'=>round((hrtime(true)-$t)/1e6,2),'render'=>0]);//DB_TIME
		return [200, ['msg' => 'ok']];
	} catch (Exception $e) {
		$pdo->rollBack();
		error_log('api_rules_generate: ' . $e->getMessage());
		return [500, ['error' => 'Schedule generation failed']];
	}
}



/** All task details + known addresses/contacts.
 * Bundles users + config to save round-trips on initial admin page load.
 * Inline config edits use the lighter standalone ?api=config GET instead. */
function api_details_get(PDO $pdo): array {
	$all=$pdo->query("SELECT * FROM task_details ORDER BY title")->fetchAll();
	$addresses = array_values(array_unique(array_filter(array_column($all, 'address'))));
	$contacts = array_values(array_unique(array_filter(array_column($all, 'related_person'))));	
	
	$wal_status = $pdo->query("PRAGMA wal_checkpoint(PASSIVE);")->fetchAll(PDO::FETCH_ASSOC);
	if(array_sum($wal_status[0])==0)
	$db_status = "WAL OK";
	else
	$db_status = "WAL checkpoint status: ". print_r($wal_status[0],true);
$users = users_full_list($pdo);
global $cfg;
$config = array_map(fn($k,$v) => ['key'=>$k,'val'=>$v], array_keys($cfg), array_values($cfg));
	return [200, [
		'details' => $all, 
		'known_addresses' => $addresses, 
		'known_contacts' => $contacts, 
		'db_status'=>$db_status,
'users'=>$users, 'config'=>$config
		]];
}



/** Batch-assign a task to multiple workers. Admin only (checked in router). */
function api_tasks_batch(PDO $pdo, array $d): array {
	if ($err = validate_task($d)) return [400, ['error' => $err]];
	if (empty($d['worker_ids']) || !is_array($d['worker_ids'])) return [400, ['error' => 'worker_ids required']];
	$pdo->beginTransaction();
	$result = db_try('tasks/batch', function() use ($pdo, $d) {
		foreach ($d['worker_ids'] as $wid) {
			upsert_task($pdo, (int)$wid, $d['title'], $d['task_date'], $d['start_time'], $d['end_time'], 0, $d['notes'] ?? '');
		}
		$pdo->commit();
		return [200, ['msg' => 'ok', 'updated' => count($d['worker_ids'])]];
	});
	if ($result[0] !== 200 && $pdo->inTransaction()) $pdo->rollBack();
	return $result;
}


/** List all users. Admin only (checked in router). */
function api_users_list(PDO $pdo): array {
	return [200, users_full_list($pdo)];
}



/** Update an existing user. Admin only (checked in router). */
function api_users_update(PDO $pdo, array $d): array {
	$id = (int)($d['id'] ?? 0);
	if ($id <= 0) return [400, ['error' => 'id is required']];
	if ($id === 1) return [403, ['error' => 'admin_protected']];
	$username = trim($d['username'] ?? '');
	if ($username === '') return [400, ['error' => 'username is required']];
	return db_try('users/update', function() use ($pdo, $d, $id, $username) {
		$sql = "UPDATE users SET username=?, real_name=?, contact=?";
		$params = [$username, trim($d['real_name'] ?? ''), trim($d['contact'] ?? '')];
		if (!empty($d['password'])) {
			$sql .= ", password=?";
			$params[] = password_hash($d['password'], PASSWORD_DEFAULT);
		}
		$sql .= " WHERE id=?";
		$params[] = $id;
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		if ($stmt->rowCount() === 0) return [404, ['error' => 'not_found']];
		return [200, ['msg' => 'ok', 'id' => $id, 'username' => $username, 'real_name' => trim($d['real_name'] ?? ''), 'contact' => trim($d['contact'] ?? '')]];
	}, 'username_exists');
}


/** Create a new user. Admin only (checked in router). */
function api_users_create(PDO $pdo, array $d): array {
	$a_u = trim($d['username'] ?? '');
	$a_p = trim($d['password'] ?? '');
	$a_rn = trim($d['real_name'] ?? '');
	$a_c = trim($d['contact'] ?? '');
	if ($a_u === '' || $a_p === '') return [400, ['error' => 'empty_credentials']];
	return db_try('users', function() use ($pdo, $a_u, $a_p, $a_rn, $a_c) {
		if (insert_user($pdo, $a_u, $a_p,$a_rn,$a_c) === 0) return [400, ['error' => 'user_exists']];
		$new_id = (int)$pdo->lastInsertId();
		return [200, ['msg' => 'ok', 'id' => $new_id, 'username' => $a_u, 'real_name' => $a_rn, 'contact' => $a_c]];
	});
}


/** Delete a user by id. Admin protected (id<=1). Admin only (checked in router). */
function api_users_delete(PDO $pdo, array $d): array {
	$id = (int)($d['id'] ?? 0);
	if ($id <= 1) return [403, ['error' => 'admin_protected']];
	return db_try('users/delete', function() use ($pdo, $id) {
		$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
		$stmt->execute([$id]);
		return $stmt->rowCount() === 0 ? [404, ['error' => 'not_found']] : [200, ['msg' => 'ok']];
	});
}


/** Change own password. Verifies old password first. */
function api_users_password(PDO $pdo, array $d, int $uid): array {
	if (empty($d['new_password'])) return [400, ['error' => 'empty_password']];
	if (empty($d['old_password'])) return [400, ['error' => 'old_password_required']];
	$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
	$stmt->execute([$uid]);
	$hash = $stmt->fetchColumn();
	if (!$hash || !password_verify($d['old_password'], $hash))
		return [403, ['error' => 'wrong_password']];
	return db_try('users/password', function() use ($pdo, $d, $uid) {
		$stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
		$stmt->execute([password_hash($d['new_password'], PASSWORD_DEFAULT), $uid]);
		return $stmt->rowCount() === 0 ? [404, ['error' => 'user_not_found']] : [200, ['msg' => 'ok']];
	});
}


/** Save or update a location detail. Admin only (checked in router). */
function api_details_save(PDO $pdo, array $d): array {
	if (trim($d['title'] ?? '') === '') return [400, ['error' => 'title is required']];
	return db_try('details', fn() => (
		$pdo->prepare("INSERT INTO task_details (title, address, description, related_person) VALUES (?, ?, ?, ?)
		ON CONFLICT(title) DO UPDATE SET address=excluded.address, description=excluded.description, related_person=excluded.related_person")
		->execute([trim($d['title']), $d['address'], $d['description'], $d['related_person']])
	) ? [200, ['msg' => 'ok']] : [500, ['error' => 'Database error']]);
}


/** Delete a location detail by title. Admin only (checked in router). */
function api_details_delete(PDO $pdo, array $d): array {
	return db_try('details/delete', function() use ($pdo, $d) {
		$stmt = $pdo->prepare("DELETE FROM task_details WHERE title = ?");
		$stmt->execute([$d['title']]);
		return $stmt->rowCount() === 0 ? [404, ['error' => 'not_found']] : [200, ['msg' => 'ok']];
	});
}
