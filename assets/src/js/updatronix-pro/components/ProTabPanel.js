import { useEffect, useRef } from '@wordpress/element';

/**
 * Slug-agnostic mount point for a Pro admin tab panel.
 *
 * Reads the renderer from the global Pro panel registry
 * (window.updatronixProPanelRegistry by default; the global name is provided
 * by PHP via window.updatronixSettings.proPanelRegistryGlobal). The renderer
 * signature is (mountEl: HTMLElement) => (() => void) | undefined.
 *
 * The renderer is invoked only while the tab is active, and any cleanup
 * function it returns is called on deactivation/unmount to avoid leaking
 * React roots (React 18 StrictMode double-invokes effects in dev).
 *
 * @param {{slug: string, isActive: boolean}} props Component props.
 * @return {JSX.Element} Mount-point div for the Pro tab content.
 */
export default function ProTabPanel({ slug, isActive }) {
	const mountRef = useRef(null);

	useEffect(() => {
		if (!isActive) {
			return undefined;
		}
		if (!window.updatronixSettings?.isPro) {
			return undefined;
		}
		const globalName = window.updatronixSettings.proPanelRegistryGlobal;
		if (typeof globalName !== 'string' || !globalName) {
			return undefined;
		}
		// eslint-disable-next-line no-undef
		const registry = window[globalName];
		if (!registry || typeof registry !== 'object') {
			return undefined;
		}
		const render = registry[slug];
		if (typeof render !== 'function' || !mountRef.current) {
			return undefined;
		}
		const cleanup = render(mountRef.current);

		return () => {
			if (typeof cleanup === 'function') {
				cleanup();
			}
		};
	}, [slug, isActive]);

	return (
		<div
			ref={mountRef}
			id={`updatronix-pro-tab-${slug}`}
			className="updatronix-pro-tab-mount"
		/>
	);
}
