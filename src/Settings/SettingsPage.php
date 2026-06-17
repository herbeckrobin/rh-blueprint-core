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
        add_filter('submenu_file', [$this, 'highlightSubmenu']);
    }

    /**
     * Hebt den richtigen Untermenü-Eintrag hervor. Ohne diesen Filter markiert
     * WordPress beim `&tab=`-Pattern immer den ersten Eintrag (Allgemein).
     */
    public function highlightSubmenu(?string $submenuFile): ?string
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== self::MENU_SLUG) {
            return $submenuFile;
        }

        $tabs = $this->hub->tabs();
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
        if ($tab === '' || ! isset($tabs[$tab])) {
            return $submenuFile;
        }

        return $tab === (string) array_key_first($tabs) ? self::MENU_SLUG : self::MENU_SLUG . '&tab=' . $tab;
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('RH Blueprint', 'rh-blueprint-core'),
            __('RH Blueprint', 'rh-blueprint-core'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render'],
            $this->menuIcon(),
            58
        );

        // Ein Untermenü-Eintrag pro Tab. Der erste teilt sich den Slug mit dem
        // Top-Level-Eintrag (überschreibt dessen Default-Label).
        $first = true;
        foreach ($this->hub->tabs() as $tabId => $label) {
            add_submenu_page(
                self::MENU_SLUG,
                $label,
                $label,
                self::CAPABILITY,
                $first ? self::MENU_SLUG : self::MENU_SLUG . '&tab=' . $tabId,
                [$this, 'render']
            );
            $first = false;
        }
    }

    /**
     * Menü-Icon: das gebundelte Logo (assets/menu-icon.svg) als data-URL, sonst
     * eine Dashicon als Fallback.
     */
    private function menuIcon(): string
    {
        $svg = rtrim(\rh_blueprint()->dir(), '/') . '/assets/menu-icon.svg';
        if (is_readable($svg)) {
            $contents = (string) file_get_contents($svg);
            if ($contents !== '') {
                return 'data:image/svg+xml;base64,' . base64_encode($contents);
            }
        }

        return 'dashicons-layout';
    }

    public function enqueueAssets(string $hook): void
    {
        // Greift für die Top-Level-Page und alle Untermenü-Tabs (alle ?page=rh-blueprint).
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== self::MENU_SLUG) {
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

        // wp.media für die TYPE_MEDIA-Felder. Die Picker-Mechanik ist generisch
        // (data-rhbp-media), damit jedes Modul ein Bild-Feld nutzen kann.
        wp_enqueue_media();
        wp_add_inline_script('rh-blueprint-settings', $this->mediaPickerScript());
    }

    /**
     * Generische wp.media-Picker-Mechanik für alle TYPE_MEDIA-Felder.
     * Event-Delegation auf document, damit auch dynamisch eingefügte Felder greifen.
     */
    private function mediaPickerScript(): string
    {
        $title = esc_js(__('Bild wählen', 'rh-blueprint-core'));
        $button = esc_js(__('Übernehmen', 'rh-blueprint-core'));

        return <<<JS
(function(){
    if (typeof wp === 'undefined' || !wp.media) { return; }
    var frame = null, active = null;
    document.addEventListener('click', function(e){
        var select = e.target.closest('[data-rhbp-media] [data-rhbp-media-select]');
        var remove = e.target.closest('[data-rhbp-media] [data-rhbp-media-remove]');
        if (select) {
            e.preventDefault();
            active = select.closest('[data-rhbp-media]');
            frame = frame || wp.media({ title: '$title', multiple: false, library: { type: 'image' }, button: { text: '$button' } });
            frame.off('select');
            frame.on('select', function(){
                if (!active) { return; }
                var att = frame.state().get('selection').first().toJSON();
                active.querySelector('[data-rhbp-media-input]').value = att.id;
                var img = active.querySelector('[data-rhbp-media-preview]');
                img.src = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
                img.hidden = false;
                active.querySelector('[data-rhbp-media-remove]').hidden = false;
            });
            frame.open();
        }
        if (remove) {
            e.preventDefault();
            var wrap = remove.closest('[data-rhbp-media]');
            wrap.querySelector('[data-rhbp-media-input]').value = '';
            var preview = wrap.querySelector('[data-rhbp-media-preview]');
            preview.src = ''; preview.hidden = true;
            remove.hidden = true;
        }
    });
})();
JS;
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

        echo '<div class="wrap rhbp-settings rhbp-stage" data-active-tab="' . esc_attr($activeTab) . '">';

        $this->renderHeader();
        $this->renderSuiteNotice();
        $this->renderTabs($tabs, $activeTab);

        // Notice-Anker: WordPress verschiebt .notice-Elemente hinter dieses Marker.
        echo '<hr class="wp-header-end" />';

        echo '<div class="rhbp-body">';

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

        echo '</div>'; // .rhbp-body
        echo '</div>'; // .rhbp-stage
    }

    private function renderHeader(): void
    {
        echo '<div class="rhbp-settings__header">';
        echo '<div class="rhbp-settings__logo" aria-hidden="true">';
        echo $this->logoMarkup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        echo '<div class="rhbp-settings__title">';
        echo '<h1>' . esc_html__('RH Blueprint', 'rh-blueprint-core') . '</h1>';
        echo '<p>' . esc_html__('Zentrale Steuerung deiner rh-blueprint Module. Allgemeine Einstellungen, Backups und Sync an einem Ort.', 'rh-blueprint-core') . '</p>';
        echo '</div>';
        printf(
            '<span class="rhbp-settings__version">v%s</span>',
            esc_html(\rh_blueprint()->version())
        );
        echo '</div>';
    }

    /**
     * Logo fürs Header-Badge: das gebundelte SVG inline (einfärbbar), sonst eine Dashicon.
     */
    private function logoMarkup(): string
    {
        $svg = rtrim(\rh_blueprint()->dir(), '/') . '/assets/menu-icon.svg';
        if (is_readable($svg)) {
            $contents = (string) file_get_contents($svg);
            if ($contents !== '') {
                return $contents;
            }
        }

        return '<span class="dashicons dashicons-layout"></span>';
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
            ], admin_url('admin.php'));

            printf(
                '<a href="%1$s" class="nav-tab %2$s" data-tab="%3$s">%4$s</a>',
                esc_url($url),
                $tabId === $activeTab ? 'nav-tab-active' : '',
                esc_attr($tabId),
                esc_html($tabLabel)
            );
        }

        // Ghost-Tabs: nicht installierte/aktive Suite-Module als ausgegraute Tabs.
        // Ein Modul liefert sie über den Filter (SuitePage), Klick fragt + installiert.
        /** @var array<int, array{slug: string, label: string, state: string}> $ghosts */
        $ghosts = (array) apply_filters('rh-blueprint/settings/ghost_tabs', []);
        foreach ($ghosts as $ghost) {
            if (empty($ghost['slug']) || empty($ghost['label'])) {
                continue;
            }
            $ghostTitle = ($ghost['state'] ?? 'missing') === 'inactive'
                ? __('Installiert, aber nicht aktiv. Klicken zum Aktivieren.', 'rh-blueprint-core')
                : __('Noch nicht installiert. Klicken zum Hinzufügen.', 'rh-blueprint-core');
            printf(
                '<a href="#" class="nav-tab is-ghost" data-rhbp-module="%1$s" data-rhbp-state="%2$s" data-rhbp-label="%3$s" title="%4$s"><span class="rhbp-ghost-add" aria-hidden="true">+</span>%3$s</a>',
                esc_attr((string) $ghost['slug']),
                esc_attr((string) ($ghost['state'] ?? 'missing')),
                esc_attr((string) $ghost['label']),
                esc_attr($ghostTitle)
            );
        }

        echo '</nav>';
    }

    /**
     * Ergebnis einer Suite-Installation/-Aktivierung anzeigen (aus dem Transient).
     */
    private function renderSuiteNotice(): void
    {
        $result = get_transient('rhbp_suite_result_' . get_current_user_id());
        if (! is_array($result) || ! isset($result['message'])) {
            return;
        }
        delete_transient('rhbp_suite_result_' . get_current_user_id());

        printf(
            '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
            ! empty($result['ok']) ? 'notice-success' : 'notice-error',
            esc_html((string) $result['message'])
        );
    }
}
