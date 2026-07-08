/**
 * Updatronix 3000 panel component.
 *
 * Placeholder React component for the Updatronix 3000 Pro tab.
 * Gated behind `updatronixSettings.isPro` in the tab registry.
 *
 * @module updatronix-pro
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Button } from '@wordpress/components';
import { update } from '@wordpress/icons';

/**
 * Updatronix 3000 panel component with a counter button.
 *
 * @return {JSX.Element} The panel content.
 */
export default function Updatronix3000Panel() {
	const [count, setCount] = useState(0);

	return (
		<Card>
			<CardHeader>
				<h2>{__('Updatronix 3000', 'updatronix-pro')}</h2>
			</CardHeader>
			<CardBody>
				<p>
					{__(
						'Welcome to the Updatronix 3000 tab. This is a React-powered tab managed entirely by Updatronix Pro.',
						'updatronix-pro'
					)}
				</p>
				<Button
					variant="primary"
					icon={update}
					onClick={() => setCount((prev) => prev + 1)}
				>
					{__('Click me', 'updatronix-pro')}
				</Button>
				{count > 0 && (
					<p>
						{sprintf(
							/* translators: %d: number of times the button has been clicked */
							__('Button clicked %d time(s).', 'updatronix-pro'),
							count
						)}
					</p>
				)}
			</CardBody>
		</Card>
	);
}
