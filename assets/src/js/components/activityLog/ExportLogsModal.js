/**
 * Modal surface for POST /updatronix/v1/logs/export — plain-text preview + chunked fetch loop.
 */

import { useCallback, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Modal,
	Button,
	ToggleControl,
	CheckboxControl,
	TextareaControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Clone view + coerce categorical filters so REST receives canonical stored keys (not translated labels).
 *
 * @param {Object} view DataViews view snapshot.
 * @param {Array}  logs Raw logs from REST (for resolving display-name filters).
 * @return {Object} Deep-cloned view safe for POST export.
 */
export function normalizeViewForExport(view, logs = []) {
	const clone =
		view && typeof view === 'object'
			? JSON.parse(JSON.stringify(view))
			: { filters: [], search: '', sort: {} };

	if (!Array.isArray(clone.filters)) {
		clone.filters = [];
	}

	const bulkLabel = __('Bulk action', 'updatronix');
	const singleLabel = __('Single action', 'updatronix');

	const normalizeScalarRunType = (raw) => {
		const s = String(raw ?? '').trim();
		if (!s) {
			return s;
		}
		const lower = s.toLowerCase();
		if (lower === 'bulk' || lower === 'single') {
			return lower;
		}
		if (s === bulkLabel || lower === String(bulkLabel).toLowerCase()) {
			return 'bulk';
		}
		if (s === singleLabel || lower === String(singleLabel).toLowerCase()) {
			return 'single';
		}

		return sanitizeExportKey(s);
	};

	const normalizeTriggeredScalar = (raw) => {
		const lower = String(raw ?? '')
			.trim()
			.toLowerCase();
		if (['manual', 'automatic', 'upload'].includes(lower)) {
			return lower;
		}

		return sanitizeExportKey(raw);
	};

	const normalizeUserScalar = (raw) => {
		const key = String(raw ?? '')
			.trim()
			.toLowerCase();
		if (key === 'system') {
			return 'system';
		}

		const numeric = Number.parseInt(String(raw ?? '').trim(), 10);
		if (!Number.isNaN(numeric) && numeric > 0) {
			return numeric;
		}

		const row = logs.find(
			(log) =>
				String(log.performed_by_display ?? '') ===
				String(raw ?? '').trim()
		);
		if (row && String(row.performed_by ?? '') === 'system') {
			return 'system';
		}
		if (row && Number(row.user_id) > 0) {
			return Number(row.user_id);
		}

		return raw;
	};

	clone.filters = clone.filters.map((f) => {
		if (!f || typeof f !== 'object') {
			return f;
		}
		const field = String(f.field ?? '');
		const out = { ...f };

		if (field === 'runType') {
			const val = out.value;
			if (Array.isArray(val)) {
				out.value = val.map(normalizeScalarRunType);
			} else {
				out.value = normalizeScalarRunType(val);
			}
		}

		if (field === 'triggeredBy') {
			const val = out.value;
			if (Array.isArray(val)) {
				out.value = val.map(normalizeTriggeredScalar);
			} else {
				out.value = normalizeTriggeredScalar(val);
			}
		}

		if (field === 'user') {
			out.value = normalizeUserScalar(out.value);
		}

		return out;
	});

	return clone;
}

/**
 * Normalise categorical tokens for strict REST allowlists.
 *
 * @param {unknown} raw Filter value fragment.
 * @return {string} Normalised lowercase token.
 */
function sanitizeExportKey(raw) {
	return String(raw ?? '')
		.trim()
		.toLowerCase()
		.replace(/[^a-z0-9_-]/g, '');
}

/**
 * Active dimension summaries for the modal (React text nodes only).
 *
 * @param {Object} view Normalised export view.
 * @return {{ dimensions: Array<{ key: string, label: string, text: string }>, sortLine: { label: string, text: string } }} Dimension rows and the locked sort row.
 */
function buildFilterSummaryParts(view) {
	const dimensions = [];

	if (view.search && String(view.search).trim() !== '') {
		dimensions.push({
			key: 'search',
			label: __('Search', 'updatronix'),
			text: `"${String(view.search)}"`,
		});
	}

	const fieldLabels = {
		category: __('Category', 'updatronix'),
		actionType: __('Action type', 'updatronix'),
		status: __('Status', 'updatronix'),
		triggeredBy: __('Triggered by', 'updatronix'),
		runType: __('Run type', 'updatronix'),
		user: __('User', 'updatronix'),
		date: __('Date', 'updatronix'),
	};

	const describeValues = (values) =>
		Array.isArray(values)
			? values.map((v) => String(v)).join(', ')
			: String(values ?? '');

	for (const f of view.filters ?? []) {
		if (!f || typeof f !== 'object') {
			continue;
		}
		const field = String(f.field ?? '');
		const label = fieldLabels[field];
		if (!label) {
			continue;
		}

		let text = '';
		const op = String(f.operator ?? '');
		const val = f.value;

		if (field === 'date') {
			text = `${op}: ${describeValues(val)}`;
		} else {
			text = describeValues(val);
		}

		dimensions.push({
			key: `${field}-${op}-${JSON.stringify(val)}`,
			label,
			text,
		});
	}

	const sortField = view.sort?.field ?? 'date';
	const sortDir = view.sort?.direction ?? 'desc';
	const sortLabel =
		sortField === 'date' ? __('Date', 'updatronix') : String(sortField);

	const dirLabel =
		sortDir === 'asc'
			? __('oldest first', 'updatronix')
			: __('newest first', 'updatronix');

	const sortLine = {
		label: __('Sort', 'updatronix'),
		text: `${sortLabel} (${dirLabel})`,
	};

	return { dimensions, sortLine };
}

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
	const [cols, setCols] = useState({
		date: false,
		user: false,
		trigger_type: false,
		run_context: false,
	});
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
		() => buildFilterSummaryParts(normalizedView),
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
				/** @type {{ cursor?: string, view?: Object, merge?: boolean, columns?: Object }} */
				const payload = cursor
					? {
							cursor,
							view: normalizedView,
							merge,
							columns: cols,
						}
					: {
							view: normalizedView,
							merge,
							columns: cols,
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
	}, [cols, mapExportError, merge, normalizedView, resetOutput]);

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
			<p className="updatronix-export-modal__sort">
				<strong>{summaryParts.sortLine.label}:</strong>{' '}
				{summaryParts.sortLine.text}
			</p>

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

			<fieldset className="updatronix-export-modal__segments">
				<legend>
					{__(
						'Optional details to include in each line',
						'updatronix'
					)}
				</legend>
				<CheckboxControl
					label={__('Include the date', 'updatronix')}
					checked={cols.date}
					onChange={(v) => setCols((s) => ({ ...s, date: !!v }))}
					disabled={busy}
					__nextHasNoMarginBottom
				/>
				<CheckboxControl
					label={__('Include the user', 'updatronix')}
					checked={cols.user}
					onChange={(v) => setCols((s) => ({ ...s, user: !!v }))}
					disabled={busy}
					__nextHasNoMarginBottom
				/>
				<CheckboxControl
					label={__('Include the trigger type', 'updatronix')}
					checked={cols.trigger_type}
					onChange={(v) =>
						setCols((s) => ({ ...s, trigger_type: !!v }))
					}
					disabled={busy}
					__nextHasNoMarginBottom
				/>
				<CheckboxControl
					label={__('Include the run type', 'updatronix')}
					checked={cols.run_context}
					onChange={(v) =>
						setCols((s) => ({ ...s, run_context: !!v }))
					}
					disabled={busy}
					__nextHasNoMarginBottom
				/>
			</fieldset>

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
