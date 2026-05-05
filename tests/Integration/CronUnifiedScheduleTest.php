<?php
/**
 * Integration tests for unified Schedule tab cron behaviour (Core hooks + wp-admin messaging parity).
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * @coversNothing
 */
final class CronUnifiedScheduleTest extends WP_UnitTestCase {
    /**
     * When recurrence is plugin-controlled, `wp_version_check` stays scheduled (admin UI messaging) while
     * redundant plugin/theme recurring checks are suppressed.
     *
     * WordPress-default recurrence restores Core's three-hook pattern.
     *
     * @return void
     */
    public function test_unified_schedule_keeps_wp_version_check_and_suppresses_plugin_theme_crons(): void {
        wp_clear_scheduled_hook('wp_version_check');
        wp_clear_scheduled_hook('wp_update_plugins');
        wp_clear_scheduled_hook('wp_update_themes');
        if (function_exists('wp_schedule_update_checks')) {
            wp_schedule_update_checks();
        }

        self::assertIsInt(wp_next_scheduled('wp_version_check'), 'Test setup should register Core update crons.');

        $current = updatronix_get_settings();
        $current['schedule']['update_check']['recurrence'] = 'daily';
        $current['schedule']['update_check']['time'] = '03:00';
        updatronix_save_settings_array($current);

        self::assertIsInt(wp_next_scheduled(Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK));
        self::assertFalse(wp_next_scheduled('wp_update_plugins'));
        self::assertFalse(wp_next_scheduled('wp_update_themes'));

        $current['schedule']['update_check']['recurrence'] = '';
        updatronix_save_settings_array($current);

        self::assertIsInt(wp_next_scheduled('wp_version_check'));
        self::assertIsInt(wp_next_scheduled('wp_update_plugins'));
        self::assertIsInt(wp_next_scheduled('wp_update_themes'));
    }

    /**
     * @return void
     */
    public function test_schedule_meta_includes_driver_fields(): void {
        $meta = Updatronix_Cron::get_schedule_rest_meta();
        self::assertArrayHasKey('schedule_driver', $meta);
        self::assertArrayHasKey('unified_schedule_active', $meta);
        self::assertContains($meta['schedule_driver'], ['wordpress', 'updatronix']);
        self::assertIsBool($meta['unified_schedule_active']);
    }

    /**
     * Weekly is a native WordPress cron schedule; unified mode should accept it and keep `wp_version_check` scheduled.
     *
     * @return void
     */
    public function test_weekly_recurrence_keeps_wp_version_check_and_suppresses_plugin_theme_crons(): void {
        $schedules = wp_get_schedules();
        if (!isset($schedules['weekly'])) {
            self::markTestSkipped('Weekly schedule not registered in this WordPress version.');
        }

        wp_clear_scheduled_hook('wp_version_check');
        wp_clear_scheduled_hook('wp_update_plugins');
        wp_clear_scheduled_hook('wp_update_themes');
        if (function_exists('wp_schedule_update_checks')) {
            wp_schedule_update_checks();
        }

        $current = updatronix_get_settings();
        $current['schedule']['update_check']['recurrence'] = 'weekly';
        $current['schedule']['update_check']['time'] = '04:15';
        updatronix_save_settings_array($current);

        self::assertTrue(Updatronix_Cron::is_unified_schedule_active());
        self::assertIsInt(wp_next_scheduled(Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK));
        self::assertFalse(wp_next_scheduled('wp_update_plugins'));
        self::assertFalse(wp_next_scheduled('wp_update_themes'));

        $current['schedule']['update_check']['recurrence'] = '';
        updatronix_save_settings_array($current);

        self::assertIsInt(wp_next_scheduled('wp_version_check'));
        self::assertIsInt(wp_next_scheduled('wp_update_plugins'));
        self::assertIsInt(wp_next_scheduled('wp_update_themes'));
    }
}
