<?php

/**
 * Plain-text export body rendering (locale-aware).
 *
 * Merge grouping applies **within each SQL chunk only** (rows passed to {@see render()}).
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
     * Append formatted rows respecting merge mode and per-chunk byte cap.
     *
     * @since 1.1.0
     *
     * @param array<int, object>     $rows               Rows from {@see Updatronix_Export_Query_Builder::fetch_rows()}.
     * @param bool                   $merge              Merge rows that share entity key within this chunk.
     * @param array<string, bool>    $columns            Segment toggles (date, user, trigger_type, run_context).
     * @param string                 $existing_chunk_body Already emitted bytes for this HTTP chunk.
     * @param int                    $max_chunk_bytes    Soft max chunk bytes ({@see Updatronix_Export::MAX_BYTES_PER_CHUNK}).
     * @return array{body: string, rows_emitted: int, merged_lines_added: int, byte_cap_hit: bool}
     */
    public static function render(
        array $rows,
        bool $merge,
        array $columns,
        string $existing_chunk_body,
        int $max_chunk_bytes
    ): array {
        switch_to_user_locale(get_current_user_id());

        try {
            self::batch_cache_users($rows);

            $records = [];
            if ($merge) {
                foreach (self::build_merged_records($rows, $columns) as $rec) {
                    $records[] = $rec;
                }
            } else {
                foreach ($rows as $row) {
                    $records[] = [
                        'line' => self::format_single_line($row, $columns),
                        'row_span' => 1,
                    ];
                }
            }

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
     * @param array<int, object> $rows Log rows.
     * @return void
     */
    private static function batch_cache_users(array $rows): void {
        $ids = [];
        foreach ($rows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            if ($uid > 0) {
                $ids[] = $uid;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids !== []) {
            cache_users($ids);
        }
    }

    /**
     * @param array<int, array{line: string, row_span: int}> $records Lines with row consumption counts.
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
            $span = max(1, (int) $rec['row_span']);
            $sep = ($body === '') ? '' : "\n";
            $candidate = $body . $sep . $line;

            if (strlen($candidate) > $max_chunk_bytes) {
                if ($body === $existing_chunk_body && $existing_chunk_body === '' && strlen($line) > $max_chunk_bytes) {
                    $body = $line;
                    $rows_emitted += $span;
                    $merged_lines_added++;
                    $byte_cap_hit = true;

                    break;
                }
                $byte_cap_hit = true;

                break;
            }

            $body = $candidate;
            $rows_emitted += $span;
            $merged_lines_added++;
        }

        return [
            'body' => $body,
            'rows_emitted' => $rows_emitted,
            'merged_lines_added' => $merged_lines_added,
            'byte_cap_hit' => $byte_cap_hit,
        ];
    }

    /**
     * Group rows by export merge key preserving first-seen order.
     *
     * @param array<int, object>  $rows    Rows.
     * @param array<string, bool> $columns Segment toggles.
     * @return array<int, array{line: string, row_span: int}>
     */
    private static function build_merged_records(array $rows, array $columns): array {
        $buckets = [];
        $order = [];

        foreach ($rows as $row) {
            $key = self::merge_key($row);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [];
                $order[] = $key;
            }
            $buckets[$key][] = $row;
        }

        $out = [];
        foreach ($order as $key) {
            $group = $buckets[$key];
            $out[] = [
                'line' => self::format_merged_line($group, $columns),
                'row_span' => count($group),
            ];
        }

        return $out;
    }

    /**
     * Stable merge key for log rows within a chunk.
     *
     * @param object $row Row object.
     * @return string Non-readable delimiter-separated key.
     */
    private static function merge_key(object $row): string {
        $lt = sanitize_key((string) ($row->log_type ?? ''));
        $slug = trim((string) ($row->item_slug ?? ''));
        if ($slug !== '') {
            return $lt . "\x00" . $slug;
        }
        if ($lt === 'core') {
            return $lt . "\x00wordpress-core";
        }
        if ($lt === 'translation') {
            return $lt . "\x00wordpress-translation";
        }

        return $lt . "\x00";
    }

    /**
     * @param object               $row     Row.
     * @param array<string, bool>  $columns toggles.
     * @return string
     */
    private static function format_single_line(object $row, array $columns): string {
        return self::format_aggregate_line([$row], $columns);
    }

    /**
     * @param array<int, object>  $group   Rows sharing merge key (sorted chronologically).
     * @param array<string, bool> $columns Segment toggles.
     * @return string
     */
    private static function format_merged_line(array $group, array $columns): string {
        usort(
            $group,
            static function (object $a, object $b): int {
                return strcmp((string) ($a->created_at ?? ''), (string) ($b->created_at ?? ''));
            }
        );

        return self::format_aggregate_line($group, $columns);
    }

    /**
     * @param array<int, object>  $group   One or more rows (sorted ASC by created_at).
     * @param array<string, bool> $columns Segment toggles.
     * @return string
     */
    private static function format_aggregate_line(array $group, array $columns): string {
        $first = $group[0];
        $last = $group[count($group) - 1];
        $count = count($group);

        $parts = [];

        if (!empty($columns['date'])) {
            $parts[] = self::segment_date($group, $first, $last);
        }

        $parts[] = self::segment_title_action($last, $count);

        $ver = self::segment_versions($group, $first);
        if ($ver !== '') {
            $parts[] = $ver;
        }

        $trig = self::segment_trigger($group, !empty($columns['trigger_type']));
        if ($trig !== '') {
            $parts[] = $trig;
        }

        $run = self::segment_run_context($group, !empty($columns['run_context']));
        if ($run !== '') {
            $parts[] = $run;
        }

        $usr = self::segment_user($group, !empty($columns['user']));
        if ($usr !== '') {
            $parts[] = $usr;
        }

        return self::normalize_field(implode(' ', array_filter($parts, static fn ($p): bool => $p !== '')));
    }

    /**
     * @param array<int, object> $group Rows.
     * @param object               $first First row chronologically.
     * @param object               $last  Last row chronologically.
     * @return string
     */
    private static function segment_date(array $group, object $first, object $last): string {
        $ts_min = strtotime((string) ($first->created_at ?? ''));
        $ts_max = strtotime((string) ($last->created_at ?? ''));
        if ($ts_min <= 0) {
            $ts_min = $ts_max;
        }
        if ($ts_max <= 0) {
            $ts_max = $ts_min;
        }

        $z = wp_timezone();
        $fmin = wp_date('Y-m-d H:i', $ts_min, $z);
        $fmax = wp_date('Y-m-d H:i', $ts_max, $z);

        if ($fmin === $fmax || count($group) === 1) {
            return sprintf('[%s]', $fmin);
        }

        return sprintf(
            /* translators: 1: Start datetime. 2: End datetime. */
            __('[%1$s → %2$s]', 'updatronix'),
            $fmin,
            $fmax
        );
    }

    /**
     * Title: localized category prefix + item name + action label (+ merged count).
     *
     * @param object $representative Representative row (typically chronologically last).
     * @param int    $group_count    Rows merged into this line.
     * @return string
     */
    private static function segment_title_action(object $representative, int $group_count): string {
        $name = self::normalize_field((string) ($representative->item_name ?? ''));
        if ($name === '') {
            /* translators: Fallback item label when name missing */
            $name = __('Item', 'updatronix');
        }

        $action_raw = sanitize_key((string) ($representative->action_type ?? ''));
        $action_label = self::action_label($action_raw);

        $base = $action_label !== '' ? $name . ' — ' . $action_label : $name;

        $lt = sanitize_key((string) ($representative->log_type ?? ''));
        $prefix = self::category_prefix($lt);
        $title = $prefix !== '' ? $prefix . ' ' . $base : $base;

        if ($group_count > 1) {
            /* translators: %d: Number of consolidated updates */
            $suffix = _n('(%d update)', '(%d updates)', $group_count, 'updatronix');

            return $title . ' ' . sprintf($suffix, $group_count);
        }

        return $title;
    }

    /**
     * @param string $log_type Raw log_type key.
     * @return string
     */
    private static function category_prefix(string $log_type): string {
        return match ($log_type) {
            'core' => __('Core:', 'updatronix'),
            'plugin' => __('Plugin:', 'updatronix'),
            'theme' => __('Theme:', 'updatronix'),
            'translation' => __('Translation:', 'updatronix'),
            default => '',
        };
    }

    /**
     * @param string $action_type Sanitized action_type key.
     * @return string
     */
    private static function action_label(string $action_type): string {
        return match ($action_type) {
            'update' => __('Update', 'updatronix'),
            'downgrade' => __('Rollback', 'updatronix'),
            'install' => __('Install', 'updatronix'),
            'same_version' => __('Reinstall', 'updatronix'),
            'failed' => __('Failed', 'updatronix'),
            'uninstall' => __('Uninstall', 'updatronix'),
            'delete' => __('Delete', 'updatronix'),
            default => '',
        };
    }

    /**
     * Version arrow segment mirroring ActivityLog plain-language rules (simplified).
     *
     * @param array<int, object> $group Rows.
     * @param object               $first First chronologically.
     * @return string
     */
    private static function segment_versions(array $group, object $first): string {
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
            usort($vals, static fn ($x, $y): int => strcmp($x, $y));

            return $vals[0];
        };

        $pick_max = static function (array $vals): string {
            $vals = array_values(array_unique($vals));
            if ($vals === []) {
                return '';
            }
            usort($vals, static fn ($x, $y): int => strcmp($x, $y));

            return $vals[count($vals) - 1];
        };

        $from = $pick_min($vb_vals);
        $to = $pick_max($va_vals);

        if ($lt === 'translation' && (!$from || $from === $to)) {
            if ($to !== '') {
                return sprintf(
                    /* translators: 1: item name, 2: version number */
                    __('Language pack updated for %1$s %2$s', 'updatronix'),
                    self::normalize_field((string) ($first->item_name ?: __('WordPress', 'updatronix'))),
                    self::normalize_field($to)
                );
            }

            return sprintf(
                /* translators: %s: item name */
                __('Language pack updated for %s', 'updatronix'),
                self::normalize_field((string) ($first->item_name ?: __('WordPress', 'updatronix')))
            );
        }

        $rep_action = sanitize_key((string) ($first->action_type ?? ''));
        if ($rep_action === 'same_version') {
            $version = $to !== '' ? $to : $from;

            return $version !== ''
                ? sprintf(
                    /* translators: %s: version number */
                    __('v%s', 'updatronix'),
                    self::normalize_field($version)
                )
                : '';
        }

        if ($from !== '' && $to !== '') {
            return sprintf(
                /* translators: 1: previous version number, 2: new version number */
                __('v%1$s → v%2$s', 'updatronix'),
                self::normalize_field($from),
                self::normalize_field($to)
            );
        }

        if ($to !== '') {
            return sprintf(
                /* translators: %s: version number */
                __('v%s', 'updatronix'),
                self::normalize_field($to)
            );
        }

        if ($from !== '') {
            return sprintf(
                /* translators: %s: version number */
                __('v%s', 'updatronix'),
                self::normalize_field($from)
            );
        }

        return '';
    }

    /**
     * @param array<int, object> $group            Rows.
     * @param bool               $column_requested Checkbox enabled.
     * @return string
     */
    private static function segment_trigger(array $group, bool $column_requested): string {
        if (!$column_requested) {
            return '';
        }

        $uniq = [];
        foreach ($group as $row) {
            $uniq[sanitize_key((string) ($row->performed_as ?? ''))] = true;
        }
        unset($uniq['']);

        if (count($uniq) !== 1) {
            return '';
        }

        $as = array_key_first($uniq);
        $label = match ($as) {
            'manual' => __('manual', 'updatronix'),
            'automatic' => __('automatic', 'updatronix'),
            'upload' => __('upload', 'updatronix'),
            default => '',
        };

        return $label !== '' ? '(' . $label . ')' : '';
    }

    /**
     * @param array<int, object> $group            Rows.
     * @param bool               $column_requested Checkbox enabled.
     * @return string
     */
    private static function segment_run_context(array $group, bool $column_requested): string {
        if (!$column_requested) {
            return '';
        }

        foreach ($group as $row) {
            $ctx = sanitize_key((string) ($row->update_context ?? ''));
            if ($ctx === '') {
                return '';
            }
        }

        $uniq = [];
        foreach ($group as $row) {
            $ctx = sanitize_key((string) ($row->update_context ?? ''));
            $uniq[$ctx] = true;
        }

        if (count($uniq) !== 1) {
            return '';
        }

        $ctx = array_key_first($uniq);

        return match ($ctx) {
            'bulk' => '[' . __('bulk', 'updatronix') . ']',
            'single' => '[' . __('single', 'updatronix') . ']',
            default => '',
        };
    }

    /**
     * @param array<int, object> $group            Rows.
     * @param bool               $column_requested Checkbox enabled.
     * @return string
     */
    private static function segment_user(array $group, bool $column_requested): string {
        if (!$column_requested) {
            return '';
        }

        $labels = [];
        foreach ($group as $row) {
            $labels[] = self::user_token_for_row($row);
        }
        $labels = array_values(array_unique($labels));

        if (count($labels) !== 1) {
            return '';
        }

        return sprintf(
            /* translators: %s: User display name or localized "system". */
            __('by %s', 'updatronix'),
            $labels[0]
        );
    }

    /**
     * @param object $row Row.
     * @return string Normalised token (never empty for valid rows).
     */
    private static function user_token_for_row(object $row): string {
        $pb = sanitize_key((string) ($row->performed_by ?? ''));
        $uid = (int) ($row->user_id ?? 0);

        if ($pb === 'system' || $uid <= 0) {
            return __('system', 'updatronix');
        }

        $user = get_userdata($uid);

        return $user
            ? self::normalize_field((string) $user->display_name)
            : sprintf(
                /* translators: %d: WordPress user ID when display name is not available */
                __('User #%d', 'updatronix'),
                $uid
            );
    }
}
