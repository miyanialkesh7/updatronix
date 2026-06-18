<?php

/**
 * Plain-text export body rendering (locale-aware).
 *
 * Output is a category-sectioned report. Rows are grouped under `== CORE ==`,
 * `== PLUGINS ==`, `== THEMES ==`, and `== TRANSLATIONS ==` headings in both
 * merge modes. Within each section, lines are ordered by date (most recent
 * activity first), then by action, status, and name as tie-breakers.
 *
 * Each line reads as a short audit sentence (subject → event → detail →
 * detail → when → how → outcome). Column widths are computed once per chunk from
 * the longest value in each field so rows align throughout the report:
 *
 *     WooCommerce          Update      8.0 → 8.2   2026-06-10 09:00, 2026-06-18 14:03   (manual, bulk)   Success
 *
 * The status label matches the admin UI (`Success` / `Error` / `Cancelled`) and
 * is always the last column. A merged line lists every event date separated by
 * commas. Trigger and run context (`manual` / `automatic` / `upload`, `bulk` /
 * `single`) are appended in parentheses only when every row in the line agrees.
 *
 * Merge grouping applies **within each SQL chunk only** (rows passed to {@see render()}),
 * and collapses rows that share entity, action, and status.
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds UTF-8 plain-text fragments for {@see Updatronix_Export::rest_export()}.
 */
final class Updatronix_Export_Body_Builder {
    /**
     * Section order for the report.
     *
     * @var list<string>
     */
    private const CATEGORY_ORDER = ['core', 'plugin', 'theme', 'translation'];

    /**
     * Sort rank per action type (lower sorts first).
     *
     * @var array<string, int>
     */
    private const ACTION_RANK = [
        'update' => 0,
        'same_version' => 1,
        'downgrade' => 2,
        'install' => 3,
        'uninstall' => 4,
        'delete' => 5,
        'failed' => 6,
    ];

    /** Column keys used for per-section width measurement and line assembly. */
    private const ROW_PART_KEYS = ['name', 'action', 'versions', 'dates', 'context', 'status'];

    /** Optional columns omitted from a section when every row value is empty. */
    private const OPTIONAL_ROW_PART_KEYS = ['versions', 'context'];

    /**
     * Append formatted rows respecting merge mode and per-chunk byte cap.
     *
     * @since 1.1.0
     *
     * @param array<int, object>     $rows                Rows from {@see Updatronix_Export_Query_Builder::fetch_rows()}.
     * @param bool                   $merge               Merge rows that share entity, action, and status within this chunk.
     * @param array<string, bool>    $columns             Reserved for backward compatibility; the report layout is fixed and no longer toggled per column.
     * @param string                 $existing_chunk_body Already emitted bytes for this HTTP chunk.
     * @param int                    $max_chunk_bytes     Soft max chunk bytes ({@see Updatronix_Export::MAX_BYTES_PER_CHUNK}).
     * @return array{body: string, rows_emitted: int, merged_lines_added: int, byte_cap_hit: bool}
     */
    public static function render(
        array $rows,
        bool $merge,
        array $columns,
        string $existing_chunk_body,
        int $max_chunk_bytes
    ): array {
        unset($columns);

        switch_to_user_locale(get_current_user_id());

        try {
            $records = self::build_sectioned_records($rows, $merge);

            return self::emit_until_cap($records, $existing_chunk_body, $max_chunk_bytes);
        } finally {
            restore_previous_locale();
        }
    }

    /**
     * User-visible truncation footer (also surfaced via modal Notice).
     *
     * @since 1.1.0
     *
     * @param int $included Rows included in this export attempt.
     * @param int $matched  Rows matched before truncation (estimate OK).
     * @return string Single line without trailing newline.
     */
    public static function truncation_footer(int $included, int $matched): string {
        return sprintf(
            /* translators: 1: Rows included in the export. 2: Rows matched before truncation. */
            __('— Truncated: %1$d of %2$d rows. Narrow your filters to include the rest. —', 'updatronix'),
            $included,
            $matched
        );
    }

