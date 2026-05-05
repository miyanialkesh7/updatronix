<?php

/**
 * Registers a single plugin option (JSON) for all settings. Logs live in a dedicated table.
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Option key for the single JSON settings. */
const UPDATRONIX_OPTION_SETTINGS = 'updatronix_settings';

/** Default settings (keys only; used when decoding). */
const UPDATRONIX_SETTINGS_DEFAULTS = [
    'logging_enabled' => true,
    'retention_days' => 90,
    'notify_enabled' => false,
    'notify_emails' => '',
    'notify_on' => [],
    'auto_update_translations' => true,
    'dismissed_constants' => [],
];

/**
 * Default Schedule tab subtree (merged when missing).
 *
 * @return array<string, mixed>
 */
function updatronix_get_schedule_defaults(): array {
    return [
        'update_check' => [
            'recurrence' => '',
            'time' => '',
        ],
        'delay_updates' => [
            'enabled' => false,
            'delay_value' => 0,
        ],
    ];
}

/**
 * Recurrence slugs allowed for unified `wp_version_check` scheduling.
 *
 * Each slug must exist in {@see wp_get_schedules()} (WordPress default schedules).
 *
 * @return list<string>
 */
function updatronix_allowed_update_check_recurrence_slugs(): array {
    return ['hourly', 'twicedaily', 'daily', 'weekly'];
}

/**
 * Labels for Core {@see wp_get_schedules()} entries used in the Schedule tab picker (immutable slugs).
 *
 * @return list<array{slug: string, label: string}>
 */
function updatronix_get_allowed_cron_schedule_labels(): array {
    /** @var array<string, array{display: string, interval: int, ...}> $all */
    $all = wp_get_schedules();
    $out = [];
    foreach (updatronix_allowed_update_check_recurrence_slugs() as $slug) {
        if (!isset($all[$slug])) {
            continue;
        }

        $out[] = [
            'slug' => $slug,
            'label' => (string) $all[$slug]['display'],
        ];
    }

    return $out;
}

/**
 * Attach an admin-rendered datetime string for Schedule tab cron diagnostics.
 *
 * @param array{cron_schedule_labels: list<array{slug: string, label: string}>, update_check_next_scheduled: int|false, wp_cron_disabled: bool, timezone_string: string, schedule_driver: 'wordpress'|'updatronix', unified_schedule_active: bool} $meta Raw meta from {@see Updatronix_Cron::get_schedule_rest_meta()}.
 * @return array<string, mixed>
 */
function updatronix_decorate_schedule_meta_for_display(array $meta): array {
    $ts = $meta['update_check_next_scheduled'];
    $date_part = trim((string) get_option('date_format', '') . ' ' . (string) get_option('time_format', ''));
    if ($date_part === '') {
        $date_part = 'Y-m-d H:i';
    }
    $meta['update_check_next_human'] = ($ts !== false)
        ? wp_date($date_part, (int) $ts)
        : '';

    return $meta;
}

/**
 * Merge a partial Schedule payload from REST over the baseline (already normalized).
 *
 * @param array<string, mixed> $partial
 * @param array<string, mixed> $baseline
 * @return array<string, mixed>
 */
function updatronix_merge_partial_schedule_into(array $partial, array $baseline): array {
    $merged = [
        'update_check' => [
            'recurrence' => $baseline['update_check']['recurrence'],
            'time' => $baseline['update_check']['time'],
        ],
        'delay_updates' => [
            'enabled' => $baseline['delay_updates']['enabled'],
            'delay_value' => $baseline['delay_updates']['delay_value'],
        ],
    ];

    if (isset($partial['update_check']) && is_array($partial['update_check'])) {
        $uc = $partial['update_check'];
        if (array_key_exists('recurrence', $uc)) {
            $merged['update_check']['recurrence'] = (string) $uc['recurrence'];
        }
        if (array_key_exists('time', $uc)) {
            $merged['update_check']['time'] = (string) $uc['time'];
        }
    }

    if (isset($partial['delay_updates']) && is_array($partial['delay_updates'])) {
        $du = $partial['delay_updates'];
        if (array_key_exists('enabled', $du)) {
            $merged['delay_updates']['enabled'] = (bool) $du['enabled'];
        }
        if (array_key_exists('delay_value', $du)) {
            $merged['delay_updates']['delay_value'] = (int) $du['delay_value'];
        }
    }

    return $merged;
}

/**
 * Sanitize Schedule subtree (REST + Settings API JSON).
 *
 * @param array<string, mixed> $in
 * @return array{update_check: array{recurrence: string, time: string}, delay_updates: array{enabled: bool, delay_value: int}}
 */
