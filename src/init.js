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