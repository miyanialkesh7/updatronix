/**
 * Modal surface for POST /updatronix/v1/logs/export — plain-text preview + chunked fetch loop.
 */

import { useCallback, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Modal,
	Button,
	ToggleControl,
	TextareaControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeViewForExport, summarizeView } from './logFilters';

/**
 * Export modal entrypoint.
 *
 * @param {Object}                                 props                  Props.
 * @param {boolean}                                props.isOpen           Visibility flag.
 * @param {Function}                               props.onClose          Parent close handler (also restores focus target).
 * @param {Object}                                 props.view             Live DataViews view.
 * @param {Array<Object>}                          props.logs             Logs from REST for resolving filter labels → IDs.
 * @param {import('react').RefObject<HTMLElement>} props.exportTriggerRef Focus target after close.
 * @return {JSX.Element|null} Modal subtree while `isOpen` is true.
 */
export function ExportLogsModal({
	isOpen,
	onClose,
	view,
	logs,
	exportTriggerRef,
}) {
	const [merge, setMerge] = useState(true);
	const [busy, setBusy] = useState(false);
	const [body, setBody] = useState('');
	const [notice, setNotice] = useState(null);
	const [liveRegion, setLiveRegion] = useState('');
	const [generatedStamp, setGeneratedStamp] = useState('');

	const normalizedView = useMemo(
		() => normalizeViewForExport(view, logs),
		[view, logs]
	);

	const summaryParts = useMemo(
		() => summarizeView(normalizedView),
		[normalizedView]
	);

	const resetOutput = useCallback(() => {
		setBody('');
		setNotice(null);
		setLiveRegion('');
		setGeneratedStamp('');
	}, []);

	const handleClose = useCallback(() => {
		resetOutput();
		onClose?.();
		requestAnimationFrame(() => {
			const node =
				exportTriggerRef?.current?.querySelector?.('button') ??
				exportTriggerRef?.current;
			if (node && typeof node.focus === 'function') {
				node.focus();
			}
		});
	}, [exportTriggerRef, onClose, resetOutput]);

	const mapExportError = useCallback((error) => {
		const code =
			error && typeof error === 'object' && 'code' in error
				? String(error.code)
				: '';

		if (code === 'rate_limited') {
			return __(
				'Too many exports started recently. Wait a minute, and then try again.',
				'updatronix'
			);
		}

		if (code === 'cursor_expired') {
			return __(
				'This export session has expired. Start a new export.',
				'updatronix'
			);
		}

		return __(
			'The export could not be generated. Try again, or adjust your filters and try again.',
			'updatronix'
		);
	}, []);

	const runExport = useCallback(async () => {
		resetOutput();
		setBusy(true);

		let accumulated = '';
		let cursor = '';

		try {
			while (true) {
				/** @type {{ cursor?: string, view?: Object, merge?: boolean }} */
				const payload = cursor
					? {
							cursor,
							view: normalizedView,
							merge,
						}
					: {
							view: normalizedView,
							merge,
						};

				const response = await apiFetch({
					path: 'updatronix/v1/logs/export',
					method: 'POST',
					data: payload,
				});

				const chunk =
					response && typeof response.body === 'string'
						? response.body
						: '';

				if (accumulated !== '' && chunk !== '') {
					accumulated += `\n${chunk}`;
				} else {
					accumulated += chunk;
				}

				const next =
					response && typeof response.next_cursor === 'string'
						? response.next_cursor
						: '';

				if (!next) {
					const metaTrunc =
						response && response.truncated === true
							? response
							: null;
					setBody(accumulated);
					const ga = response?.meta?.generated_at;
					const stamp =
						ga !== undefined && ga !== null
							? String(ga)
							: String(Date.now());
					setGeneratedStamp(stamp);

					if (
						metaTrunc &&
						typeof metaTrunc.truncated_included === 'number' &&
						typeof metaTrunc.truncated_total === 'number'
					) {
						setNotice({
							status: 'warning',
							message: sprintf(
								/* translators: 1: Rows included in export. 2: Rows matched before truncation. */
								__(
									'The export was truncated to %1$d of %2$d rows. Narrow your filters to include the rest.',
									'updatronix'
								),
								metaTrunc.truncated_included,
								metaTrunc.truncated_total
							),
						});
					} else if (
						accumulated.trim() === '' &&
						!(metaTrunc && metaTrunc.truncated === true)
					) {
						setNotice({
							status: 'info',
							message: __(
								'No logs match the current filters. The export is empty.',
								'updatronix'
							),
						});
					} else {
						setLiveRegion(
							__(
								'Export ready. Select all and copy from the export output below.',
								'updatronix'
							)
						);
					}

					break;
				}

				cursor = next;
			}
		} catch (error) {
			setNotice({
				status: 'error',
				message: mapExportError(error),
			});
		} finally {
			setBusy(false);
		}
	}, [mapExportError, merge, normalizedView, resetOutput]);

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			className="updatronix-export-modal"
			title={__('Export update logs', 'updatronix')}
			onRequestClose={handleClose}
			shouldCloseOnClickOutside={false}
			focusOnMount="firstContentElement"
			aria-describedby="updatronix-export-modal-desc"
		>
			<p id="updatronix-export-modal-desc">
				{__(
					'Generate a plain-text summary of the logs that match your current filters and sort. Dimensions without a filter include all values.',
					'updatronix'
				)}
			</p>

			<p>
				<strong>{__('Filters applied', 'updatronix')}</strong>
			</p>
			{summaryParts.dimensions.length === 0 ? (
				<p>
					{__(
						'No filters applied — all logs in the current view are included.',
						'updatronix'
					)}
				</p>
			) : (
				<ul className="updatronix-export-modal__filters">
					{summaryParts.dimensions.map(({ key, label, text }) => (
						<li key={key}>
							<strong>{label}:</strong> {text}
						</li>
					))}
				</ul>
			)}
			{merge ? null : (
				<p className="updatronix-export-modal__sort">
					<strong>{summaryParts.sortLine.label}:</strong>{' '}
					{summaryParts.sortLine.text}
				</p>
			)}

			<ToggleControl
				label={__('Merge logs for the same item', 'updatronix')}
				help={__(
					'Combine repeated updates of the same plugin, theme, core release, or translation into a single line with the earliest and latest versions.',
					'updatronix'
				)}
				checked={merge}
				onChange={setMerge}
				disabled={busy}
				__nextHasNoMarginBottom
			/>

			<div className="updatronix-export-modal__actions">
				<Button
					variant="primary"
					onClick={runExport}
					isBusy={busy}
					disabled={busy}
					aria-busy={busy}
				>
					{busy
						? __('Generating the export…', 'updatronix')
						: __('Generate export', 'updatronix')}
				</Button>
				<Button variant="secondary" onClick={handleClose}>
					{__('Close', 'updatronix')}
				</Button>
			</div>

			{notice ? (
				<Notice status={notice.status} isDismissible={false}>
					{notice.message}
				</Notice>
			) : null}

			<div
				aria-live="polite"
				className="screen-reader-text"
				key={generatedStamp}
			>
				{liveRegion}
			</div>

			{notice?.status === 'info' ? null : (
				<TextareaControl
					className="updatronix-export-modal__output"
					label={__('Export output', 'updatronix')}
					help={__(
						'Select all and copy this report to save it. The export expires after 15 minutes.',
						'updatronix'
					)}
					value={body}
					readOnly
					onChange={() => {}}
					rows={14}
				/>
			)}
		</Modal>
	);
}
