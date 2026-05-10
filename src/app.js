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