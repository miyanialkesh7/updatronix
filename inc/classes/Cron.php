<?php

/**
 * Scheduled tasks for log retention and optional update discovery refreshes.
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin-owned WP-Cron jobs.
 */
final class Updatronix_Cron {
    /**
     * Cron hook name.
     *
     * @var string
     */
    public const HOOK_CLEANUP = 'updatronix_cleanup_logs';

    /**
     * Cron hook: run WordPress discovery functions ({@see wp_version_check()}, etc.).
     *
     * @var string
     */
    public const HOOK_UPDATE_CHECK = 'updatronix_run_update_discovery';

    /**
     * Transient key: throttle self-healing schedule checks (not autoloaded).
     *
     * @var string
     */
    private const SELF_HEAL_TRANSIENT = 'updatronix_cron_self_heal_throttle';

    /**
     * Transient key: throttle rediscovery cron self-heal (not autoloaded).
     *
     * @var string
     */
    private const UPDATE_CHECK_HEAL_TRANSIENT = 'updatronix_update_check_heal_throttle';

    /**
     * Register cron schedule and hook.
     *
     * @return void
     */
    public static function register(): void {
        add_action(self::HOOK_CLEANUP, [self::class, 'run_cleanup']);
        add_action(self::HOOK_UPDATE_CHECK, [self::class, 'run_update_discovery']);
        add_action('shutdown', [self::class, 'maybe_schedule_if_needed'], 999);
        add_action('shutdown', [self::class, 'maybe_heal_update_check_schedule'], 998);
        add_action('updatronix_after_save_settings', [self::class, 'apply_update_check_schedule_from_settings']);
    }

    /**
     * Re-schedule cleanup if the event was lost (e.g. manual cron table clear), at most once per day.
     *
     * Activation still calls {@see Updatronix_Cron::schedule_if_needed()} directly; this path avoids wp_next_scheduled
     * on every init request.
     *
     * @return void
     */
    public static function maybe_schedule_if_needed(): void {
        if (get_transient(self::SELF_HEAL_TRANSIENT)) {
            return;
        }

        set_transient(self::SELF_HEAL_TRANSIENT, '1', DAY_IN_SECONDS);
        self::schedule_if_needed();
    }

    /**
     * Schedule daily cleanup if not already scheduled.
     *
     * @return void
     */
    public static function schedule_if_needed(): void {
        if (wp_next_scheduled(self::HOOK_CLEANUP)) {
            return;
        }

        wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP);
    }

    /**
     * Run cleanup: delete logs older than retention days.
     *
     * @return void
     */
    public static function run_cleanup(): void {
        $days = updatronix_get_settings()['retention_days'];
        if ($days < 1) {
            return;
        }

        Updatronix_Logger::delete_older_than($days);
    }

    /**
     * WP-Cron callback: refresh core/plugin/theme discovery transients via native APIs.
     *
     * Mirrors the behaviour documented in wordpress-native-updates-reference.md §1.1–§1.5
     * (`wp_version_check()`, `wp_update_plugins()`, `wp_update_themes()` populate site transients).
     *
     * @return void
     */
    public static function run_update_discovery(): void {
        if (!function_exists('wp_version_check')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        wp_version_check();
        wp_update_plugins();
        wp_update_themes();
    }

    /**
     * Reschedule discovery runs from stored settings (after save).
     *
     * @return void
     */
    public static function apply_update_check_schedule_from_settings(): void {
        wp_clear_scheduled_hook(self::HOOK_UPDATE_CHECK);
        $settings = updatronix_get_settings();
        $schedule = $settings['schedule'];
        $recurrence = $schedule['update_check']['recurrence'];
        if ($recurrence === '' || !in_array($recurrence, ['hourly', 'twicedaily', 'daily'], true)) {
            return;
        }

        $time = $schedule['update_check']['time'];
        $timestamp = updatronix_next_update_check_timestamp($recurrence, $time);
        wp_schedule_event((int) $timestamp, $recurrence, self::HOOK_UPDATE_CHECK);
    }

    /**
     * If settings require a recurring discovery run but WP-Cron lost the hook, reschedule (throttled).
     *
     * @return void
     */
    public static function maybe_heal_update_check_schedule(): void {
        if (get_transient(self::UPDATE_CHECK_HEAL_TRANSIENT)) {
            return;
        }

        set_transient(self::UPDATE_CHECK_HEAL_TRANSIENT, '1', HOUR_IN_SECONDS);

        $settings = updatronix_get_settings();
        $schedule = $settings['schedule'];
        $recurrence = $schedule['update_check']['recurrence'];
        if ($recurrence === '' || !in_array($recurrence, ['hourly', 'twicedaily', 'daily'], true)) {
            wp_clear_scheduled_hook(self::HOOK_UPDATE_CHECK);

            return;
        }

        if (wp_next_scheduled(self::HOOK_UPDATE_CHECK)) {
            return;
        }

        $time = $schedule['update_check']['time'];
        $timestamp = updatronix_next_update_check_timestamp($recurrence, $time);
        wp_schedule_event((int) $timestamp, $recurrence, self::HOOK_UPDATE_CHECK);
    }

    /**
     * Unschedule the cleanup event (e.g. on deactivation).
     *
     * @return void
     */
    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
        wp_clear_scheduled_hook(self::HOOK_UPDATE_CHECK);
    }

    /**
     * Remove plugin transients (e.g. on uninstall).
     *
     * @return void
     */
    public static function delete_plugin_transients(): void {
        delete_transient(self::SELF_HEAL_TRANSIENT);
        delete_transient(self::UPDATE_CHECK_HEAL_TRANSIENT);
    }

    /**
     * Read-only Schedule tab cron diagnostics (localized + REST payload).
     *
     * @return array{
     *     cron_schedule_labels: list<array{slug: string, label: string}>,
     *     update_check_next_scheduled: int|false,
     *     wp_cron_disabled: bool,
     *     timezone_string: string,
     * }
     */
    public static function get_schedule_rest_meta(): array {
        $next = wp_next_scheduled(self::HOOK_UPDATE_CHECK);

        return [
            'cron_schedule_labels' => updatronix_get_allowed_cron_schedule_labels(),
            'update_check_next_scheduled' => ($next !== false) ? $next : false,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'timezone_string' => (string) wp_timezone_string(),
        ];
    }
}
