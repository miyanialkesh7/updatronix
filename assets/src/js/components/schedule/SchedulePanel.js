/**
 * Schedule tab — background update window (discovery + eligible auto-updates) and delay preferences.
 */

import { memo, useMemo } from '@wordpress/element';
import {
	Notice,
	CheckboxControl,
	Button,
	SelectControl,
	Icon,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { update as iconUpdate, calendar as iconDelay } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';

const SCHED_FALLBACK = {
	update_check: {
		recurrence: '',
		time: '03:00',
	},
	delay_updates: {
		enabled: false,
		delay_value: 0,
	},
};

/**
 * Split H:mm into picker parts.
 *
 * @param {string} hi Canonical site wall time.
 * @return {{hours: number, minutes: number}} Hour and minute parts (24h).
 */
function hiToParts(hi) {
	const normalized = /^([01]?[0-9]|2[0-3]):([0-5][0-9])$/.test(hi)
		? hi
		: '03:00';
	const parts = normalized.split(':');
	const hours = Math.min(23, Math.max(0, parseInt(parts[0], 10) || 0));
	const minutes = Math.min(59, Math.max(0, parseInt(parts[1], 10) || 0));

	return { hours, minutes };
}

/**
 * Serialize hour/minute parts to H:i strings.
 *
 * @param {{hours: number, minutes: number}} v Value.
 * @return {string} Two-digit hour and minute separated by a colon.
 */
function partsToHi(v) {
	const h = Math.min(23, Math.max(0, v.hours ?? 0));
	const m = Math.min(59, Math.max(0, v.minutes ?? 0));
	return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/**
 * Schedule tab body — background update recurrence and delay preferences.
 *
 * @param {Object}   props                         Component props.
 * @param {Object}   props.settings                Current settings including `schedule`.
 * @param {Function} props.setSettings             Setter.
 * @param {Function} props.saveSettings            Async save.
 * @param {boolean}  props.saving                  Saving state.
 * @param {Object}   props.scheduleMeta            Labels + next run diagnostics.
 * @param {Object}   props.wpConfigConstants       Localized constant map (same payload as Auto-updates).
 * @param {Function} props.onDismissConstantNotice Dismiss handler for dismissible constant notices.
 * @return {JSX.Element} JSX.
 */
export const SchedulePanel = memo(function SchedulePanel({
	settings,
	setSettings,
	saveSettings,
	saving,
	scheduleMeta,
	wpConfigConstants,
	onDismissConstantNotice,
}) {
	const schedule = settings.schedule ?? SCHED_FALLBACK;

	const recurrence = schedule.update_check.recurrence ?? '';
	const showClock = recurrence === 'daily' || recurrence === 'twicedaily';

	const intervalOptions = useMemo(() => {
		const fallback = [];

		const fromServer =
			scheduleMeta.cron_schedule_labels?.map(({ slug, label }) => ({
				label,
				value: slug,
			})) ?? fallback;

		return [
			{
				label: __('Use WordPress default schedule', 'updatronix'),
				value: '',
			},
			...fromServer,
		];
	}, [scheduleMeta.cron_schedule_labels]);

	const timeParts = hiToParts(schedule.update_check.time ?? '');

	const scheduleDriver = scheduleMeta.schedule_driver ?? 'wordpress';

	let nextScheduledCopy;
	if (
		scheduleMeta.update_check_next_scheduled !== false &&
		scheduleMeta.update_check_next_human &&
		scheduleDriver === 'updatronix'
	) {
		nextScheduledCopy = sprintf(
			/* translators: %s: localized date/time of the next plugin-scheduled background update run */
			__('Next background update run: %s', 'updatronix'),
			scheduleMeta.update_check_next_human
		);
	} else if (scheduleDriver === 'wordpress') {
		nextScheduledCopy = __(
			'WordPress uses its own cron schedule for update checks and eligible automatic updates.',
			'updatronix'
		);
	} else {
		nextScheduledCopy = __(
			'The next run time will appear shortly after you save.',
			'updatronix'
		);
	}

	return (
		<div className="updatronix-settings-form updatronix-schedule-panel">
			<h2 className="updatronix-panel-title">
				{__('Schedule', 'updatronix')}
			</h2>
			<Text variant="muted">
				{__(
					'Choose when this site runs background update work and set delay preferences for automatic updates.',
					'updatronix'
				)}
			</Text>

			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={iconUpdate} size={24} />
					{__('Background update schedule', 'updatronix')}
				</h3>
				<Text variant="muted" as="p">
					{__(
						'A single schedule controls when WordPress checks for updates and may apply eligible automatic updates. Pick a Core WP-Cron interval and optional time, or leave WordPress default so Core keeps its own cron.',
						'updatronix'
					)}
				</Text>
				<ConstantNotices
					constants={wpConfigConstants}
					sections={['schedule']}
					dismissibleOnly
					dismissed={settings.dismissed_constants ?? []}
					onDismiss={onDismissConstantNotice}
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Update interval', 'updatronix')}
					help={__(
						'Hourly, twice daily, and daily match WordPress Core recurrence names. WordPress default clears this plugin schedule.',
						'updatronix'
					)}
					value={recurrence}
					options={intervalOptions}
					onChange={(value) =>
						setSettings((prev) => {
							const ps = prev.schedule ?? SCHED_FALLBACK;
							return {
								...prev,
								schedule: {
									...ps,
									update_check: {
										...ps.update_check,
										recurrence: value,
										time:
											value === 'hourly'
												? ''
												: ps.update_check.time ||
													'03:00',
									},
								},
							};
						})
					}
				/>
				{showClock && (
					<fieldset className="updatronix-schedule-time">
						<legend className="updatronix-schedule-time__legend">
							{__('Time of check', 'updatronix')}
						</legend>
						<div className="updatronix-schedule-time__row">
							<NumberControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className="updatronix-schedule-time__part"
								label={__('Hour', 'updatronix')}
								min={0}
								max={23}
								step={1}
								value={timeParts.hours}
								onChange={(val) => {
									const n = Number(val);
									const hours = Number.isFinite(n)
										? Math.min(23, Math.max(0, n))
										: timeParts.hours;
									setSettings((prev) => {
										const ps =
											prev.schedule ?? SCHED_FALLBACK;
										const { minutes } = hiToParts(
											ps.update_check.time ?? ''
										);
										return {
											...prev,
											schedule: {
												...ps,
												update_check: {
													...ps.update_check,
													time: partsToHi({
														hours,
														minutes,
													}),
												},
											},
										};
									});
								}}
							/>
							<span
								className="updatronix-schedule-time__sep"
								aria-hidden="true"
							>
								:
							</span>
							<NumberControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className="updatronix-schedule-time__part"
								label={__('Minute', 'updatronix')}
								min={0}
								max={59}
								step={1}
								value={timeParts.minutes}
								onChange={(val) => {
									const n = Number(val);
									const minutes = Number.isFinite(n)
										? Math.min(59, Math.max(0, n))
										: timeParts.minutes;
									setSettings((prev) => {
										const ps =
											prev.schedule ?? SCHED_FALLBACK;
										const { hours } = hiToParts(
											ps.update_check.time ?? ''
										);
										return {
											...prev,
											schedule: {
												...ps,
												update_check: {
													...ps.update_check,
													time: partsToHi({
														hours,
														minutes,
													}),
												},
											},
										};
									});
								}}
							/>
						</div>
						<Text
							variant="muted"
							as="p"
							className="updatronix-schedule-time-help"
						>
							{__(
								'Uses your site timezone setting in WordPress.',
								'updatronix'
							)}
						</Text>
					</fieldset>
				)}
				<Notice status="info" isDismissible={false}>
					{nextScheduledCopy}
				</Notice>
			</div>

			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={iconDelay} size={24} />
					{__('Delay updates', 'updatronix')}
				</h3>
				<Text variant="muted" as="p">
					{__(
						'Background automatic updates can wait until each release has been offered for a number of full days. Updatronix tracks maturity per update (core, plugin, theme, or translation). Deferred installs are recorded in Update logs.',
						'updatronix'
					)}
				</Text>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={__('Delay updates', 'updatronix')}
					help={__(
						'When enabled, background automatic installs wait until each offer has soaked for the duration you set (counted from first detection).',
						'updatronix'
					)}
					checked={schedule.delay_updates.enabled}
					onChange={(checked) =>
						setSettings((prev) => {
							const ps = prev.schedule ?? SCHED_FALLBACK;
							return {
								...prev,
								schedule: {
									...ps,
									delay_updates: {
										enabled: !!checked,
										delay_value: checked
											? Math.max(
													ps.delay_updates
														.delay_value || 1,
													1
												)
											: 0,
									},
								},
							};
						})
					}
				/>
				<fieldset
					disabled={!schedule.delay_updates.enabled}
					className="updatronix-settings-fieldset"
				>
					{schedule.delay_updates.enabled && (
						<NumberControl
							__next40pxDefaultSize
							label={__('Delay duration (days)', 'updatronix')}
							help={__(
								'Minimum full days after an offer is first detected before it may install via the automatic updater.',
								'updatronix'
							)}
							min={1}
							max={365}
							value={Math.max(
								1,
								Math.min(
									365,
									schedule.delay_updates.delay_value || 1
								)
							)}
							onChange={(val) =>
								setSettings((prev) => {
									const ps = prev.schedule ?? SCHED_FALLBACK;
									const n = Number(val);
									const bounded = Number.isFinite(n)
										? Math.max(1, Math.min(365, n))
										: 7;
									return {
										...prev,
										schedule: {
											...ps,
											delay_updates: {
												enabled:
													ps.delay_updates.enabled,
												delay_value: bounded,
											},
										},
									};
								})
							}
						/>
					)}
				</fieldset>
				{schedule.delay_updates.enabled && (
					<Text variant="muted" as="p">
						{__(
							'On WordPress Updates, Plugins, and Themes screens, the automatic update countdown shows when the next background check may run — not exactly when each delayed item will install. Maturity is per offer; deferrals appear in Update logs.',
							'updatronix'
						)}
					</Text>
				)}
			</div>

			<div className="updatronix-actions">
				<Button
					variant="primary"
					onClick={saveSettings}
					isBusy={saving}
					disabled={saving}
				>
					{__('Save schedule', 'updatronix')}
				</Button>
			</div>
		</div>
	);
});