    /**
     * Normalise a dynamic field for single-line plain-text output.
     *
     * @since 1.1.0
     *
     * @param string $value Raw field value.
     * @return string
     */
    public static function normalize_field(string $value): string {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = is_string($value) ? $value : '';
        $value = str_replace("\n", ' ', $value);

        return trim($value);
    }

    /**
     * @param array<int, array{line: string, row_span: int}> $records             Lines with row consumption counts.
     * @param string                                          $existing_chunk_body Prefix already counted toward chunk cap.
     * @param int                                             $max_chunk_bytes     Chunk ceiling.
     * @return array{body: string, rows_emitted: int, merged_lines_added: int, byte_cap_hit: bool}
     */
    private static function emit_until_cap(array $records, string $existing_chunk_body, int $max_chunk_bytes): array {
        $body = $existing_chunk_body;
        $rows_emitted = 0;
        $merged_lines_added = 0;
        $byte_cap_hit = false;

        foreach ($records as $rec) {
            $line = $rec['line'];
            $span = (int) $rec['row_span'];
            $sep = ($body === '') ? '' : "\n";
            $candidate = $body . $sep . $line;

            if (strlen($candidate) > $max_chunk_bytes) {
                if ($body === $existing_chunk_body && $existing_chunk_body === '' && strlen($line) > $max_chunk_bytes) {
                    $body = $line;
                    if ($span > 0) {
                        $rows_emitted += $span;
                        $merged_lines_added++;
                    }
                    $byte_cap_hit = true;

                    break;
                }
                $byte_cap_hit = true;

                break;
            }

            $body = $candidate;
            if ($span > 0) {
                $rows_emitted += $span;
                $merged_lines_added++;
            }
        }

        return [
            'body' => $body,
            'rows_emitted' => $rows_emitted,
            'merged_lines_added' => $merged_lines_added,
            'byte_cap_hit' => $byte_cap_hit,
        ];
    }

    /**
     * Build the category-sectioned record list for a chunk.
     *
     * @param array<int, object> $rows  Rows in one SQL chunk.
     * @param bool               $merge Collapse rows sharing entity, action, and status.
     * @return array<int, array{line: string, row_span: int}>
     */
    private static function build_sectioned_records(array $rows, bool $merge): array {
        $buckets = [];
        if ($merge) {
            foreach ($rows as $row) {
                $buckets[self::merge_key($row)][] = $row;
            }
        } else {
            $index = 0;
            foreach ($rows as $row) {
                $buckets['row_' . $index++] = [$row];
            }
        }

        /** @var array<string, array<int, array{sort_ts:int, action_rank:int, status_rank:int, name:string, parts:array{name:string, action:string, versions:string, dates:string, context:string, status:string}, row_span:int}>> $by_category */
        $by_category = ['core' => [], 'plugin' => [], 'theme' => [], 'translation' => []];

        foreach ($buckets as $group) {
            $first = $group[0];
            $lt = sanitize_key((string) ($first->log_type ?? ''));
            if (!isset($by_category[$lt])) {
                continue;
            }

            $by_category[$lt][] = [
                'sort_ts' => self::latest_timestamp($group),
                'action_rank' => self::action_rank(sanitize_key((string) ($first->action_type ?? ''))),
                'status_rank' => self::status_rank((string) ($first->status ?? '')),
                'name' => mb_strtolower(self::real_name($first), 'UTF-8'),
                'parts' => self::build_row_parts($group),
                'row_span' => count($group),
            ];
        }

        $records = [];
        $has_section = false;

        /** @var array<int, array{parts: array{name: string, action: string, versions: string, dates: string, context: string, status: string}}> $all_items */
        $all_items = [];
        foreach (self::CATEGORY_ORDER as $lt) {
            foreach ($by_category[$lt] as $item) {
                $all_items[] = $item;
            }
        }
        $widths = self::column_widths_for_rows($all_items);

        foreach (self::CATEGORY_ORDER as $lt) {
            $items = $by_category[$lt];
            if ($items === []) {
                continue;
            }

            usort(
                $items,
                static function (array $a, array $b): int {
                    // Most recent activity first; action, status, and name break ties.
                    $by_date = $b['sort_ts'] <=> $a['sort_ts'];
                    if ($by_date !== 0) {
                        return $by_date;
                    }

                    return [$a['action_rank'], $a['status_rank'], $a['name']]
                        <=> [$b['action_rank'], $b['status_rank'], $b['name']];
                }
            );

            if ($has_section) {
                $records[] = ['line' => '', 'row_span' => 0];
            }
            $records[] = ['line' => self::section_heading_for($lt), 'row_span' => 0];
            foreach ($items as $item) {
                $records[] = [
                    'line' => self::format_row_parts($item['parts'], $widths),
                    'row_span' => $item['row_span'],
                ];
            }
            $has_section = true;
        }

        return $records;
    }