function updatronix_sanitize_schedule_array(array $in): array {
    $defaults = updatronix_get_schedule_defaults();

    $uc_in = isset($in['update_check']) && is_array($in['update_check'])
        ? $in['update_check']
        : [];
    $du_in = isset($in['delay_updates']) && is_array($in['delay_updates'])
        ? $in['delay_updates']
        : [];

    $recurrence_raw = strtolower(trim((string) ($uc_in['recurrence'] ?? $defaults['update_check']['recurrence'])));
    $allowed_recurrences = updatronix_allowed_update_check_recurrence_slugs();
    $recurrence = in_array($recurrence_raw, $allowed_recurrences, true)
        ? $recurrence_raw
        : '';

    $time_raw = trim((string) ($uc_in['time'] ?? $defaults['update_check']['time']));
    $time = '';
    if ($recurrence === 'daily' || $recurrence === 'twicedaily' || $recurrence === 'weekly') {
        $time = updatronix_sanitize_schedule_wall_time($time_raw);
    }

    $delay_enabled = (bool) ($du_in['enabled'] ?? false);
    $delay_value_raw = isset($du_in['delay_value']) ? (int) $du_in['delay_value'] : (int) $defaults['delay_updates']['delay_value'];
    $delay_value = $delay_enabled ? max(1, min(365, $delay_value_raw)) : max(0, min(365, $delay_value_raw));

    if (!$delay_enabled) {
        $delay_value = 0;
    }

    return [
        'update_check' => [
            'recurrence' => $recurrence,
            'time' => $time,
        ],
        'delay_updates' => [
            'enabled' => $delay_enabled,
            'delay_value' => $delay_value,
        ],
    ];
}

/**
 * Normalize H:i in site-wall-clock semantics (used with {@see wp_timezone()} for cron timestamps).
 *
 * @param string $time_raw User input.
 * @return string Canonical `HH:mm` defaulting to 03:00 when empty or invalid (for daily schedules).
 */
function updatronix_sanitize_schedule_wall_time(string $time_raw): string {
    if ($time_raw !== '' && preg_match('/^(?:([01]?[0-9]|2[0-3])):([0-5][0-9])$/', $time_raw, $matches)) {
        $h = (int) $matches[1];
        $m = (int) $matches[2];
        $h = max(0, min(23, $h));
        $m = max(0, min(59, $m));

        return sprintf('%02d:%02d', $h, $m);
    }

    return '03:00';
}

/**
 * Next Unix timestamp for the first recurring discovery run ({@see wp_schedule_event()} first argument).
 *
 * `twicedaily` uses Core's twelve-hour interval; the picker time anchors only the initial run wall clock.
 *
 * For `weekly`, if today's preferred time has passed, the next run is the same weekday and clock time in seven days.
 *
 * @param string $recurrence hourly|twicedaily|daily|weekly
 * @param string $time       H:i site TZ wall clock when not hourly
 *
 * @return int
 */
function updatronix_next_update_check_timestamp(string $recurrence, string $time): int {
    if ($recurrence === 'hourly') {
        return (int) time();
    }

    if ($recurrence !== 'daily' && $recurrence !== 'twicedaily' && $recurrence !== 'weekly') {
        return (int) time();
    }

    $tz = wp_timezone();
    try {
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $normalized = updatronix_sanitize_schedule_wall_time($time);
        $parts = explode(':', $normalized);
        $hour = (int) $parts[0];
        $minute = (int) ($parts[1] ?? 0);
        $run = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:00', $today, $hour, $minute), $tz);

        if ($run === false) {
            return (int) time();
        }

        if ($run->getTimestamp() <= $now->getTimestamp()) {
            if ($recurrence === 'weekly') {
                $run = $run->modify('+7 days');
            } else {
                $run = $run->modify('+1 day');
            }
        }

        return (int) $run->getTimestamp();
    } catch (\Exception $exception) {
        return (int) time();
    }
}

add_action('init', 'updatronix_register_settings');
add_action('init', 'updatronix_maybe_grant_manage_cap', 1);

/**
 * Grant {@see UPDATRONIX_CAP_MANAGE} to the administrator role on existing installs (activation hook does not run on upgrade).
 *
 * @return void
 */
function updatronix_maybe_grant_manage_cap(): void {
    if (get_option('updatronix_cap_migrated', '') === '1') {
        return;
    }
    $role = get_role('administrator');
    if ($role && !$role->has_cap(UPDATRONIX_CAP_MANAGE)) {
        $role->add_cap(UPDATRONIX_CAP_MANAGE);
    }
    update_option('updatronix_cap_migrated', '1', false);
}

/**
 * Register the single plugin option (JSON-encoded settings).
 *
 * @return void
 */
function updatronix_register_settings(): void {
    register_setting(
        'updatronix',
        UPDATRONIX_OPTION_SETTINGS,
        [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'updatronix_sanitize_settings_json',
            'show_in_rest' => false,
        ]
    );
}

/**
 * Get plugin settings from the single JSON option.
 *
 * @return array{logging_enabled: bool, retention_days: int, notify_enabled: bool, notify_emails: string, notify_on: array<string>, auto_update_translations: bool, dismissed_constants: array<string>, schedule: array{update_check: array{recurrence: string, time: string}, delay_updates: array{enabled: bool, delay_value: int}}}
 */
