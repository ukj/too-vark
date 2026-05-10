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
