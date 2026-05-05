<?php

/**
 * Contextual admin notices when Delay updates is enabled on Updates, Plugins, and Themes screens.
 *
 * Clarifies how countdown text on those screens relates to delayed automatic installs and Update logs.
 *
 * @package updatronix
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_notices', 'updatronix_render_delay_context_admin_notice', 12);
/**
 * Prints an informational notice when delayed automatic updates are active.
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
            /* translators: %d: full days to wait after WordPress first sees an update (1–365). */
            _n(
                'Delayed updates are on in Updatronix: WordPress waits %d full day after it first sees an update before installing it automatically.',
                'Delayed updates are on in Updatronix: WordPress waits %d full days after it first sees an update before installing it automatically.',
                $days,
                'updatronix'
            ),
            $days
        )
    );
    echo '</p><p>';
    echo esc_html__(
        'Countdowns on this screen show when the next automatic update check may run, not when a delayed install happens. Each update keeps its own timer. Details stay in Update logs.',
        'updatronix'
    );
    echo '</p>';

    if (current_user_can(UPDATRONIX_CAP_MANAGE)) {
        $schedule_u = esc_url(admin_url('tools.php?page=updatronix&tab=schedule'));
        $logs_u = esc_url(admin_url('tools.php?page=updatronix&tab=logs'));
        $linked = sprintf(
            /* translators: %1$s: Schedule tab URL. %2$s: Update logs URL. */
            __('Change delay settings on the <a href="%1$s">Schedule tab</a> or open <a href="%2$s">Update logs</a> for details.', 'updatronix'),
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
            'Ask a site administrator who can open Tools → Updatronix to change delay settings or review Update logs.',
            'updatronix'
        );
        echo '</p>';
    }

    echo '</div>';
}

/**
 * Whether delay controls from settings should gate automatic updates (matches {@see Updatronix_AutoUpdateDelay}).
 *
 * @return bool True when delay is enabled with a positive day count.
 */
function updatronix_delay_updates_is_active_from_settings(): bool {
    $delay = updatronix_get_settings()['schedule']['delay_updates'];

    return !empty($delay['enabled']) && (int) $delay['delay_value'] > 0;
}

/**
 * Whether the current user may see delay notices on this admin screen.
 *
 * @param string $screen_id Screen ID from `WP_Screen::$id`.
 * @return bool True when the user has the capability required for that screen.
 */
function updatronix_delay_notice_user_can_see_screen(string $screen_id): bool {
    return match ($screen_id) {
        'update-core' => current_user_can('update_core'),
        'plugins' => current_user_can('update_plugins'),
        'themes' => current_user_can('update_themes'),
        default => false,
    };
}