function updatronix_get_settings(): array {
    $raw = get_option(UPDATRONIX_OPTION_SETTINGS, '');
    $decoded = [];
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    $schedule_src = isset($decoded['schedule']) && is_array($decoded['schedule']) ? $decoded['schedule'] : [];
    $defaults = UPDATRONIX_SETTINGS_DEFAULTS;
    $out = [
        'logging_enabled' => isset($decoded['logging_enabled']) ? (bool) $decoded['logging_enabled'] : $defaults['logging_enabled'],
        'retention_days' => isset($decoded['retention_days']) ? max(1, min(365, (int) $decoded['retention_days'])) : $defaults['retention_days'],
        'notify_enabled' => isset($decoded['notify_enabled']) ? (bool) $decoded['notify_enabled'] : $defaults['notify_enabled'],
        'notify_emails' => isset($decoded['notify_emails']) ? (string) $decoded['notify_emails'] : $defaults['notify_emails'],
        'notify_on' => updatronix_normalize_notify_on($decoded['notify_on'] ?? $defaults['notify_on']),
        'auto_update_translations' => isset($decoded['auto_update_translations']) ? (bool) $decoded['auto_update_translations'] : $defaults['auto_update_translations'],
        'dismissed_constants' => isset($decoded['dismissed_constants']) && is_array($decoded['dismissed_constants'])
            ? array_values(array_filter($decoded['dismissed_constants'], 'is_string'))
            : $defaults['dismissed_constants'],
        'schedule' => updatronix_sanitize_schedule_array($schedule_src),
    ];

    return $out;
}

/**
 * Sanitize incoming settings (REST or form) into a JSON string for the option.
 *
 * @param mixed $value Raw value (array or JSON string).
 * @return string JSON string to store.
 */
function updatronix_sanitize_settings_json(mixed $value): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        $value = [];
    }
    $allowed_notify = ['core', 'plugin_theme', 'debug', 'technical'];
    $raw_notify = array_filter((array) ($value['notify_on'] ?? []), 'is_string');
    $notify_on = array_values(array_intersect($raw_notify, $allowed_notify));
    // Legacy: treat 'plugin' or 'theme' as 'plugin_theme'.
    if (array_intersect($raw_notify, ['plugin', 'theme']) !== [] && !in_array('plugin_theme', $notify_on, true)) {
        $notify_on[] = 'plugin_theme';
        $notify_on = array_values(array_unique($notify_on));
    }
    $out = [
        'logging_enabled' => (bool) ($value['logging_enabled'] ?? true),
        'retention_days' => max(1, min(365, (int) ($value['retention_days'] ?? 90))),
        'notify_enabled' => (bool) ($value['notify_enabled'] ?? false),
        'notify_emails' => updatronix_sanitize_emails($value['notify_emails'] ?? ''),
        'notify_on' => $notify_on,
        'auto_update_translations' => (bool) ($value['auto_update_translations'] ?? true),
        'dismissed_constants' => array_values(array_filter((array) ($value['dismissed_constants'] ?? []), 'is_string')),
        'schedule' => updatronix_sanitize_schedule_array(
            isset($value['schedule']) && is_array($value['schedule']) ? $value['schedule'] : []
        ),
    ];
    $encoded = wp_json_encode($out);

    return $encoded !== false ? $encoded : '{}';
}

/**
 * Persist settings using the same sanitization as the Settings API and register_setting callback.
 *
 * @param array<string, mixed> $input Raw settings (same shape as {@see updatronix_get_settings()} keys).
 * @return void
 */
function updatronix_save_settings_array(array $input): void {
    update_option(UPDATRONIX_OPTION_SETTINGS, updatronix_sanitize_settings_json($input));
    do_action('updatronix_after_save_settings');
}

/**
 * Sanitize comma-separated email list.
 *
 * @param mixed $value Raw value.
 * @return string Sanitized comma-separated email addresses.
 */
function updatronix_sanitize_emails(mixed $value): string {
    $emails = array_filter(array_map('sanitize_email', explode(',', (string) $value)));

    return implode(', ', $emails);
}

/**
 * Normalize notify_on for display (REST/localize). Expands legacy 'all' to all allowed keys.
 *
 * @param mixed $notify_on Raw option value.
 * @return array<string> Normalized notification type keys.
 */
function updatronix_normalize_notify_on(mixed $notify_on): array {
    $allowed = ['core', 'plugin_theme', 'debug', 'technical'];
    $raw = array_filter((array) $notify_on, 'is_string');
    if (in_array('all', $raw, true)) {
        return $allowed;
    }
    $arr = array_values(array_intersect($raw, $allowed));
    // Legacy: map 'plugin' or 'theme' to 'plugin_theme' for display.
    if (array_intersect($raw, ['plugin', 'theme']) !== [] && !in_array('plugin_theme', $arr, true)) {
        $arr[] = 'plugin_theme';
        $arr = array_values(array_unique($arr));
    }

    return $arr;
}
