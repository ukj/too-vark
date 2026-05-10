<?php
/**
 * VIEWS — Pure HTML shell + templates.
 * 
 * REST EDITION: No $pdo, no SQL, no data arrays, no json_encode.
 * PHP session is used ONLY to decide which shell to render.
 * All dynamic data is fetched by app.js via the REST API.
 * 
 */

$is_month = ($scope === 'month');


/** Language toggle */
function html_lngTgl($view='0'):void { 
global $scope,$lang,$i18ni;
	foreach (array_keys($i18ni) as $i){
	if($lang != $i) echo 
	sprintf('<a href="?view=%s&lang=%s%s" class="lang-toggle">%s</a> ',
	$view, $i, $scope?"&scope=$scope":'', strtoupper($i) );
	}
}


/** needs class hint also */
function html_hint($hint=''){
	$hint=htmlspecialchars($hint);
echo <<<HINT
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



function view_change_password(): void {
$force = isset($_GET['msg']) && $_GET['msg'] === 'force_change';
?>
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



function view_nav($view,$nav_items, $is_admin, $u_name): void {
	global $today_date;
echo '<nav>';
foreach ($nav_items as $key => $label){
echo sprintf('<a href="?view=%s" %s>%s</a>',
$key,
$view===$key? 'class="active"' : '',
$label );
}
?>
</nav>

<table class="user-info no_Print"><thead><tr>
<td><?= __('g_worker') ?> <b><a href="?view=user_info"><?= htmlspecialchars($u_name) ?></a></b></td>
<td><b><?= $today_date ?></b></td>
<th><button type="button" onclick="cycleTextSize()" class="lang-toggle no_Print" title="Text size"><small>A</small>A</button></th>
<th><?= html_lngTgl($view) ?>
<a href="#" onclick="doLogout(event)" class="logout-btn no_Print"><?= __('logout') ?></a></th> 
</tr></thead></table>
<?php }



/** TODAY — empty container, filled by app.js via ?api=tasks/today */
function view_today(): void { ?>
<div id="today-tasks-container"></div>
<?php }



/** RULES — static shell. Data loaded by initRulesView() in app.js */
function view_rules(): void {
	global $ym, $is_admin;
?>
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



/** fetches print data from API and renders client-side e*/
function view_print(): void { ?>
<div id="print-container"></div>
<?php }



function view_team_nav(): void {
	global $scope, $is_month; ?>
<div class="sub-nav no_Print">
	<a href="?view=team&scope=today" <?= $scope === 'today' ? 'class="active"' : '' ?>><?= __('team_today') ?></a>
	<a href="?view=team&scope=month" <?= $is_month ? 'class="active"' : '' ?>><?= __('team_month') ?></a>
	<a href="?view=team&scope=wobjects" <?= $scope === 'wobjects' ? 'class="active"' : '' ?>><?= __('team_wobj') ?></a>
	<a href="?view=team&scope=baastegijad" <?= $scope === 'baastegijad' ? 'class="active"' : '' ?>><?= __('team_base') ?></a>
</div>
<?php }



/** TEAM TASKS — shell with form + empty container */
function view_team_tasks(): void {
	global $scope, $is_month, $today_date;
	view_team_nav();
?>
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


/** object locations details, populated by JS */
function view_objloc_mgmt(): void {
	view_team_nav();
?>
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



/** TEAM MANAGEMENT — user CRUD + details, populated by JS */
function view_team_mgmt(): void {
	view_team_nav();
?>
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
const _tplGroup = compileTpl(<?= json_encode(
'<div class="card team_group" {{data_date}}>'
.'<div class="group-header card_header">{{header}}</div>'
.'<table role="presentation" class="group-items card_body card_body--p10">{{rows}}</table>'
.'</div>') ?>);
const _tplRow = compileTpl(<?= json_encode(
'<tr class="team-row no_Print" data-id="{{id}}" title="' . __('g_click_to_edit') . '">'
.'<td><span class="t-user {{user_cls}}"><b>{{username}}</b></span><br>'
.'<span class="t-title">{{title}}</span><br>'
.'<span class="t-coworkers">{{coworkers}}</span></td>'
.'<td><span class="t-time">{{time}}</span><br>'
.'<span class="t-status status-{{status}}">{{status_text}}</span></td>'
.'<td width="20%"><span class="t-actions">'
.'<a class="btn-sm btn-silver print-link" href="?view=print&task_id={{id}}" target="_blank">🖨️</a>&nbsp;'
.'<button class="{{del_cls}}" title="' . __('g_btn_delete') . '" {{del_dis}}>✖</button>'
.'</span></td></tr>'
.'<tr><td colspan="3" class="t-notes">{{notes}}</td></tr>') ?>);
const _tplTask = compileTpl(<?= json_encode(
'<form class="card {{card_cls}}" onsubmit="updateTask(event)">'
.'<table role="presentation"><tbody><tr><td>'
.'<strong>{{title}}</strong><br>'
.'<span class="t-coworkers">{{coworkers}}</span></td>'
.'<td><input type="time" name="start_time" value="{{start_time}}">'
.'<input type="time" name="end_time" value="{{end_time}}"></td>'
.'<td width="20%">'
.'<a class="btn-sm btn-silver print-link" href="?view=print&task_id={{id}}" target="_blank">🖨️</a>'
.'<input type="hidden" name="status" value="{{status}}">'
.'<input type="hidden" name="id" value="{{id}}">'
.'<button type="submit" name="tegu" class="t-status btn-sm {{btn_cls}}" {{btn_dis}}>{{status_text}}</button></td></tr>'
.'<tr><td colspan="3"><textarea name="notes" placeholder="' . __('g_ph_notes') . '">{{notes}}</textarea>{{description}}</td></tr>'
.'</tbody></table></form>') ?>);
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
	<?php 
	
	foreach(explode(' ',__('rules_days_sh')) as $wdi => $wd) { 
if($wdi>0) echo 
"	<label><input type=\"checkbox\" value=\"{$wdi}\">{$wd}</label>
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

<?php }



// ─── HTML GENERATION HELPERS

/** Status <select> — used in task editor forms. */
function html_status_select(string $name='status'): string {
	$s = <<<SEL
<select name="$name">
<option value="0">%s</option>
<option value="1">%s</option>
<option value="2">%s</option></select>
SEL;
return sprintf($s, 
__('status_0'),
__('status_1'),
__('status_2') );
}



/**
 * Shared task editor fields — the core that appears in rules form and team form.
 * Caller wraps in <form> with their own id, style, heading, and worker selector.
 */
function html_task_fields(string $date_value=''): string {

return sprintf( '<input type="hidden" name="id" value="">
<label>
<input type="text" name="title" placeholder="%s" list="known-task-titles" required>
<datalist id="known-task-titles"></datalist></label>
<table role="presentation"><tr>
<td>%s:</td><td>%s</td>
<td>%s:</td><td><input type="date" name="task_date" value="%s" required></td></tr>

<tr><td>%s:</td><td><input type="time" name="start_time"></td>
<td>%s:</td><td><input type="time" name="end_time"></td></tr>
</table>
<br><textarea name="notes" placeholder="%s"></textarea>' ,
__('g_ph_location'),
__('Status_sh'),
html_status_select(),
__('date_short'),
$date_value,
__('start_short'),
__('end_short'),
__('g_ph_notes') );
}



/** Save + Cancel button pair. */
function html_save_cancel(string $save_label='',string $clear_label='',bool $br=true): string {
	if (!$save_label) $save_label=__('g_btn_save');
	if (!$clear_label) $clear_label=__('g_btn_clear');
	
	return sprintf('<button type="submit" class="btn-sm btn-green">%s</button>%s<button type="button" 
	class="btn-sm btn-silver" 
	onclick="this.form.reset(); this.form.id.value=\'\';">%s</button>',
$save_label,
($br?'<br>':''),
$clear_label);
}



// ─── DISPATCH

if (!$logged_in) { view_login(); }
else {
	$nav_items = [
	'today' => __('nav_today'),
	'rules' => __('nav_rules'),
	...($is_admin? ['team' => __('nav_team')] : [])
	];
	// Merge plugin nav items (plugins may set 'admin_only' => true)
	foreach ($plugin_nav as $pnk => $pnv) {
		if (isset($plugins[$pnk]['admin_only']) && $plugins[$pnk]['admin_only'] && !$is_admin) continue;
		$nav_items[$pnk] = $pnv;
	}
	view_nav($view,$nav_items, $is_admin, $u_name);
	match($view) {
		'today'		=> view_today(),
		'rules'		=> view_rules(),
		'change_password' => view_change_password(),
		'user_info'	=> view_user_info(),
		'print'		=> view_print(),
		'team'		=> match($scope) {
			'baastegijad'	=> view_team_mgmt(),
			'wobjects'=>	view_objloc_mgmt(),
			default			=> view_team_tasks(),
		},
		default		=> isset($plugin_views[$view])
			? ($plugin_views[$view])()
			: view_today(),
	};
	view_templates();
}
?>
