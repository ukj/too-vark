(function() {
	function sendLog(type, payload) {
		// keepalive: true ensures the request completes even if the user closes the tab!
		fetch('?api=debug_log', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
			body: JSON.stringify({ type, payload, url: location.href }),
			keepalive: true 
		}).catch(() => {}); // silently fail if offline
	}

	// 1. Catch standard JS errors
	window.addEventListener('error', function(e) {
		sendLog('error', {
			msg: e.message,
			file: e.filename,
			line: e.lineno,
			stack: e.error ? e.error.stack : ''
		});
	});

	// 2. Catch async/await and fetch errors! (CRITICAL for this project)
	window.addEventListener('unhandledrejection', function(e) {
		sendLog('promise_rejection', {
			reason: e.reason ? (e.reason.message || e.reason) : 'Unknown promise rejection',
			stack: e.reason ? e.reason.stack : ''
		});
	});

	// Optional: Expose a manual log function to the console for testing
	window.AppLog = function(msg) { sendLog('manual_log', { msg }); };
})();