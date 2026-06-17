<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Suite-Erweiterung über die Tab-Leiste.
 *
 * Module der rh-blueprint Kollektion, die NICHT installiert (oder installiert aber
 * inaktiv) sind, erscheinen als ausgegraute "Ghost"-Tabs neben den echten Tabs.
 * Ein Klick fragt nach und installiert das Modul per Klick aus dem GitHub-Release
 * (bzw. aktiviert ein vorhandenes), ohne ins Plugin-Fenster zu wechseln.
 *
 * So sieht man auch mit nur einem installierten Modul die ganze Suite und kann sie
 * mit wenigen Klicks erweitern. Sicherheit: install_plugins/activate_plugins-Cap
 * plus Nonce auf den admin-post-Handlern.
 */
final class SuitePage
{
    private const GH_OWNER = 'herbeckrobin';
    private const ACTION_INSTALL = 'rhbp_install_module';
    private const ACTION_ACTIVATE = 'rhbp_activate_module';
    private const NONCE = 'rhbp_suite_nonce';

    public function boot(): void
    {
        add_filter('rh-blueprint/settings/ghost_tabs', [$this, 'ghostTabs']);
        add_action('admin_footer', [$this, 'renderInstaller']);
        add_action('admin_post_' . self::ACTION_INSTALL, [$this, 'handleInstall']);
        add_action('admin_post_' . self::ACTION_ACTIVATE, [$this, 'handleActivate']);
    }

    /**
     * Die komplette Suite (installierbare Plugins; Libraries gehören nicht dazu).
     *
     * @return array<int, array{slug: string, label: string, desc: string}>
     */
    public function modules(): array
    {
        $modules = [
            ['slug' => 'rh-hardening', 'label' => __('Sicherheit', 'rh-blueprint-core'), 'desc' => __('Security-Härtung', 'rh-blueprint-core')],
            ['slug' => 'rh-seo', 'label' => __('SEO', 'rh-blueprint-core'), 'desc' => __('JSON-LD, Meta, Sitemap', 'rh-blueprint-core')],
            ['slug' => 'rh-motion', 'label' => __('Animationen', 'rh-blueprint-core'), 'desc' => __('Scroll-Animationen pro Block', 'rh-blueprint-core')],
            ['slug' => 'rh-editor', 'label' => __('Editor', 'rh-blueprint-core'), 'desc' => __('Inserter, Block-Kategorie, SVG', 'rh-blueprint-core')],
            ['slug' => 'rh-responsive', 'label' => __('Responsive', 'rh-blueprint-core'), 'desc' => __('Sichtbarkeit, Nav-Breakpoint', 'rh-blueprint-core')],
            ['slug' => 'rh-performance', 'label' => __('Performance', 'rh-blueprint-core'), 'desc' => __('LCP-Preload', 'rh-blueprint-core')],
            ['slug' => 'rh-blocks', 'label' => __('Blöcke', 'rh-blueprint-core'), 'desc' => __('Block-Bibliothek', 'rh-blueprint-core')],
            ['slug' => 'rh-monitor', 'label' => __('Monitoring', 'rh-blueprint-core'), 'desc' => __('Error-Tracking, Health', 'rh-blueprint-core')],
            ['slug' => 'rh-tracking', 'label' => __('Tracking', 'rh-blueprint-core'), 'desc' => __('Umami, GlitchTip', 'rh-blueprint-core')],
            ['slug' => 'rh-smtp', 'label' => __('SMTP', 'rh-blueprint-core'), 'desc' => __('Mail-Versand', 'rh-blueprint-core')],
            ['slug' => 'rh-login', 'label' => __('Login', 'rh-blueprint-core'), 'desc' => __('Login-Schutz', 'rh-blueprint-core')],
            ['slug' => 'rh-consent', 'label' => __('Consent', 'rh-blueprint-core'), 'desc' => __('Cookie-Banner', 'rh-blueprint-core')],
            ['slug' => 'rh-backup', 'label' => __('Backup', 'rh-blueprint-core'), 'desc' => __('Backup & Restore', 'rh-blueprint-core')],
            ['slug' => 'rh-sync', 'label' => __('Sync', 'rh-blueprint-core'), 'desc' => __('Peer-to-Peer Sync', 'rh-blueprint-core')],
        ];

        /** @var array<int, array{slug: string, label: string, desc: string}> $modules */
        $modules = apply_filters('rh-blueprint/suite/modules', $modules);

        return $modules;
    }

    /**
     * Nicht-aktive Module als Ghost-Tabs liefern (Filter aus SettingsPage::renderTabs).
     *
     * @param array<int, array{slug: string, label: string, state: string}> $ghosts
     * @return array<int, array{slug: string, label: string, state: string}>
     */
    public function ghostTabs(array $ghosts): array
    {
        $this->ensurePluginFns();

        foreach ($this->modules() as $module) {
            $file = $this->pluginFile($module['slug']);
            if (is_plugin_active($file)) {
                continue;
            }
            $installed = file_exists(WP_PLUGIN_DIR . '/' . $file);
            $ghosts[] = [
                'slug' => $module['slug'],
                'label' => $module['label'],
                'state' => $installed ? 'inactive' : 'missing',
            ];
        }

        return $ghosts;
    }

