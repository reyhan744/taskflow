/**
 * TaskFlow – i18n helper.
 *
 * The translated dictionary comes from PHP (TaskFlow_I18n::strings()) via
 * wp_localize-style injection, so the SPA speaks the site language without
 * any client-side translation files.
 */
(function () {
	'use strict';

	var boot = window.TaskFlowBoot || {};
	var strings = boot.strings || {};

	/**
	 * Translate a key with optional sprintf-style replacements.
	 *
	 * @param {string} key      Dictionary key.
	 * @param {Array}  [args]   Replacement values (%1$s, %2$s, ...).
	 * @return {string}
	 */
	function t(key, args) {
		var text = Object.prototype.hasOwnProperty.call(strings, key) ? strings[key] : key;

		if (args && args.length) {
			for (var i = 0; i < args.length; i++) {
				text = text.replace('%' + (i + 1) + '$s', args[i]).replace('%s', args[i]);
			}
		}

		return text;
	}

	/**
	 * Is the current locale Persian-like? Used for number/date formatting.
	 *
	 * @return {boolean}
	 */
	function isFaLocale() {
		return String(boot.locale || '').toLowerCase().indexOf('fa') === 0;
	}

	window.TaskFlowI18n = { t: t, isFaLocale: isFaLocale };
})();
