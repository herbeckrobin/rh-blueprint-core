<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

/**
 * Räumt das WordPress-Dashboard auf: entfernt die Default-Widgets und das
 * Welcome-Panel. Die Liste ist über `rh-blueprint/dashboard/remove` filterbar.
 */
final class DashboardCleanup
{
    public function boot(): void
    {
        add_action('wp_dashboard_setup', [$this, 'removeWidgets'], 999);
        add_action('admin_head-index.php', [$this, 'hideWelcomePanel']);
    }

    public function removeWidgets(): void
    {
        /** @var array<int, array{0: string, 1: string, 2: string}> $widgets */
        $widgets = apply_filters('rh-blueprint/dashboard/remove', [
            ['dashboard_activity', 'dashboard', 'normal'],
            ['dashboard_right_now', 'dashboard', 'normal'],
            ['dashboard_quick_press', 'dashboard', 'side'],
            ['dashboard_recent_drafts', 'dashboard', 'side'],
            ['dashboard_primary', 'dashboard', 'side'],
            ['dashboard_secondary', 'dashboard', 'side'],
            ['dashboard_site_health', 'dashboard', 'normal'],
            ['dashboard_php_nag', 'dashboard', 'normal'],
        ]);

        foreach ($widgets as $widget) {
            remove_meta_box($widget[0], $widget[1], $widget[2]);
        }

        remove_action('welcome_panel', 'wp_welcome_panel');
    }

    public function hideWelcomePanel(): void
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            update_user_meta($userId, 'show_welcome_panel', 0);
        }
    }
}
