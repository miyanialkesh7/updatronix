<?php

/**
 * Per-user export rate limiting (short sliding windows via transients).
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Token-bucket style limits for export starts (requests without a continuation cursor).
 */
final class Updatronix_Export_Rate_Limiter {
    /**
     * Consume one export start slot for the given site and user.
     *
     * @since 1.1.0
     *
     * @param int $site_id Site blog ID.
     * @param int $user_id User ID.
     * @return true|\WP_Error True when allowed; WP_Error rate_limited when exhausted.
     */
    public static function consume(int $site_id, int $user_id): bool|\WP_Error {
        $site_id = max(1, $site_id);
        $user_id = max(1, $user_id);

        $key_min = sprintf('updatronix_export_rl_min_%d_%d', $site_id, $user_id);
        $key_hour = sprintf('updatronix_export_rl_hour_%d_%d', $site_id, $user_id);

        $minute_ok = self::incr_window($key_min, MINUTE_IN_SECONDS, Updatronix_Export::RATE_LIMIT_PER_MINUTE);
        if (is_wp_error($minute_ok)) {
            return $minute_ok;
        }

        $hour_ok = self::incr_window($key_hour, HOUR_IN_SECONDS, Updatronix_Export::RATE_LIMIT_PER_HOUR);
        if (is_wp_error($hour_ok)) {
            return $hour_ok;
        }

        return true;
    }

    /**
     * Increment a named counter transient with TTL; enforce max.
     *
     * @param string $key    Transient key.
     * @param int    $ttl    TTL seconds.
     * @param int    $max    Maximum count allowed after increment.
     * @return true|\WP_Error
     */
    private static function incr_window(string $key, int $ttl, int $max): bool|\WP_Error {
        $group = 'transient_updatronix_export_rl';

        if (wp_using_ext_object_cache()) {
            $cur = (int) wp_cache_get($key, $group, false, $found);
            if (!$found) {
                $cur = 0;
            }
            if ($cur + 1 > $max) {
                self::log_rate_limited($key);

                return new WP_Error(
                    'rate_limited',
                    '',
                    ['status' => 429]
                );
            }
            wp_cache_set($key, $cur + 1, $group, $ttl);

            return true;
        }

        $cur = (int) get_transient($key);
        if ($cur + 1 > $max) {
            self::log_rate_limited($key);

            return new WP_Error(
                'rate_limited',
                '',
                ['status' => 429]
            );
        }
        set_transient($key, $cur + 1, $ttl);

        return true;
    }

    /**
     * Log rate-limit exhaustion server-side only.
     *
     * @param string $key Transient key (diagnostic).
     * @return void
     */
    private static function log_rate_limited(string $key): void {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated diagnostic only.
        error_log(sprintf('[updatronix] export rate_limited window=%s', $key));
    }
}
