/**
 * Fraud Tenant app shell behaviour (no dependencies):
 * - off-canvas navigation (tablet / mobile), Escape to close, focus return
 * - SSN inputs: digits + dashes only, auto-format, never stored in the browser
 * - incident review form: reason codes filtered by the chosen decision
 * - membership: hosted Stripe Checkout / Customer Portal via the REST API
 * - confirm prompts for destructive actions (data-ft-confirm)
 */
(function () {
	'use strict';
	var body = document.body;

	// Navigation drawer
	var openBtn = document.querySelector('[data-ft-open-nav]');
	var backdrop = document.querySelector('.ft-backdrop');
	function setNav(open) {
		body.classList.toggle('ft-nav-open', open);
		if (backdrop) { backdrop.hidden = !open; }
		if (openBtn) { openBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
		if (!open && openBtn) { openBtn.focus(); }
	}
	if (openBtn) { openBtn.addEventListener('click', function () { setNav(true); var first = document.querySelector('.ft-nav-item'); if (first) { first.focus(); } }); }
	document.querySelectorAll('[data-ft-close-nav]').forEach(function (el) { el.addEventListener('click', function () { setNav(false); }); });
	document.addEventListener('keydown', function (e) { if ('Escape' === e.key && body.classList.contains('ft-nav-open')) { setNav(false); } });

	// SSN inputs: keep only digits/dashes, format as 123-45-6789. Values are never persisted client-side.
	document.querySelectorAll('input[data-ft-ssn]').forEach(function (input) {
		input.setAttribute('autocomplete', 'off');
		input.addEventListener('input', function () {
			var d = input.value.replace(/\D/g, '').slice(0, 9);
			var out = d.length > 5 ? d.slice(0, 3) + '-' + d.slice(3, 5) + '-' + d.slice(5) : (d.length > 3 ? d.slice(0, 3) + '-' + d.slice(3) : d);
			input.value = out;
		});
		var toggle = document.querySelector('[data-ft-ssn-toggle="' + input.id + '"]');
		if (toggle) {
			toggle.addEventListener('click', function () {
				var show = 'password' === input.type;
				input.type = show ? 'text' : 'password';
				toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
				toggle.textContent = show ? toggle.getAttribute('data-hide') : toggle.getAttribute('data-show');
			});
		}
	});
	window.addEventListener('pagehide', function () {
		document.querySelectorAll('input[data-ft-ssn]').forEach(function (i) { i.value = ''; });
	});

	// Incident decision: show only the reason codes for the selected decision.
	var decisionForm = document.querySelector('input[name="decision"]') ? document.querySelector('input[name="decision"]').form : null;
	if (decisionForm) {
		var select = decisionForm.querySelector('select[name="reason_code"]');
		var map = { approve: 0, request_clarification: 1, reject: 2 };
		var sync = function () {
			var chosen = decisionForm.querySelector('input[name="decision"]:checked');
			if (!select || !chosen) { return; }
			var groups = select.querySelectorAll('optgroup');
			groups.forEach(function (g, i) { g.disabled = i !== map[chosen.value]; g.hidden = i !== map[chosen.value]; });
			var firstEnabled = select.querySelector('optgroup:not([disabled]) option');
			if (firstEnabled) { select.value = firstEnabled.value; }
			decisionForm.querySelectorAll('[data-ft-approve-only]').forEach(function (el) { el.hidden = 'approve' !== chosen.value; });
		};
		decisionForm.querySelectorAll('input[name="decision"]').forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	}

	// Destructive confirmations.
	document.querySelectorAll('form[data-ft-confirm]').forEach(function (f) {
		f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-ft-confirm'))) { e.preventDefault(); } });
	});

	// Membership: hosted Stripe Checkout / Customer Portal (server decides everything).
	var billing = document.querySelector('[data-ft-billing]');
	if (billing) {
		var rest = billing.getAttribute('data-rest-url');
		var nonce = billing.getAttribute('data-rest-nonce');
		var notice = billing.querySelector('[data-ft-billing-notice]');
		var show = function (msg) { if (notice) { notice.hidden = false; notice.textContent = msg; } };
		var call = function (btn, path, payload, key) {
			var label = btn.textContent;
			btn.disabled = true;
			btn.textContent = btn.getAttribute('data-busy') || label;
			fetch(rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify(payload || {}) })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
				.then(function (res) {
					var url = res.d && res.d.data && res.d.data[key];
					if (res.ok && url) { window.location.assign(url); return; }
					btn.disabled = false; btn.textContent = label;
					show((res.d && res.d.message) || 'The request could not be completed. Please try again.');
				})
				.catch(function () { btn.disabled = false; btn.textContent = label; show('Connection error. Please try again.'); });
		};
		billing.querySelectorAll('[data-ft-checkout]').forEach(function (b) { b.addEventListener('click', function () { call(b, '/owner/membership/checkout', { plan_code: b.getAttribute('data-ft-checkout') }, 'checkout_url'); }); });
		billing.querySelectorAll('[data-ft-portal]').forEach(function (b) { b.addEventListener('click', function () { call(b, '/owner/membership/portal', {}, 'portal_url'); }); });
	}
}());
