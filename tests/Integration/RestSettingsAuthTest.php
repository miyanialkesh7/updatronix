<?php
/**
 * REST API auth smoke tests for Updatronix settings route.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * @coversNothing
 */
final class RestSettingsAuthTest extends WP_UnitTestCase {
    public function test_get_settings_returns_403_when_not_logged_in(): void {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/updatronix/v1/settings');
        $response = rest_do_request($request);

        $status = $response->get_status();
        self::assertContains($status, [401, 403], 'Anonymous user must not read settings.');
    }

    public function test_get_settings_returns_200_for_administrator_with_cap(): void {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $request = new WP_REST_Request('GET', '/updatronix/v1/settings');
        $response = rest_do_request($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertArrayHasKey('options', $data);
        self::assertIsArray($data['options']);
    }
}