    /**
     * Build the display parts for one aggregate group (merged bucket or single row).
     *
     * @param array<int, object> $group One or more rows sharing entity, action, and status.
     * @return array{name: string, action: string, versions: string, dates: string, context: string, status: string}
     */
    private static function build_row_parts(array $group): array {
        usort(
            $group,
            static fn (object $a, object $b): int => strcmp((string) ($a->created_at ?? ''), (string) ($b->created_at ?? ''))
        );
        $first = $group[0];
        $last = $group[count($group) - 1];

        return [
            'name' => self::real_name($last),
            'action' => self::action_type_display_label(sanitize_key((string) ($first->action_type ?? ''))),
            'versions' => self::compact_version_plain($group, $first),
            'dates' => self::format_dates_list($group),
            'context' => self::trigger_context_suffix($group),
            'status' => self::status_label((string) ($first->status ?? '')),
        ];
    }

    /**
     * Measure the widest value per column across all data rows in one chunk.
     *
     * Widths are shared by every section so columns stay aligned throughout the
     * report. Uses multibyte length so accented names and translated labels pad
     * correctly. Optional columns (`versions`, `context`) are dropped when every
     * row leaves them empty.
     *
     * @param array<int, array{parts: array{name: string, action: string, versions: string, dates: string, context: string, status: string}}> $items Data rows after sorting (section headings excluded).
     * @return array<string, int> Column key => width in characters (0 means omit optional column).
     */
    private static function column_widths_for_rows(array $items): array {
        $widths = array_fill_keys(self::ROW_PART_KEYS, 0);

        foreach ($items as $item) {
            foreach (self::ROW_PART_KEYS as $key) {
                $length = mb_strlen($item['parts'][$key], 'UTF-8');
                if ($length > $widths[$key]) {
                    $widths[$key] = $length;
                }
            }
        }

        foreach (self::OPTIONAL_ROW_PART_KEYS as $key) {
            if ($widths[$key] === 0) {
                unset($widths[$key]);
            }
        }

        return $widths;
    }

    /**
     * Assemble one aligned line from row parts and precomputed section widths.
     *
     * Status is always the last column. Field order: name → action → versions →
     * dates → context → status.
     *
     * @param array{name: string, action: string, versions: string, dates: string, context: string, status: string} $parts  Row values.
     * @param array<string, int>                                                                                    $widths Section column widths.
     * @return string
     */
    private static function format_row_parts(array $parts, array $widths): string {
        $segments = [];

        foreach (self::ROW_PART_KEYS as $key) {
            if (!isset($widths[$key])) {
                continue;
            }
            $segments[] = self::pad_column($parts[$key], $widths[$key]);
        }

        return implode('  ', $segments);
    }

