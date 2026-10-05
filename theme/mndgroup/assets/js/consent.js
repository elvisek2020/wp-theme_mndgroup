/**
 * MND Group — cookie lišta a Google Analytics 4 (Consent Mode v2).
 *
 * Výchozí stav „denied“ nastavuje inline skript v <head> (inc/analytics-consent.php).
 * Po souhlasu: consent update → config → teprve pak se stáhne gtag.js, takže první
 * zobrazení stránky se změří už s cookies. Bez souhlasu se ke Googlu nepošle nic.
 * Volba se drží v localStorage 12 měsíců, pak se lišta zeptá znovu.
 */
(function () {
	'use strict';

	var bar = document.getElementById('mnd-consent');
	if (!bar || typeof window.gtag !== 'function') {
		return;
	}

	var KEY = 'mndConsent';
	var MAX_AGE = 365 * 24 * 60 * 60 * 1000;
	var id = bar.getAttribute('data-ga');
	var loaded = false;

	function read() {
		try {
			var value = JSON.parse(window.localStorage.getItem(KEY));
			if (value && value.t > Date.now() - MAX_AGE && (value.v === 'granted' || value.v === 'denied')) {
				return value.v;
			}
		} catch (e) {}
		return null;
	}

	function save(value) {
		try {
			window.localStorage.setItem(KEY, JSON.stringify({ v: value, t: Date.now() }));
		} catch (e) {}
	}

	function load() {
		if (loaded) {
			return;
		}
		loaded = true;
		window.gtag('consent', 'update', { analytics_storage: 'granted' });
		window.gtag('js', new Date());
		window.gtag('config', id);
		var script = document.createElement('script');
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
		document.head.appendChild(script);
	}

	// Smaže cookies _ga a _ga_XXXX na všech úrovních domény (www.mndgroup.eu, .mndgroup.eu …).
	function clearCookies() {
		var parts = location.hostname.split('.');
		document.cookie.split(';').forEach(function (cookie) {
			var name = cookie.split('=')[0].trim();
			if (!/^_ga(_|$)/.test(name)) {
				return;
			}
			for (var i = 0; i < parts.length; i++) {
				var domain = parts.slice(i).join('.');
				document.cookie = name + '=; Max-Age=0; path=/; domain=' + domain;
			}
			document.cookie = name + '=; Max-Age=0; path=/';
		});
	}

	function show() {
		bar.hidden = false;
	}

	function decide(value) {
		save(value);
		bar.hidden = true;
		if (value === 'granted') {
			load();
		} else {
			if (loaded) {
				window.gtag('consent', 'update', { analytics_storage: 'denied' });
			}
			clearCookies();
		}
	}

	bar.addEventListener('click', function (event) {
		var button = event.target.closest('[data-mnd-consent]');
		if (button) {
			decide(button.getAttribute('data-mnd-consent'));
		}
	});

	document.addEventListener('click', function (event) {
		if (event.target.closest('[data-mnd-consent-open]')) {
			show();
			var first = bar.querySelector('button');
			if (first) {
				first.focus();
			}
		}
	});

	var choice = read();
	if (choice === 'granted') {
		load();
	} else if (choice === null) {
		show();
	}
})();
