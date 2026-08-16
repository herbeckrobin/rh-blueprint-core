<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

/**
 * Was ein Modul von einem anderen braucht, und was es nur empfiehlt.
 *
 * Bisher konnte ein Modul nur sagen "mit X wäre hier mehr möglich" (siehe
 * AddonHints). Das reicht nicht für den Fall, der wirklich weh tut: eine
 * Funktion, die ohne das andere Modul gar nicht läuft. Wer den Shop
 * installiert und keine E-Mail-Anbindung hat, bekommt keine
 * Bestellbestätigung. Nichts bricht sichtbar, es kommt nur nie eine Mail an.
 *
 * Deshalb zwei Stufen, mit verschiedenen Folgen:
 *
 *   braucht    Ohne das andere Modul fehlt eine Funktion. Deutlich sichtbar
 *              oben im Reiter, nicht wegklickbar, mit Knopf zum Nachholen.
 *              Ein Modul kann zusätzlich selbst nachsehen und die betroffene
 *              Stelle sperren statt sie tot anzubieten.
 *
 *   empfiehlt  Es ginge auch ohne, wäre aber besser. Bleibt die dezente Zeile
 *              von AddonHints, wegklickbar.
 *
 * Bewusst keine harte Sperre beim Aktivieren: ein Modul, das sich weigert zu
 * starten, weil ein anderes fehlt, ist auf einer Kundenseite schlimmer als
 * eines, das ohne die eine Funktion läuft und das sagt.
 */
final class Dependencies
{
    /**
     * Alle angemeldeten Abhängigkeiten.
     *
     * Ein Eintrag: welches Modul (`module`) braucht welches andere (`needs`),
     * wofür (`for`), auf welchem Reiter das gemeldet wird (`tab`), und ob es
     * eine Voraussetzung oder eine Empfehlung ist (`required`).
     *
     * @return array<int, array{module: string, needs: string, for: string, tab: string, required: bool}>
     */
    public static function all(): array
    {
        /**
         * Hier meldet ein Modul an, was es von einem anderen braucht.
         *
         * @param array<int, array<string, mixed>> $deps
         */
        $deps = apply_filters('rh-blueprint/dependencies', []);

        if (! is_array($deps)) {
            return [];
        }

        $sauber = [];

        foreach ($deps as $d) {
            if (! is_array($d) || ! isset($d['module'], $d['needs'], $d['for'])) {
                continue;
            }

            $sauber[] = [
                'module' => (string) $d['module'],
                'needs' => (string) $d['needs'],
                'for' => (string) $d['for'],
                'tab' => (string) ($d['tab'] ?? ''),
                'required' => (bool) ($d['required'] ?? true),
            ];
        }

        return $sauber;
    }

    /**
     * Läuft dieses Modul gerade?
     */
    public static function active(string $slug): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($slug . '/' . $slug . '.php');
    }

    /**
     * Was diesem Modul fehlt.
     *
     * Für Module, die selbst darauf reagieren wollen: eine Funktion sperren,
     * einen Hinweis an der betroffenen Stelle setzen, einen Versand
     * unterlassen. Besser als eine Schaltfläche, die nichts tut.
     *
     * @return array<int, array{module: string, needs: string, for: string, tab: string, required: bool}>
     */
    public static function missing(string $module, bool $onlyRequired = true): array
    {
        $fehlt = [];

        foreach (self::all() as $d) {
            if ($d['module'] !== $module) {
                continue;
            }

            if ($onlyRequired && ! $d['required']) {
                continue;
            }

            if (self::active($d['needs'])) {
                continue;
            }

            $fehlt[] = $d;
        }

        return $fehlt;
    }

    /**
     * Kurze Frage für den Alltag: kann ich diese Funktion anbieten?
     */
    public static function satisfied(string $module): bool
    {
        return self::missing($module) === [];
    }

    public function boot(): void
    {
        add_action('rh-blueprint/settings/tab_content_before', [$this, 'render'], 1);
    }

    /**
     * Der Hinweis auf ein fehlendes Pflicht-Modul, oben im betroffenen Reiter.
     */
    public function render(string $tab): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        foreach (self::all() as $d) {
            if (! $d['required'] || $d['tab'] !== $tab || self::active($d['needs'])) {
                continue;
            }

            $this->zeile($d);
        }
    }

    /**
     * @param array{module: string, needs: string, for: string, tab: string, required: bool} $d
     */
    private function zeile(array $d): void
    {
        $installiert = file_exists(WP_PLUGIN_DIR . '/' . $d['needs'] . '/' . $d['needs'] . '.php');
        $label = $this->label($d['needs']);

        echo '<div class="rhbp-callout rhbp-callout--warn rhbp-dep">';
        echo Ui::icon('warn', 'sm');

        echo '<span class="rhbp-dep__text">';
        printf(
            /* translators: 1: was ohne das Modul fehlt, 2: Name des fehlenden Moduls */
            esc_html__('%1$s braucht das Modul %2$s. Solange es fehlt, passiert an dieser Stelle nichts.', 'rh-blueprint-core'),
            '<strong>' . esc_html($d['for']) . '</strong>',
            '<strong>' . esc_html($label) . '</strong>'
        );
        echo '</span>';

        // Derselbe Weg wie beim Ghost-Tab: nachfragen, installieren, aktivieren.
        // Die Attributnamen kommen aus dem Skript in SuitePage, nicht raten.
        printf(
            '<button type="button" class="rhbp-btn rhbp-btn--primary" data-rhbp-module="%s" data-rhbp-state="%s" data-rhbp-label="%s">%s</button>',
            esc_attr($d['needs']),
            $installiert ? 'inactive' : 'missing',
            esc_attr($label),
            esc_html($installiert
                ? __('Jetzt aktivieren', 'rh-blueprint-core')
                : __('Jetzt installieren', 'rh-blueprint-core'))
        );

        echo '</div>';
    }

    /**
     * Der Anzeigename eines Moduls, wie ihn die Suite-Liste kennt.
     */
    private function label(string $slug): string
    {
        foreach ((new SuitePage())->modules() as $m) {
            if ($m['slug'] === $slug) {
                return $m['label'];
            }
        }

        return $slug;
    }
}