    /**
     * Pad a value to a minimum column width (multibyte-aware; never truncates).
     *
     * @param string $value Field value.
     * @param int    $width Minimum column width.
     * @return string
     */
    private static function pad_column(string $value, int $width): string {
        $length = mb_strlen($value, 'UTF-8');
        if ($length >= $width) {
            return $value;
        }

        return $value . str_repeat(' ', $width - $length);
    }

    /**
     * Status label matching the admin UI ({@see Updatronix_Settings} and the log filters).
     *
     * @param string $status Raw status value.
     * @return string Localised `Success`, `Error`, or `Cancelled`.
     */
    private static function status_label(string $status): string {
        return match (strtolower(sanitize_key($status))) {
            'error', 'failed', 'errors' => __('Error', 'updatronix'),
            'cancelled' => __('Cancelled', 'updatronix'),
            default => __('Success', 'updatronix'),
        };
    }

    /**
     * Sort rank per status (successes first, failures last).
     *
     * @param string $status Raw status value.
     * @return int
     */
    private static function status_rank(string $status): int {
        return match (strtolower(sanitize_key($status))) {
            'error', 'failed', 'errors' => 2,
            'cancelled' => 1,
            default => 0,
        };
    }

    /**
     * Sort rank per action type.
     *
     * @param string $action Sanitized action type.
     * @return int
     */
    private static function action_rank(string $action): int {
        return self::ACTION_RANK[$action] ?? 99;
    }

    /**
     * Human-readable item name (real name, not slug).
     *
     * @param object $row Representative row.
     * @return string
     */
    private static function real_name(object $row): string {
        $lt = sanitize_key((string) ($row->log_type ?? ''));
        if ($lt === 'core') {
            return 'WordPress';
        }

        $name = trim((string) ($row->item_name ?? ''));
        if ($name !== '') {
            return self::normalize_field($name);
        }

        $slug = trim((string) ($row->item_slug ?? ''));
        if ($slug !== '') {
            return self::normalize_field($slug);
        }

        return '—';
    }

    /**
     * Comma-separated list of each event's date (ascending, de-duplicated),
     * using site date and time preferences.
     *
     * @param array<int, object> $group Rows.
     * @return string e.g. `2026-06-10 09:00, 2026-06-18 14:03` or `—`.
     */
    private static function format_dates_list(array $group): string {
        $stamps = [];
        foreach ($group as $row) {
            $ts = strtotime((string) ($row->created_at ?? ''));
            if ($ts !== false && $ts > 0) {
                $stamps[] = $ts;
            }
        }
        sort($stamps);

        $out = [];
        $seen = [];
        foreach ($stamps as $ts) {
            $formatted = self::format_export_datetime((int) $ts);
            if ($formatted === '' || isset($seen[$formatted])) {
                continue;
            }
            $seen[$formatted] = true;
            $out[] = $formatted;
        }

        return $out === [] ? '—' : implode(', ', $out);
    }

    /**
     * Most recent `created_at` in a group, for date-based section ordering.
     *
     * @param array<int, object> $group Rows.
     * @return int Unix epoch, or 0 when none parse.
     */
    private static function latest_timestamp(array $group): int {
        $latest = 0;
        foreach ($group as $row) {
            $ts = strtotime((string) ($row->created_at ?? ''));
            if ($ts !== false && $ts > $latest) {
                $latest = $ts;
            }
        }

        return $latest;
    }

