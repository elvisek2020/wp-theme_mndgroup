/**
 * MND Group – skripty šablony (bez jQuery a dalších knihoven).
 *
 * 1. Prezentace bannerů – prolínání, šipky, tečky, pauza, swipe, klávesnice.
 *    Automatické přehrávání se zastaví při najetí myší, fokusu, skryté záložce
 *    a vůbec se nespustí, pokud si uživatel v systému vypnul animace.
 * 2. Dlaždice – rozbalení seznamu společností na dotykových zařízeních.
 */
(function () {
	'use strict';

	var l10n = window.mndgroupL10n || {
		goTo: 'Show slide %s',
		pause: 'Pause slideshow',
		play: 'Play slideshow'
	};

	function format(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		return text
			.replace(/%(\d+)\$s/g, function (m, n) { return args[n - 1]; })
			.replace(/%s/, args[0]);
	}

	/* 1. Prezentace bannerů
	   ====================================================================== */

	function initCarousel(root) {
		var slides = Array.prototype.slice.call(root.querySelectorAll('.mnd-hero__slide'));
		if (slides.length < 2) {
			return;
		}

		var viewport = root.querySelector('.mnd-hero__slides');
		var dotsWrap = root.querySelector('[data-mnd-carousel-dots]');
		var pauseBtn = root.querySelector('[data-mnd-carousel-pause]');
		var interval = parseInt(root.getAttribute('data-interval'), 10) || 6000;
		var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

		var current = 0;
		var timer = null;
		var userPaused = reducedMotion.matches; // bez animací se nepřehrává automaticky
		var hovered = false;
		var focused = false;
		var dots = [];

		slides.forEach(function (slide, i) {
			var dot = document.createElement('button');
			dot.type = 'button';
			dot.className = 'mnd-hero__dot';
			dot.setAttribute('aria-label', format(l10n.goTo, i + 1));
			dot.addEventListener('click', function () {
				go(i, true);
			});
			dotsWrap.appendChild(dot);
			dots.push(dot);
		});

		function go(index, byUser) {
			var next = (index + slides.length) % slides.length;
			if (next !== current) {
				slides[current].classList.remove('is-active');
				slides[current].setAttribute('aria-hidden', 'true');
				slides[next].classList.add('is-active');
				slides[next].removeAttribute('aria-hidden');
				current = next;
			}
			dots.forEach(function (dot, i) {
				if (i === current) {
					dot.setAttribute('aria-current', 'true');
				} else {
					dot.removeAttribute('aria-current');
				}
			});
			// Čtečky obrazovky oznámí změnu jen tehdy, když ji vyvolal uživatel.
			viewport.setAttribute('aria-live', byUser ? 'polite' : 'off');
			if (byUser) {
				restart();
			}
		}

		function stop() {
			window.clearTimeout(timer);
			timer = null;
		}

		function restart() {
			stop();
			if (userPaused || hovered || focused || document.hidden) {
				return;
			}
			timer = window.setTimeout(function () {
				go(current + 1, false);
				restart();
			}, interval);
		}

		function setPaused(paused) {
			userPaused = paused;
			root.classList.toggle('is-paused', paused);
			if (pauseBtn) {
				pauseBtn.setAttribute('aria-label', paused ? l10n.play : l10n.pause);
			}
			restart();
		}

		root.querySelector('[data-mnd-carousel-prev]').addEventListener('click', function () {
			go(current - 1, true);
		});
		root.querySelector('[data-mnd-carousel-next]').addEventListener('click', function () {
			go(current + 1, true);
		});
		if (pauseBtn) {
			pauseBtn.addEventListener('click', function () {
				setPaused(!userPaused);
			});
		}

		root.addEventListener('mouseenter', function () { hovered = true; stop(); });
		root.addEventListener('mouseleave', function () { hovered = false; restart(); });
		root.addEventListener('focusin', function () { focused = true; stop(); });
		root.addEventListener('focusout', function (e) {
			if (!root.contains(e.relatedTarget)) {
				focused = false;
				restart();
			}
		});
		document.addEventListener('visibilitychange', restart);

		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				go(current - 1, true);
			} else if (e.key === 'ArrowRight') {
				go(current + 1, true);
			}
		});

		// Swipe prstem.
		var startX = null;
		var startY = null;
		viewport.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse') {
				return;
			}
			startX = e.clientX;
			startY = e.clientY;
		});
		viewport.addEventListener('pointerup', function (e) {
			if (startX === null) {
				return;
			}
			var dx = e.clientX - startX;
			var dy = e.clientY - startY;
			startX = null;
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
				go(current + (dx < 0 ? 1 : -1), true);
			}
		});
		viewport.addEventListener('pointercancel', function () {
			startX = null;
		});

		// Ostatní bannery mají loading="lazy" (nebrzdí první vykreslení). Po načtení stránky
		// je stáhneme na pozadí, aby při prolnutí nebyla vidět prázdná plocha.
		function preloadSlides() {
			slides.forEach(function (slide) {
				var img = slide.querySelector('img');
				if (img && img.loading === 'lazy') {
					img.loading = 'eager';
				}
			});
		}
		if (document.readyState === 'complete') {
			preloadSlides();
		} else {
			window.addEventListener('load', preloadSlides);
		}

		go(0, false);
		setPaused(userPaused);
		root.classList.add('is-ready');
	}

	/* 2. Dlaždice – rozbalení seznamu společností
	   ====================================================================== */

	function initSegments(nav) {
		var buttons = Array.prototype.slice.call(nav.querySelectorAll('.mnd-segments__toggle, .mnd-segments__trigger'));
		if (!buttons.length) {
			return;
		}

		var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

		function setOpen(item, open) {
			item.classList.toggle('is-open', open);
			item.querySelectorAll('.mnd-segments__toggle, .mnd-segments__trigger').forEach(function (btn) {
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}

		function closeAll(except) {
			nav.querySelectorAll('.mnd-segments__item.is-open').forEach(function (item) {
				if (item !== except) {
					setOpen(item, false);
				}
			});
		}

		buttons.forEach(function (btn) {
			var item = btn.closest('.mnd-segments__item');
			var submenu = item && item.querySelector('.mnd-segments__submenu');
			if (!submenu) {
				return;
			}
			if (!submenu.id) {
				submenu.id = 'mnd-segments-sub-' + item.id.replace(/\D/g, '');
			}
			btn.setAttribute('aria-controls', submenu.id);

			btn.addEventListener('click', function () {
				var open = !item.classList.contains('is-open');
				closeAll(item);
				setOpen(item, open);
			});

			// S myší se seznam zavře po odjetí z dlaždice (stejně jako při najetí).
			item.addEventListener('mouseleave', function () {
				if (finePointer.matches) {
					setOpen(item, false);
				}
			});
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}
			var open = nav.querySelector('.mnd-segments__item.is-open');
			if (open) {
				setOpen(open, false);
				var btn = open.querySelector('.mnd-segments__toggle, .mnd-segments__trigger');
				if (btn) {
					btn.focus();
				}
			}
		});

		document.addEventListener('click', function (e) {
			if (!nav.contains(e.target)) {
				closeAll(null);
			}
		});
	}

	function init() {
		document.querySelectorAll('[data-mnd-carousel]').forEach(initCarousel);
		document.querySelectorAll('.mnd-segments').forEach(initSegments);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
