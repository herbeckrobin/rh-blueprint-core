<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Hinweise auf ergänzende Module, dort wo sie etwas nützen.
 *
 * Die Ghost-Tabs zeigen, welche Module es gibt. Das beantwortet aber nicht die
 * Frage, die im Moment zählt: wer nur den Shop installiert hat, sieht in der
 * Tab-Leiste "E-Mail" ausgegraut und weiss trotzdem nicht, dass genau dieses
 * Modul die Sammelberichte des Shops verschicken würde.
 *
 * Ein Modul meldet deshalb an, was es könnte, wenn ein anderes da wäre. Daraus
 * wird eine schmale Zeile am Ende des jeweiligen Tabs.
 *
 * Zwei Regeln, damit das nicht zur Werbefläche wird:
 *
 *   1. Der Hinweis nennt den Nutzen, nicht das Produkt. "Sammelberichte
 *      übernimmt das E-Mail-Modul" statt "Installiere rh-smtp".
 *   2. Er lässt sich wegklicken, und zwar dauerhaft. Wer sich einmal dagegen
 *      entschieden hat, will nicht bei jedem Besuch neu gefragt werden.
 */
final class AddonHints
{
    private const DISMISS_META = 'rhbp_dismissed_hints';
    private const DISMISS_ACTION = 'rhbp_dismiss_hint';

    public function boot(): void
    {
        add_action('rh-blueprint/settings/tab_content_after', [$this, 'render']);
        add_action('admin_post_' . self::DISMISS_ACTION, [$this, 'dismiss']);
    }

    /**
     * Alle gemeldeten Hinweise.
     *
     * @return array<int, array{tab: string, module: string, benefit: string}>
     */
    public static function all(): array
    {
        /**
         * Ein Modul meldet hier an, wofür es ein anderes gebrauchen könnte.
         *
         * @param array<int, array{tab: string, module: string, benefit: string}> $hints
         */
        $hints = apply_filters('rh-blueprint/addon_hints', []);

        return is_array($hints) ? $hints : [];
    }

    public function render(string $tab): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $dismissed = $this->dismissed();

        foreach (self::all() as $hint) {
            if (($hint['tab'] ?? '') !== $tab) {
                continue;
            }

            $module = (string) ($hint['module'] ?? '');
            $benefit = (string) ($hint['benefit'] ?? '');

            if ($module === '' || $benefit === '' || in_array($module, $dismissed, true)) {
                continue;
            }

            if ($this->isActive($module)) {
                continue;
            }

            $this->renderHint($tab, $module, $benefit);
        }
    }

    private function renderHint(string $tab, string $module, string $benefit): void
    {
        $label = $this->moduleLabel($module);

        echo '<p class="rhbp-addon-hint">';
        echo '<span class="rhbp-addon-hint__text">' . esc_html($benefit) . ' ';
        printf(
            /* translators: %s: Name des ergänzenden Moduls */
            esc_html__('Dafür ist das Modul %s zuständig, es ist hier noch nicht aktiv.', 'rh-blueprint-core'),
            '<strong>' . esc_html($label) . '</strong>'
        );
        echo '</span> ';

        if (current_user_can('install_plugins')) {
            // Zustand mitgeben: das Installer-JS des Core unterscheidet daran,
            // ob es installieren oder nur aktivieren muss.
            printf(
                '<button type="button" class="rhbp-link" data-rhbp-module="%s" data-rhbp-label="%s" data-rhbp-state="%s">%s</button> ',
                esc_attr($module),
                esc_attr($label),
                esc_attr($this->isInstalled($module) ? 'inactive' : 'missing'),
                esc_html__('Jetzt hinzufügen', 'rh-blueprint-core')
            );
        }

        printf(
            '<a class="rhbp-addon-hint__dismiss" href="%s">%s</a>',
            esc_url(wp_nonce_url(
                admin_url('admin-post.php?action=' . self::DISMISS_ACTION . '&module=' . rawurlencode($module) . '&tab=' . rawurlencode($tab)),
                self::DISMISS_ACTION
            )),
            esc_html__('Nicht mehr anzeigen', 'rh-blueprint-core')
        );

        echo '</p>';
    }

    public function dismiss(): void
    {
        check_admin_referer(self::DISMISS_ACTION);

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Fehlende Berechtigung.', 'rh-blueprint-core'));
        }

        $module = isset($_GET['module']) ? sanitize_key((string) wp_unslash($_GET['module'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';

        if ($module !== '') {
            $dismissed = $this->dismissed();
            $dismissed[] = $module;
            update_user_meta(get_current_user_id(), self::DISMISS_META, array_values(array_unique($dismissed)));
        }

        wp_safe_redirect(admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . ($tab !== '' ? '&tab=' . $tab : '')));
        exit;
    }

    /**
     * @return array<int, string>
     */
    private function dismissed(): array
    {
        $stored = get_user_meta(get_current_user_id(), self::DISMISS_META, true);

        return is_array($stored) ? array_map('strval', $stored) : [];
    }

    private function isActive(string $slug): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($slug . '/' . $slug . '.php');
    }

    private function isInstalled(string $slug): bool
    {
        return file_exists(WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php');
    }

    private function moduleLabel(string $slug): string
    {
        foreach ((new SuitePage())->modules() as $module) {
            if (($module['slug'] ?? '') === $slug) {
                return (string) $module['label'];
            }
        }

        return $slug;
    }
}