    /**
     * Trigger and run-context suffix, shown only when every row agrees.
     *
     * @param array<int, object> $group Rows.
     * @return string e.g. `(manual, bulk)` or empty.
     */
    private static function trigger_context_suffix(array $group): string {
        $triggers = [];
        foreach ($group as $row) {
            $triggers[sanitize_key((string) ($row->performed_as ?? ''))] = true;
        }
        unset($triggers['']);

        $trigger = '';
        if (count($triggers) === 1) {
            $trigger = match (array_key_first($triggers)) {
                'manual' => __('manual', 'updatronix'),
                'automatic' => __('automatic', 'updatronix'),
                'upload' => __('upload', 'updatronix'),
                default => '',
            };
        }

        $contexts = [];
        $all_have_context = true;
        foreach ($group as $row) {
            $ctx = sanitize_key((string) ($row->update_context ?? ''));
            if ($ctx === '') {
                $all_have_context = false;
            }
            $contexts[$ctx] = true;
        }

        $context = '';
        if ($all_have_context && count($contexts) === 1) {
            $context = match (array_key_first($contexts)) {
                'bulk' => __('bulk', 'updatronix'),
                'single' => __('single', 'updatronix'),
                default => '',
            };
        }

        $parts = [];
        if ($trigger !== '') {
            $parts[] = $trigger;
        }
        if ($context !== '') {
            $parts[] = $context;
        }

        if ($parts === []) {
            return '';
        }

        return '(' . implode(', ', $parts) . ')';
    }

    /**
     * Localised action label aligned with {@see Updatronix_Settings::enrich_log_for_display()}.
     *
     * @param string $action_type Sanitized action key.
     * @return string
     */
    private static function action_type_display_label(string $action_type): string {
        return match ($action_type) {
            'update' => __('Update', 'updatronix'),
            'downgrade' => __('Rollback', 'updatronix'),
            'install' => __('Install', 'updatronix'),
            'same_version' => __('Reinstall', 'updatronix'),
            'failed' => __('Failed', 'updatronix'),
            'uninstall' => __('Uninstall', 'updatronix'),
            default => $action_type !== '' ? $action_type : '',
        };
    }

    /**
     * Section heading for a category.
     *
     * @param string $lt Sanitized log type.
     * @return string
     */
    private static function section_heading_for(string $lt): string {
        return match ($lt) {
            'core' => sprintf('== %s ==', __('CORE', 'updatronix')),
            'plugin' => sprintf('== %s ==', __('PLUGINS', 'updatronix')),
            'theme' => sprintf('== %s ==', __('THEMES', 'updatronix')),
            'translation' => sprintf('== %s ==', __('TRANSLATIONS', 'updatronix')),
            default => sprintf('== %s ==', mb_strtoupper($lt, 'UTF-8')),
        };
    }

    /**
     * WordPress Reading → date + time preference (Settings → General), site timezone via {@see wp_date()}.
     */
    private static function export_datetime_pattern(): string {
        $df = wp_unslash((string) get_option('date_format', 'Y-m-d'));
        $tf = wp_unslash((string) get_option('time_format', 'H:i'));

        return trim($df . ' ' . $tf);
    }

    /**
     * @param int $ts Unix epoch (validated).
     * @return string Localised formatted timestamp; empty on failure.
     */
    private static function format_export_datetime(int $ts): string {
        if ($ts <= 0) {
            return '';
        }

        return wp_date(self::export_datetime_pattern(), $ts, wp_timezone()) ?: '';
    }

    /**
     * Normalise logged `item_slug` so manual rows (`akismet`) and automatic paths (`akismet/akismet.php`)
     * share one merge bucket.
     *
     * @param string $slug Raw `item_slug` from the logs table (may be folder or plugin-relative path).
     * @param string $lt   Sanitized `log_type`.
     * @return string Empty when input has no usable token.
     */
    private static function normalise_item_slug_token_for_merge(string $slug, string $lt): string {
        $slug = trim(str_replace('\\', '/', $slug));
        if ($slug === '') {
            return '';
        }

        // Plugin updates sometimes log the main PHP file (`dir/plugin.php`). Folder is the canonical slug.
        if ($lt === 'plugin' && str_contains($slug, '/')) {
            $folder = dirname($slug);
            if ($folder !== '.') {
                $slug = $folder;
            }
        }

        return sanitize_key($slug);
    }

