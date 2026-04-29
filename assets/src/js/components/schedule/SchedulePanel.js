/**
 * Schedule panel — empty shell for the Schedule tab.
 *
 * Sibling scheduling tickets (`update-check-schedule`, `delayed-auto-updates`,
 * `auto-update-queueing`) mount their controls inside this panel. Until then,
 * the panel renders a heading, a muted intro, and a non-dismissible
 * informational notice.
 *
 * @since 1.1.0
 */

import { memo } from '@wordpress/element';
import {
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Render the Schedule tab body.
 *
 * @return {JSX.Element} The Schedule panel.
 */
export const SchedulePanel = memo(function SchedulePanel() {
	return (
		<div className="updatronix-schedule-panel">
			<h2 className="updatronix-panel-title">
				{__('Schedule', 'updatronix')}
			</h2>
			<Text variant="muted">
				{__(
					'Choose when Updatronix checks for available updates and how it applies automatic updates.',
					'updatronix'
				)}
			</Text>
			<Notice status="info" isDismissible={false}>
				{__(
					'Scheduling controls will appear here once the Schedule feature is configured.',
					'updatronix'
				)}
			</Notice>
		</div>
	);
});
