<?php

/**
 * Plain-text export body rendering (locale-aware).
 *
 * Human-readable lines: bullets (`*`), site date & time preferences, optional tight `[Category]` / `[Status]`
 * tags (per export checkboxes), three heading levels: `===` (document) → `==` (section type) → `=` (before bullet runs).
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
     * @param array<string, bool>    $columns            Column toggles (@see COLUMN_KEYS).
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

            $columns = self::normalize_export_columns($columns);

            $records = [];
            if ($merge) {
                foreach (self::build_sectioned_merge_records($rows, $columns) as $rec) {
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

            if ($existing_chunk_body === '') {
                $lead = [['line' => self::export_document_heading(), 'row_span' => 0]];
                $lead[] = ['line' => '', 'row_span' => 0];
                if (!$merge) {
                    $lead[] = [
                        'line' => sprintf(
                            '== %s ==',
                            /* translators: Non-merge export: chronological list heading. */
                            __('All entries', 'updatronix')
                        ),
                        'row_span' => 0,
                    ];
                    $lead[] = ['line' => self::section_detail_rule_heading(), 'row_span' => 0];
                }
                $records = array_merge($lead, $records);
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
     * Merge client toggles with defaults so older payloads and sparse objects stay predictable.
     *
     * Keys align with {@see Updatronix_Export::COLUMN_KEYS}; omitted keys inherit defaults below.
     *
     * @param array<string, bool> $columns Raw request column toggles.
     * @return array<string, bool>
     */
    private static function normalize_export_columns(array $columns): array {
        /** @var array<string, bool> */
        static $defaults = [
            'date' => true,
            'category' => true,
            'status' => true,
            'action_type' => true,
            'user' => false,
            'trigger_type' => false,
            'run_context' => false,
        ];

        $out = [];
        foreach ($defaults as $key => $default_on) {
            $out[$key] = array_key_exists($key, $columns)
                ? (bool) $columns[$key]
                : $default_on;
        }

        return $out;
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
     * Merge ON: section headings + bullet lines — skip empty sections; A–Z within each section.
     *
     * @param array<int, object>  $rows    Rows in one SQL chunk.
     * @param array<string, bool> $columns Segment toggles.
     * @return array<int, array{line: string, row_span: int}>
     */
    private static function build_sectioned_merge_records(array $rows, array $columns): array {
        $buckets = [];

        foreach ($rows as $row) {
            $key = self::merge_key($row);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [];
            }
            $buckets[$key][] = $row;
        }

        $by_type = [
            'core' => [],
            'theme' => [],
            'plugin' => [],
            'translation' => [],
        ];

        foreach ($buckets as $key => $group) {
            $lt = sanitize_key((string) ($group[0]->log_type ?? ''));
            if (!isset($by_type[$lt])) {
                continue;
            }
            $by_type[$lt][$key] = $group;
        }

        $records = [];
        $has_section = false;

        $core_items = self::sorted_merge_lines($by_type['core'], $columns);
        if ($core_items !== []) {
            $records[] = ['line' => self::section_heading_core(), 'row_span' => 0];
            $records[] = ['line' => self::section_detail_rule_heading(), 'row_span' => 0];
            foreach ($core_items as $item) {
                $records[] = $item;
            }
            $has_section = true;
        }

        $theme_items = self::sorted_merge_lines($by_type['theme'], $columns);
        if ($theme_items !== []) {
            if ($has_section) {
                $records[] = ['line' => '', 'row_span' => 0];
            }
            $records[] = ['line' => self::section_heading_themes(), 'row_span' => 0];
            $records[] = ['line' => self::section_detail_rule_heading(), 'row_span' => 0];
            foreach ($theme_items as $item) {
                $records[] = $item;
            }
            $has_section = true;
        }

        $plugin_items = self::sorted_merge_lines($by_type['plugin'], $columns);
        if ($plugin_items !== []) {
            if ($has_section) {
                $records[] = ['line' => '', 'row_span' => 0];
            }
            $records[] = ['line' => self::section_heading_plugins(), 'row_span' => 0];
            $records[] = ['line' => self::section_detail_rule_heading(), 'row_span' => 0];
            foreach ($plugin_items as $item) {
                $records[] = $item;
            }
            $has_section = true;
        }

        $translation_items = self::sorted_merge_lines($by_type['translation'], $columns);
        if ($translation_items !== []) {
            if ($has_section) {
                $records[] = ['line' => '', 'row_span' => 0];
            }
            $records[] = ['line' => self::section_heading_translations(), 'row_span' => 0];
            $records[] = ['line' => self::section_detail_rule_heading(), 'row_span' => 0];
            foreach ($translation_items as $item) {
                $records[] = $item;
            }
        }

        return $records;
    }

    /**
     * Format merged groups as list lines, sorted A–Z by sort label (case-insensitive).
     *
     * @param array<string, array<int, object>> $type_buckets merge_key => rows.
     * @param array<string, bool>               $columns      Column toggles.
     * @return array<int, array{line: string, row_span: int}>
     */
    private static function sorted_merge_lines(array $type_buckets, array $columns): array {
        $items = [];
        foreach ($type_buckets as $group) {
            if ($group === []) {
                continue;
            }
            usort(
                $group,
                static function (object $a, object $b): int {
                    return strcmp((string) ($a->created_at ?? ''), (string) ($b->created_at ?? ''));
                }
            );
            $line = self::format_merged_line($group, $columns);
            $label = self::sort_label_for_group($group);
            $items[] = [
                'sort' => $label,
                'line' => $line,
                'row_span' => count($group),
            ];
        }
        usort(
            $items,
            static fn (array $a, array $b): int => strcasecmp($a['sort'], $b['sort'])
        );

        $out = [];
        foreach ($items as $item) {
            $out[] = [
                'line' => $item['line'],
                'row_span' => $item['row_span'],
            ];
        }

        return $out;
    }

    /**
     * Sort key: lowercased item name or slug (stable for A–Z).
     *
     * @param array<int, object> $group Merged rows.
     * @return string
     */
    private static function sort_label_for_group(array $group): string {
        $row = $group[0] ?? null;
        if (!is_object($row)) {
            return '';
        }
        $slug = trim((string) ($row->item_slug ?? ''));
        $name = trim((string) ($row->item_name ?? ''));
        $raw = $slug !== '' ? $slug : $name;

        return mb_strtolower(self::normalize_field($raw), 'UTF-8');
    }

    /**
     * Largest export title (first chunk only, via {@see render()}).
     */
    private static function export_document_heading(): string {
        return sprintf(
            '=== %s ===',
            __('Update log export', 'updatronix')
        );
    }

    /**
     * Small title line immediately before bullet lines under a section.
     */
    private static function section_detail_rule_heading(): string {
        return sprintf(
            '= %s =',
            /* translators: Narrow third-level export heading before bullet list. */
            __('Updates', 'updatronix')
        );
    }

    /**
     * @return string
     */
    private static function section_heading_core(): string {
        /* translators: %s: Core updates section (merged export). */
        return sprintf('== %s ==', __('CORE', 'updatronix'));
    }

    /**
     * @return string
     */
    private static function section_heading_plugins(): string {
        /* translators: %s: Plugin updates section. */
        return sprintf('== %s ==', __('PLUGINS', 'updatronix'));
    }

    /**
     * @return string
     */
    private static function section_heading_themes(): string {
        /* translators: %s: Theme updates section. */
        return sprintf('== %s ==', __('THEMES', 'updatronix'));
    }

    /**
     * @return string
     */
    private static function section_heading_translations(): string {
        /* translators: %s: Translation updates section. */
        return sprintf('== %s ==', __('TRANSLATIONS', 'updatronix'));
    }

    /**
     * Human/slug label for translation identifiers in export (slug preferred; lowercase).
     *
     * @param object $row Representative row.
     * @return string
     */
    private static function translation_export_list_label(object $row): string {
        $slug = trim((string) ($row->item_slug ?? ''));
        if ($slug !== '') {
            return self::normalize_field(mb_strtolower($slug, 'UTF-8'));
        }

        return self::normalize_field((string) ($row->item_name ?? ''));
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
            if ($folder !== '' && $folder !== '.') {
                $slug = $folder;
            }
        }

        return sanitize_key($slug);
    }

    /**
     * Stable merge key for log rows within a chunk.
     *
     * Translation rows split per slug/name so lists can enumerate packages.
     *
     * @param object $row Row object.
     * @return string Non-readable delimiter-separated key.
     */
    private static function merge_key(object $row): string {
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
     * @return string Single bullet-prefixed line.
     */
    private static function format_aggregate_line(array $group, array $columns): string {
        $first = $group[0];
        $last = $group[count($group) - 1];

        $chunks = [];

        if (!empty($columns['date'])) {
            $chunks[] = self::export_datetime_bracket($group, $first, $last);
        }

        if (!empty($columns['category'])) {
            $chunks[] = self::export_category_bracket($last);
        }

        if (!empty($columns['status'])) {
            $chunks[] = self::export_status_bracket_for_group($group);
        }

        if (!empty($columns['action_type'])) {
            $chunks[] = self::export_action_bracket_for_group($group);
        }

        $chunks[] = self::export_human_identifier($last);
        $chunks[] = self::compact_version_plain($group, $first);

        $chunks = array_filter(array_map(static fn ($p): ?string => $p !== '' ? $p : null, $chunks));
        $main = implode(' ', $chunks);

        $suffix = self::human_export_optional_suffix($group, $columns);
        if ($suffix !== '') {
            $main .= ' ' . $suffix;
        }

        return '* ' . self::normalize_field($main);
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
     * @param array<int, object> $group Rows.
     * @param object               $first First chronologically.
     * @param object               $last  Last chronologically.
     * @return string Bracketed single time or localized range `[a → b]`.
     */
    private static function export_datetime_bracket(array $group, object $first, object $last): string {
        $ts_min = strtotime((string) ($first->created_at ?? ''));
        $ts_max = strtotime((string) ($last->created_at ?? ''));

        if ($ts_min === false) {
            $ts_min = 0;
        }
        if ($ts_max === false) {
            $ts_max = $ts_min;
        }
        if ($ts_min <= 0) {
            $ts_min = $ts_max > 0 ? $ts_max : 0;
        }
        if ($ts_max <= 0) {
            $ts_max = $ts_min;
        }

        $fmin = self::format_export_datetime((int) $ts_min);
        $fmax = self::format_export_datetime((int) $ts_max);

        $one = $fmin !== '' ? $fmin : $fmax;
        $two = $fmax !== '' ? $fmax : $fmin;

        if ($one === '' && $two === '') {
            return '[' . _x('?', 'Placeholder when exported log row timestamp is unreadable', 'updatronix') . ']';
        }

        if ($one === $two || count($group) === 1) {
            return '[' . $one . ']';
        }

        return sprintf(
            /* translators: 1: Start datetime (site preferences). 2: End datetime. */
            __('[%1$s → %2$s]', 'updatronix'),
            $one,
            $two
        );
    }

    /**
     * @param object $representative Typical last row chronologically within the merge bucket.
     * @return string e.g. "[PLUGIN]".
     */
    private static function export_category_bracket(object $representative): string {
        $lt = sanitize_key((string) ($representative->log_type ?? ''));

        $label = match ($lt) {
            'core' => _x('CORE', 'Compressed log-export category label', 'updatronix'),
            'plugin' => _x('PLUGIN', 'Compressed log-export category label', 'updatronix'),
            'theme' => _x('THEME', 'Compressed log-export category label', 'updatronix'),
            'translation' => _x('TRANSLATION', 'Compressed log-export category label', 'updatronix'),
            default => mb_strtoupper($lt !== '' ? mb_substr($lt, 0, 13, 'UTF-8') : '—', 'UTF-8'),
        };

        return '[' . $label . ']';
    }

    /**
     * Status bracket when every row agrees (activity log wording); mixed rows → omitted.
     *
     * @param array<int, object> $group Rows in one merged or single-row group.
     * @return string e.g. "[Success]" or empty.
     */
    private static function export_status_bracket_for_group(array $group): string {
        $by_flat = [];
        foreach ($group as $row) {
            $raw = trim((string) ($row->status ?? ''));
            if ($raw === '') {
                continue;
            }
            $flat = strtolower(sanitize_key($raw));
            $by_flat[$flat] = $raw;
        }

        if (count($by_flat) !== 1) {
            return '';
        }

        $flat = array_key_first($by_flat);
        $sample_raw = $by_flat[$flat];

        $label = match ($flat) {
            'success', 'updated', 'ok' => __('Success', 'updatronix'),
            'warning', 'warn' => __('Warning', 'updatronix'),
            'error', 'failed', 'errors' => __('Error', 'updatronix'),
            'cancelled' => __('Cancelled', 'updatronix'),
            default => '',
        };

        if ($label === '') {
            $label = $sample_raw;
        }

        return '[' . $label . ']';
    }

    /**
     * Action type label when all rows share the same action (matches log list / details).
     *
     * @param array<int, object> $group Rows.
     * @return string e.g. "[Update]" or empty.
     */
    private static function export_action_bracket_for_group(array $group): string {
        $uniq = [];
        foreach ($group as $row) {
            $k = sanitize_key((string) ($row->action_type ?? ''));
            if ($k !== '') {
                $uniq[$k] = true;
            }
        }

        if (count($uniq) !== 1) {
            return '';
        }

        $label = self::action_type_display_label(array_key_first($uniq));

        return $label !== '' ? '[' . $label . ']' : '';
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
     * Slug-like lowercase token for the exported line (`woocommerce`, `wordpress`, …).
     *
     * @param object $representative Representative row for category/slug/name.
     * @return string
     */
    private static function export_human_identifier(object $representative): string {
        $lt = sanitize_key((string) ($representative->log_type ?? ''));

        if ($lt === 'core') {
            return 'wordpress';
        }

        $slug_raw = trim((string) ($representative->item_slug ?? ''));
        if ($slug_raw !== '') {
            $tok = self::normalise_item_slug_token_for_merge($slug_raw, $lt);
            if ($tok !== '') {
                return strtolower($tok);
            }
        }

        if ($lt === 'translation') {
            return self::translation_export_list_label($representative);
        }

        return strtolower(
            self::normalize_field(
                (string) (
                    $representative->item_name
                    ?: _x('item', 'Fallback export slug when neither slug nor translation label exists', 'updatronix')
                )
            )
        );
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

    /**
     * @param array<int, object>  $group   Rows.
     * @param array<string, bool> $columns Optional detail toggles.
     * @return string Space-prefixed optional suffix (empty → none).
     */
    private static function human_export_optional_suffix(array $group, array $columns): string {
        $extras = [];

        $trig = self::segment_trigger($group, !empty($columns['trigger_type']));
        if ($trig !== '') {
            $extras[] = $trig;
        }

        $run = self::segment_run_context($group, !empty($columns['run_context']));
        if ($run !== '') {
            $extras[] = $run;
        }

        $usr = self::segment_user($group, !empty($columns['user']));
        if ($usr !== '') {
            $extras[] = $usr;
        }

        if ($extras === []) {
            return '';
        }

        return implode(' ', $extras);
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