    /**
     * Stable merge key: entity, then action type, then status, so a merged line is
     * homogeneous and can be sorted and tagged unambiguously.
     *
     * @param object $row Row object.
     * @return string Non-readable delimiter-separated key.
     */
    private static function merge_key(object $row): string {
        $action = sanitize_key((string) ($row->action_type ?? ''));
        $status = strtolower(sanitize_key((string) ($row->status ?? '')));

        return self::merge_entity_key($row) . "\x00" . $action . "\x00" . $status;
    }

    /**
     * Entity portion of the merge key (per plugin, theme, core release, or translation package).
     *
     * @param object $row Row object.
     * @return string
     */
    private static function merge_entity_key(object $row): string {
        $lt = sanitize_key((string) ($row->log_type ?? ''));

        // Core: manual completion logs `item_slug` as "core"; automatic completion often leaves it empty.
        // One canonical key prevents duplicate merged lines for the same WordPress update.
        if ($lt === 'core') {
            return 'core' . "\x00wordpress-core";
        }

        $slug = trim((string) ($row->item_slug ?? ''));
        if ($slug !== '') {
            $token = self::normalise_item_slug_token_for_merge($slug, $lt);
            if ($token !== '') {
                return $lt . "\x00" . $token;
            }
        }

        if ($lt === 'translation') {
            $name = trim((string) ($row->item_name ?? ''));
            if ($name !== '') {
                return 'translation' . "\x00" . mb_strtolower(self::normalize_field($name), 'UTF-8');
            }

            return $lt . "\x00wordpress-translation";
        }

        // Plugin/theme rows should always carry a slug; fall back to name so we never merge unrelated items under one key.
        $name = trim((string) ($row->item_name ?? ''));
        if ($name !== '') {
            return $lt . "\x00name:" . mb_strtolower(self::normalize_field($name), 'UTF-8');
        }

        return $lt . "\x00_";
    }

    /**
     * Version span without leading `v` (e.g. `8.7.0 → 8.8.1`).
     *
     * @param array<int, object> $group Rows.
     * @param object             $first First chronologically.
     * @return string
     */
    private static function compact_version_plain(array $group, object $first): string {
        $lt = sanitize_key((string) ($first->log_type ?? ''));

        $vb_vals = [];
        $va_vals = [];
        foreach ($group as $row) {
            $b = trim((string) ($row->version_before ?? ''));
            $a = trim((string) ($row->version_after ?? ''));
            if ($b !== '') {
                $vb_vals[] = $b;
            }
            if ($a !== '') {
                $va_vals[] = $a;
            }
        }

        $pick_min = static function (array $vals): string {
            $vals = array_values(array_unique($vals));
            if ($vals === []) {
                return '';
            }
            usort($vals, static fn ($x, $y): int => strcmp((string) $x, (string) $y));

            return $vals[0];
        };

        $pick_max = static function (array $vals): string {
            $vals = array_values(array_unique($vals));
            if ($vals === []) {
                return '';
            }
            usort($vals, static fn ($x, $y): int => strcmp((string) $x, (string) $y));

            return $vals[count($vals) - 1];
        };

        $from = self::normalize_field($pick_min($vb_vals));
        $to = self::normalize_field($pick_max($va_vals));

        if ($lt === 'translation') {
            if ($from !== '' && $to !== '') {
                return $from === $to ? $from : sprintf('%1$s → %2$s', $from, $to);
            }
            if ($to !== '') {
                return $to;
            }

            return $from;
        }

        $rep_action = sanitize_key((string) ($first->action_type ?? ''));
        if ($rep_action === 'same_version') {
            $single = $to !== '' ? $to : $from;

            return $single !== '' ? self::normalize_field($single) : '';
        }

        if ($from !== '' && $to !== '') {
            return sprintf(
                '%1$s → %2$s',
                $from,
                $to
            );
        }

        if ($to !== '') {
            return $to;
        }

        return $from;
    }
}
