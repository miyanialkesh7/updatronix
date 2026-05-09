<?php
/**
 * Multisite policy tests for the Schedule subtree (network site option) and the
 * `manage_network_options` write gate added by S2 / M-1.
 *
 * Companion to scenarios MS-01, MS-02, MS-03, MS-05 in
 * `.cursor/notes/2026-05-09-test-plan-opus-notifications-schedule-features.md`.
 *
 * To run this suite the WordPress test bootstrap must be in multisite mode. Locally:
 *
 *     WP_TESTS_MULTISITE=1 vendor/bin/phpunit -c .config/phpunit.integration.xml.dist --filter Multisite
 *
 * Each test self-skips on single-site bootstraps so this file is safe in the default suite.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * @coversNothing
 */
final class MultisiteScheduleAccessTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        if (!is_multisite()) {
            self::markTestSkipped('MultisiteScheduleAccessTest requires WP_TESTS_MULTISITE=1.');
        }
    }

    protected function tearDown(): void {
        if (is_multisite()) {
            delete_site_option(UPDATRONIX_OPTION_NETWORK_SCHEDULE);
        }
        parent::tearDown();
    }

    public function test_subsite_admin_schedule_write_is_silently_ignored(): void {
        if (!function_exists('wpmu_create_blog')) {
            self::markTestSkipped('Multisite blog factory unavailable.');
        }

        $subsite_id = self::factory()->blog->create();
        $subsite_admin = self::factory()->user->create(['role' => 'administrator']);

        $baseline = ['recurrence' => 'twicedaily', 'time' => ''];
        updatronix_save_network_schedule([
            'update_check' => $baseline,
            'delay_updates' => ['enabled' => false, 'delay_value' => 0],
        ]);

        switch_to_blog($subsite_id);
        try {
            wp_set_current_user($subsite_admin);
            self::assertFalse(
                current_user_can('manage_network_options'),
                'A subsite administrator must not have manage_network_options.'
            );

            $request = new WP_REST_Request('POST', '/updatronix/v1/settings');
            $request->set_param('schedule', [
                'update_check' => ['recurrence' => 'daily', 'time' => '03:00'],
            ]);
            $response = rest_do_request($request);
            self::assertSame(200, $response->get_status(), 'Subsite save itself must not 4xx.');

            $data = $response->get_data();
            self::assertIsArray($data);
            self::assertArrayHasKey('schedule_ignored', $data);
            self::assertTrue($data['schedule_ignored'], 'Server must signal that the schedule payload was ignored.');

            $stored = updatronix_get_network_schedule();
            self::assertSame(
                'twicedaily',
                $stored['update_check']['recurrence'],
                'Subsite admin must not be able to mutate the network-wide schedule.'
            );
        } finally {
            restore_current_blog();
        }
    }

    public function test_super_admin_schedule_write_propagates_to_every_subsite(): void {
        if (!function_exists('wpmu_create_blog') || !function_exists('grant_super_admin')) {
            self::markTestSkipped('Multisite super-admin helpers unavailable.');
        }

        $subsite_a = self::factory()->blog->create();
        $subsite_b = self::factory()->blog->create();
        $super_admin = self::factory()->user->create(['role' => 'administrator']);
        grant_super_admin($super_admin);

        switch_to_blog($subsite_a);
        try {
            wp_set_current_user($super_admin);
            self::assertTrue(current_user_can('manage_network_options'), 'Super admin gate must hold for the test fixture.');

            $request = new WP_REST_Request('POST', '/updatronix/v1/settings');
            $request->set_param('schedule', [
                'update_check' => ['recurrence' => 'weekly', 'time' => '04:15'],
            ]);
            $response = rest_do_request($request);
            self::assertSame(200, $response->get_status());

            $data = $response->get_data();
            self::assertFalse(
                $data['schedule_ignored'] ?? true,
                'Super admin save must report schedule_ignored=false.'
            );
        } finally {
            restore_current_blog();
        }

        switch_to_blog($subsite_b);
        try {
            $observed = updatronix_get_settings()['schedule']['update_check']['recurrence'];
            self::assertSame(
                'weekly',
                $observed,
                'Network site option must propagate to every subsite.'
            );
        } finally {
            restore_current_blog();
        }
    }

    public function test_per_site_settings_remain_isolated_across_subsites(): void {
        if (!function_exists('wpmu_create_blog')) {
            self::markTestSkipped('Multisite blog factory unavailable.');
        }

        $subsite_a = self::factory()->blog->create();
        $subsite_b = self::factory()->blog->create();
        $admin_a = self::factory()->user->create(['role' => 'administrator']);

        switch_to_blog($subsite_a);
        try {
            wp_set_current_user($admin_a);
            $current = updatronix_get_settings();
            $current['notify_emails'] = 'subsite-a@example.com';
            updatronix_save_settings_array($current);
        } finally {
            restore_current_blog();
        }

        switch_to_blog($subsite_b);
        try {
            $observed = updatronix_get_settings()['notify_emails'];
            self::assertSame(
                '',
                $observed,
                'Per-site settings (notify_emails) must not bleed across subsites.'
            );
        } finally {
            restore_current_blog();
        }
    }
}
