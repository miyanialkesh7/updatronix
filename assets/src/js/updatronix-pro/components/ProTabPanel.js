import { useEffect } from '@wordpress/element';

/**
 * Pro tab panel wrapper component.
 *
 * Dynamically imports the Pro tab registry and invokes the render function
 * for the given tab slug. This is a proper React component so that hooks
 * (useEffect) are called unconditionally, respecting React's Rules of Hooks.
 *
 * @param {{slug: string}} props      Component props.
 * @param {string}         props.slug The tab slug to render.
 * @return {JSX.Element} Mount-point div for the Pro tab content.
 */
export default function ProTabPanel({ slug }) {
	useEffect(() => {
		if (!window.updatronixSettings?.isPro) {
			return;
		}

		import('../index.js').then((mod) => {
			const panels = mod.default;
			panels[slug]?.();
		});
	}, [slug]);

	return (
		<div
			id={`updatronix-pro-tab-${slug}`}
			className="updatronix-pro-tab-mount"
		/>
	);
}