    /**
     * Verstecktes Formular + JS auf der Settings-Page: Klick auf einen Ghost-Tab
     * fragt nach und schickt Install/Activate ab.
     */
    public function renderInstaller(): void
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== SettingsPage::MENU_SLUG || ! current_user_can('install_plugins')) {
            return;
        }

        echo '<form id="rhbp-suite-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:none">';
        echo '<input type="hidden" name="action" value="">';
        echo '<input type="hidden" name="slug" value="">';
        echo '<input type="hidden" name="' . esc_attr(self::NONCE) . '" value="' . esc_attr(wp_create_nonce(self::NONCE)) . '">';
        echo '</form>';

        // Strings als JSON-Literale (wp_json_encode) statt esc_js: esc_js wandelt "
        // in &quot; um, das landet dann wörtlich im confirm()-Dialog (kaputt).
        $cfg = wp_json_encode([
            'missing' => __('Modul „%s" ist noch nicht installiert. Jetzt aus dem rh-blueprint Release installieren und aktivieren?', 'rh-blueprint-core'),
            'inactive' => __('Modul „%s" ist installiert, aber nicht aktiv. Jetzt aktivieren?', 'rh-blueprint-core'),
            'install' => self::ACTION_INSTALL,
            'activate' => self::ACTION_ACTIVATE,
            'working' => __('wird hinzugefügt …', 'rh-blueprint-core'),
        ]);

        $js = "(function(){"
            . "var cfg=" . $cfg . ";"
            . "var form=document.getElementById('rhbp-suite-form');"
            . "if(!form){return;}"
            . "document.querySelectorAll('.nav-tab.is-ghost').forEach(function(tab){"
            . "tab.addEventListener('click',function(e){"
            . "e.preventDefault();"
            . "var slug=tab.getAttribute('data-rhbp-module');"
            . "var state=tab.getAttribute('data-rhbp-state');"
            . "var label=tab.getAttribute('data-rhbp-label')||slug;"
            . "var tpl=(state==='inactive')?cfg.inactive:cfg.missing;"
            . "if(!window.confirm(tpl.replace('%s',label))){return;}"
            . "form.action.value=(state==='inactive')?cfg.activate:cfg.install;"
            . "form.slug.value=slug;"
            . "form.submit();"
            . "});});"
            . "})();";

        echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statisches JS + wp_json_encode-Literale
    }

    public function handleInstall(): void
    {
        $this->guard('install_plugins');
        $slug = $this->postSlug();
        $module = $this->moduleBySlug($slug);
        if ($module === null) {
            $this->finish(false, __('Unbekanntes Modul.', 'rh-blueprint-core'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $zip = 'https://github.com/' . self::GH_OWNER . '/' . $slug . '/releases/latest/download/' . $slug . '.zip';
        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
        $result = $upgrader->install($zip);

        if ($result !== true) {
            $this->finish(false, sprintf(__('Installation von "%s" fehlgeschlagen. Gibt es ein Release?', 'rh-blueprint-core'), $slug));
        }

        $activated = activate_plugin($this->pluginFile($slug));
        $msg = is_wp_error($activated)
            ? sprintf(__('"%s" installiert, Aktivierung fehlgeschlagen.', 'rh-blueprint-core'), $slug)
            : sprintf(__('"%s" installiert und aktiviert.', 'rh-blueprint-core'), $slug);
        $this->finish(! is_wp_error($activated), $msg);
    }

    public function handleActivate(): void
    {
        $this->guard('activate_plugins');
        $slug = $this->postSlug();
        if ($this->moduleBySlug($slug) === null) {
            $this->finish(false, __('Unbekanntes Modul.', 'rh-blueprint-core'));
        }

        $activated = activate_plugin($this->pluginFile($slug));
        $this->finish(
            ! is_wp_error($activated),
            is_wp_error($activated)
                ? sprintf(__('Aktivierung von "%s" fehlgeschlagen.', 'rh-blueprint-core'), $slug)
                : sprintf(__('"%s" aktiviert.', 'rh-blueprint-core'), $slug)
        );
    }

    private function guard(string $capability): void
    {
        $nonce = isset($_POST[self::NONCE]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE)) {
            wp_die(esc_html__('Sicherheitsprüfung fehlgeschlagen.', 'rh-blueprint-core'));
        }
        if (! current_user_can($capability)) {
            wp_die(esc_html__('Keine Berechtigung.', 'rh-blueprint-core'));
        }
        $this->ensurePluginFns();
    }

    private function postSlug(): string
    {
        return isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
    }

    /**
     * @return array{slug: string, label: string, desc: string}|null
     */
    private function moduleBySlug(string $slug): ?array
    {
        foreach ($this->modules() as $module) {
            if ($module['slug'] === $slug) {
                return $module;
            }
        }

        return null;
    }

    private function pluginFile(string $slug): string
    {
        return $slug . '/' . $slug . '.php';
    }

    private function ensurePluginFns(): void
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private function finish(bool $ok, string $message): void
    {
        set_transient('rhbp_suite_result_' . get_current_user_id(), ['ok' => $ok, 'message' => $message], 60);
        wp_safe_redirect(admin_url('admin.php?page=' . SettingsPage::MENU_SLUG));
        exit;
    }
}
