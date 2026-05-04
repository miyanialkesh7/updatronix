<?php

/**
 * Contextual admin notices when Delay updates is enabled — native Updates / Plugins / Themes screens.
 *
 * Explains the difference between Core’s “next automatic update check” timing and Updatronix’s
 * per-offer soak (see .cursor/notes/2026-05-04-implementation-note-delay-updates-admin-messaging.md).
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_notices', 'updatronix_render_delay_context_admin_notice', 12);
/**
 * Print an informational notice on core update-related screens when soak delay is active.
 *
 * @return void
 */
function updatronix_render_delay_context_admin_notice(): void {
    if (!updatronix_delay_updates_is_active_from_settings()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen instanceof WP_Screen || !updatronix_delay_notice_user_can_see_screen($screen->id)) {
        return;
    }

    $delay = updatronix_get_settings()['schedule']['delay_updates'];
    $days = max(1, min(365, (int) $delay['delay_value']));

    echo '<div class="notice notice-info updatronix-delay-context-notice"><p>';
    echo esc_html(
        sprintf(
            /* translators: %d: configured full-day soak count (1–365). */
            _n(
                'Updatronix has automatic update delay enabled: new releases must mature for %d full day after first detection before they may install in the background.',
                'Updatronix has automatic update delay enabled: new releases must mature for %d full days after first detection before they may install in the background.',
                $days,
                'updatronix'
            ),
            $days
        )
    );
    echo '</p><p>';
    echo esc_html__(
        'The countdown WordPress shows below refers to when the next background update check may run. Delay applies separately to each update offer, so an install can happen on a later run. Check the Updatronix activity log for deferred items.',
        'updatronix'
    );
    echo '</p>';

    if (current_user_can(UPDATRONIX_CAP_MANAGE)) {
        $schedule_u = esc_url(admin_url('tools.php?page=updatronix&tab=schedule'));
        $logs_u = esc_url(admin_url('tools.php?page=updatronix&tab=logs'));
        $linked = sprintf(
            /* translators: %1$s: Schedule tab URL. %2$s: Update logs URL. */
            __('You can <a href="%1$s">open the Schedule tab</a> to change delay settings, or <a href="%2$s">view update logs</a> for deferral details.', 'updatronix'),
            $schedule_u,
            $logs_u
        );
        echo '<p>' . wp_kses($linked, [
            'a' => [
                'href' => true,
            ],
        ]) . '</p>';
    } else {
        echo '<p>';
        echo esc_html__(
            'Ask a site administrator who can access Updatronix under Tools to review delay settings or the update log.',
            'updatronix'
        );
        echo '</p>';
    }

    echo '</div>';
}

/**
 * @return bool True when delay controls should affect automatic updates (aligned with AutoUpdateDelay gate).
 */
function updatronix_delay_updates_is_active_from_settings(): bool {
    $delay = updatronix_get_settings()['schedule']['delay_updates'];

    return !empty($delay['enabled']) && (int) $delay['delay_value'] > 0;
}

/**
 * @param string $screen_id WP_Screen::$id.
 */
function updatronix_delay_notice_user_can_see_screen(string $screen_id): bool {
    return match ($screen_id) {
        'update-core' => current_user_can('update_core'),
        'plugins' => current_user_can('update_plugins'),
        'themes' => current_user_can('update_themes'),
        default => false,
    };
}
