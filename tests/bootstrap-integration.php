<?php
/**
 * Bootstrap for WordPress integration tests (full WP + plugin load).
 *
 * Requires a one-time install: see `.config/wp-tests-env.example` and `bin/install-wp-tests.sh`.
 *
 * @package updatronix
 */

declare(strict_types=1);

// Only PHPUnit / WP test harness loads this file (never via HTTP).
if ('cli' !== \PHP_SAPI && 'phpdbg' !== \PHP_SAPI) {
    exit;
}

$updatronix_plugin_root = dirname(__DIR__);

require_once $updatronix_plugin_root . '/vendor/autoload.php';
require_once $updatronix_plugin_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$updatronix_wp_tests_dir = getenv('WP_TESTS_DIR');
if (!is_string($updatronix_wp_tests_dir) || $updatronix_wp_tests_dir === '') {
    $updatronix_wp_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!is_file($updatronix_wp_tests_dir . '/includes/functions.php')) {
    die(
        "WordPress test library not found.\n"
        . "Install it (from the plugin directory):\n"
        . "  bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> latest\n"
        . "Or set WP_TESTS_DIR to an existing wordpress-tests-lib path.\n"
    );
}

require_once $updatronix_wp_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test (same pattern as WP-CLI scaffold).
 */
function updatronix_tests_load_plugin(): void {
    require dirname(__DIR__) . '/updatronix.php';
}

tests_add_filter('muplugins_loaded', 'updatronix_tests_load_plugin');

/**
 * Ensure Administrator has the plugin cap in tests (activation migration does not run here).
 */
function updatronix_tests_ensure_admin_cap(): void {
    if (!defined('UPDATRONIX_CAP_MANAGE')) {
        return;
    }
    $role = get_role('administrator');
    if ($role && !$role->has_cap(UPDATRONIX_CAP_MANAGE)) {
        $role->add_cap(UPDATRONIX_CAP_MANAGE);
    }
}

tests_add_filter('init', 'updatronix_tests_ensure_admin_cap', 0);

require $updatronix_wp_tests_dir . '/includes/bootstrap.php';
