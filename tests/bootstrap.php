<?php

/**
 * PHPUnit bootstrap: minimal WordPress stubs for unit tests without full WP bootstrap.
 *
 * @package updatronix
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

require_once dirname(__DIR__) . '/inc/classes/CoreUpdateLogVersions.php';
