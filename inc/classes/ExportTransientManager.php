<?php

/**
 * Transient lifecycle helpers for chunked export payloads.
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unguessable export transient keys plus replace-on-write user-meta pointer.
 */
final class Updatronix_Export_Transient_Manager {
    /**
     * Meta key storing the latest export transient key for replace-on-write.
     *
     * @var string
     */
    public const POINTER_META_KEY = 'updatronix_export_last_key';

    /**
     * Mint a new transient key bound to `(site_id, user_id)`.
     *
     * @since 1.1.0
     *
     * @param int $site_id Blog ID.
     * @param int $user_id User ID.
     * @return string Key matching `/^updatronix_log_export_\d+_\d+_[a-f0-9]{32}$/`.
     */
    public static function generate_key(int $site_id, int $user_id): string {
        return sprintf(
            'updatronix_log_export_%d_%d_%s',
            max(1, $site_id),
            max(1, $user_id),
            bin2hex(random_bytes(16))
        );
    }

    /**
     * Replace the user's export transient pointer (delete prior key best-effort).
     *
     * @since 1.1.0
     *
     * @param int                  $user_id User owning the export.
     * @param string               $new_key New transient key ({@see self::generate_key()}).
     * @param array<string, mixed> $payload Serializable payload (TTL {@see Updatronix_Export::TRANSIENT_TTL}).
     * @return true|\WP_Error
     */
    public static function replace(int $user_id, string $new_key, array $payload): bool|\WP_Error {
        if ($user_id <= 0) {
            return new WP_Error('internal', '', ['status' => 500]);
        }

        if (!preg_match('/^updatronix_log_export_\d+_\d+_[a-f0-9]{32}$/', $new_key)) {
            return new WP_Error('internal', '', ['status' => 500]);
        }

        $prior = get_user_meta($user_id, self::POINTER_META_KEY, true);
        $prior = is_string($prior) ? $prior : '';

        if ($prior !== '' && preg_match('/^updatronix_log_export_\d+_\d+_[a-f0-9]{32}$/', $prior)) {
            $deleted = delete_transient($prior);
            if (!$deleted && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated diagnostic only.
                error_log('[updatronix] export transient delete prior returned false');
            }
        }

        if (!set_transient($new_key, $payload, Updatronix_Export::TRANSIENT_TTL)) {
            return new WP_Error('internal', '', ['status' => 500]);
        }

        $updated = update_user_meta($user_id, self::POINTER_META_KEY, $new_key);
        if ($updated === false) {
            delete_transient($new_key);

            return new WP_Error('internal', '', ['status' => 500]);
        }

        return true;
    }
}
