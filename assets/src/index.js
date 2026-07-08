import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import './index.scss';

const updatronixSettings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};

if (updatronixSettings.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(updatronixSettings.nonce));
}

/**
 * Registry for Pro (or other extensions) to mount React components into mount points
 * rendered by Free's dynamic tab system.
 *
 * Free creates this array synchronously at module load time, before any async code.
 * Pro (loaded as a dependency of updatronix-scripts) pushes its mount callbacks
 * synchronously at module load time. After Free's dynamic import resolves and renders
 * the mount points, Free invokes all registered callbacks.
 *
 * @type {Array<() => void>}
 */
if (typeof window !== 'undefined') {
	window.updatronixProMounts = [];
}

/**
 * Render the Updatronix settings page once the DOM is ready.
 *
 * wp-notices is a script dependency so the default store context is available.
 */
domReady(() => {
	const rootEl = document.getElementById('updatronix-settings');
	if (!rootEl || !(rootEl instanceof HTMLElement)) {
		return;
	}

	const root = createRoot(rootEl);
	root.render(null);

	import('./js/pages/SettingsPage')
		.then(({ SettingsPage }) => {
			root.render(<SettingsPage />);

			// Invoke Pro mount callbacks now that all mount points are in the DOM.
			if (Array.isArray(window.updatronixProMounts)) {
				window.updatronixProMounts.forEach((fn) => fn());
			}
		})
		.catch(() => {
			rootEl.textContent = 'Updatronix failed to load.';
		});
});
