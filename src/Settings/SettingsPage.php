<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Settings;

/**
 * Die geteilte "RH Blueprint" Settings-Page unter Einstellungen.
 *
 * Rendert die von allen Plugins angemeldeten Tabs. Pro Tab entweder ein
 * Settings-Form (wenn Gruppen registriert sind) oder Custom-Content über die
 * Hooks `rh-blueprint/settings/tab_content_before|after`. Jeder Tab hat seine
 * eigene Option-Group, damit ein Save nicht die Options anderer Tabs leert.
 */
final class SettingsPage
{
    public const MENU_SLUG = 'rh-blueprint';
    public const CAPABILITY = 'manage_options';

    public function __construct(private readonly SettingsHub $hub)
    {
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'options-general.php',
            __('RH Blueprint', 'rh-blueprint-core'),
            __('RH Blueprint', 'rh-blueprint-core'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::MENU_SLUG) {
            return;
        }

        $core = \rh_blueprint();
        $version = $core->version();

        wp_enqueue_style(
            'rh-blueprint-settings',
            $core->assetUrl('assets/settings.css'),
            ['dashicons'],
            $core->assetVersion('assets/settings.css', $version)
        );

        wp_enqueue_script(
            'rh-blueprint-settings',
            $core->assetUrl('assets/settings.js'),
            [],
            $core->assetVersion('assets/settings.js', $version),
            true
        );
    }

    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $tabs = $this->hub->tabs();
        $activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : (string) array_key_first($tabs);

        if (! isset($tabs[$activeTab])) {
            $activeTab = (string) array_key_first($tabs);
        }

        echo '<div class="wrap rhbp-settings" data-active-tab="' . esc_attr($activeTab) . '">';

        $this->renderHeader();
        $this->renderToolbar();
        $this->renderTabs($tabs, $activeTab);

        foreach ($tabs as $tabId => $tabLabel) {
            $isActive = $tabId === $activeTab;
            printf(
                '<div class="rhbp-tab-panel" data-tab-panel="%1$s" %2$s>',
                esc_attr($tabId),
                $isActive ? '' : 'hidden'
            );

            /**
             * Ganz oben im Tab-Panel, vor dem Settings-Form.
             * Eignet sich für Erfolgs-/Fehlermeldungen von admin-post-Handlern.
             */
            do_action('rh-blueprint/settings/tab_content_before', $tabId);

            $hasGroups = false;
            foreach ($this->hub->groups() as $group) {
                if ($group->tab() !== $tabId) {
                    continue;
                }
                $hasGroups = true;
                break;
            }

            if ($hasGroups) {
                echo '<form action="' . esc_url(admin_url('options.php')) . '" method="post" class="rhbp-form">';
                settings_fields(SettingsHub::optionGroupForTab($tabId));
                do_settings_sections('rh-blueprint-' . $tabId);
                submit_button(__('Änderungen speichern', 'rh-blueprint-core'));
                echo '</form>';
            } else {
                /**
                 * Ohne Gruppen muss ein tab_content_after-Hook eigenen Content liefern,
                 * sonst zeigen wir den Empty-State.
                 */
                ob_start();
                do_action('rh-blueprint/settings/tab_content_after', $tabId);
                $customContent = (string) ob_get_clean();

                if (trim($customContent) === '') {
                    printf(
                        '<div class="rhbp-empty">%s</div>',
                        esc_html__('Noch keine Einstellungen in diesem Bereich.', 'rh-blueprint-core')
                    );
                } else {
                    echo $customContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                echo '</div>';
                continue;
            }

            /**
             * Innerhalb eines Tab-Panels NACH dem Settings-Form.
             * Hier haken Module eigene Forms / Cards ein (z.B. DB-Tools, Sync-Peers).
             */
            do_action('rh-blueprint/settings/tab_content_after', $tabId);

            echo '</div>';
        }

        echo '</div>';
    }

    private function renderHeader(): void
    {
        echo '<div class="rhbp-settings__header">';
        echo '<div class="rhbp-settings__logo" aria-hidden="true">';
        echo '<span class="dashicons dashicons-layout"></span>';
        echo '</div>';
        echo '<div class="rhbp-settings__title">';
        echo '<h1>' . esc_html__('RH Blueprint', 'rh-blueprint-core') . '</h1>';
        echo '<p>' . esc_html__('Zentrale Steuerung der rh-blueprint Module.', 'rh-blueprint-core') . '</p>';
        echo '</div>';
        printf(
            '<span class="rhbp-settings__version">v%s</span>',
            esc_html(\rh_blueprint()->version())
        );
        echo '</div>';
    }

    private function renderToolbar(): void
    {
        echo '<div class="rhbp-settings__toolbar">';
        echo '<div class="rhbp-search">';
        printf(
            '<input type="search" id="rhbp-search-input" placeholder="%s" autocomplete="off" />',
            esc_attr__('Einstellungen durchsuchen…', 'rh-blueprint-core')
        );
        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<string, string> $tabs
     */
    private function renderTabs(array $tabs, string $activeTab): void
    {
        echo '<nav class="rhbp-tabs" aria-label="' . esc_attr__('Einstellungs-Kategorien', 'rh-blueprint-core') . '">';
        foreach ($tabs as $tabId => $tabLabel) {
            $url = add_query_arg([
                'page' => self::MENU_SLUG,
                'tab' => $tabId,
            ], admin_url('options-general.php'));

            printf(
                '<a href="%1$s" class="nav-tab %2$s" data-tab="%3$s">%4$s</a>',
                esc_url($url),
                $tabId === $activeTab ? 'nav-tab-active' : '',
                esc_attr($tabId),
                esc_html($tabLabel)
            );
        }
        echo '</nav>';
    }
}
