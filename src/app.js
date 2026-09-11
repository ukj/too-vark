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
vrContainer.addEventListener('change', e => {
			// Mutual exclusivity: '*' unchecks 1-4; checking 1-4 unchecks '*'
			if (e.target.closest('.vr-weeks')) {
				const weeksDiv = e.target.closest('.vr-weeks');
				if (e.target.value === '*' && e.target.checked) {
					weeksDiv.querySelectorAll('input:not([value="*"])').forEach(cb => cb.checked = false);
				} else if (e.target.value !== '*' && e.target.checked) {
					const allCb = weeksDiv.querySelector('input[value="*"]');
					if (allCb) allCb.checked = false;
				}
			}
			syncVisualToText();
		});
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
/** Workaround: form.id collides with the DOM e