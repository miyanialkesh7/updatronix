/**
 * Registry for Pro tab panel renderers.
 *
 * Each entry lazily imports a React component and renders it into the
 * corresponding mount point in the DOM. The mount point div is rendered by
 * Free's `SettingsPage` in the `default` case of `renderTabPanel`.
 *
 * @module updatronix-pro
 */

const proTabPanels = {
	/**
	 * Render the Updatronix 3000 panel.
	 *
	 * Mounts into `#updatronix-pro-tab-updatronix-3000`.
	 *
	 * @return {Promise<void>}
	 */
	'updatronix-3000': async () => {
		const mountId = 'updatronix-pro-tab-updatronix-3000';
		const el = document.getElementById(mountId);
		if (!el) {
			return;
		}

		const { default: Updatronix3000Panel } =
			await import('./components/Updatronix3000Panel');
		const { createRoot } = await import('@wordpress/element');

		createRoot(el).render(<Updatronix3000Panel />);
	},
};

export default proTabPanels;
