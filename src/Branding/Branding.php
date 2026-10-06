<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Branding;

/**
 * White-Label: wie die Kollektion im Backend heißt und aussieht.
 *
 * Ein Satz Werte (Name, Icon, Untertitel, Version-Badge, Autor, Autor-URL,
 * Beschreibungs-Suffix) aus der Option `rhbp_branding`, danach der Filter
 * `rh-blueprint/branding`. Aktiv ist das Branding, sobald ein Name gesetzt ist.
 *
 * Die Option syncht per rh-sync mit (der LocalOptionGuard schützt nur
 * `rhbp_sync_%` und `rhbp_peers`). Gewollt: Stage und Live zeigen dieselbe Marke.
 *
 * Ohne Branding bleibt alles wie bisher, jeder Konsument fällt dann auf seinen
 * bisherigen Text zurück.
 */
final class Branding
{
    public const OPTION = 'rhbp_branding';

    /** Suffix, den alle Modul-Beschreibungen tragen und der ersetzt wird. */
    public const DESCRIPTION_SUFFIX = 'Teil der rh-blueprint Kollektion.';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{name: string, icon: string, subtitle: string, show_version: bool, author: string, author_uri: string, description_suffix: string}
     */
    public static function defaults(): array
    {
        return [
            'name' => '',
            'icon' => '',
            'subtitle' => '',
            'show_version' => true,
            'author' => '',
            'author_uri' => '',
            'description_suffix' => '',
        ];
    }

    /**
     * Aufgelöster Satz: Default, Option, Filter.
     *
     * @return array{name: string, icon: string, subtitle: string, show_version: bool, author: string, author_uri: string, description_suffix: string}
     */
    public static function get(): array
    {
        if (self::$cache === null) {
            $stored = get_option(self::OPTION, []);
            $data = array_merge(self::defaults(), is_array($stored) ? $stored : []);

            /**
             * Branding per Code setzen oder überschreiben, etwa aus einem Theme.
             *
             * @param array<string, mixed> $data
             */
            $data = (array) apply_filters('rh-blueprint/branding', $data);

            self::$cache = self::normalize($data);
        }

        return self::$cache;
    }

    public static function isActive(): bool
    {
        return self::get()['name'] !== '';
    }

    /**
     * Ein Wert, oder `$fallback`, solange kein Branding aktiv oder der Wert leer ist.
     */
    public static function value(string $key, string $fallback = ''): string
    {
        if (! self::isActive()) {
            return $fallback;
        }

        $value = self::get()[$key] ?? '';

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): void
    {
        update_option(self::OPTION, self::normalize(array_merge(self::defaults(), $data)), false);
        self::$cache = null;
    }

    /**
     * Typen festziehen und das Icon säubern. Läuft beim Speichern und nach dem
     * Filter, damit auch per Code gesetzte Werte in derselben Form ankommen.
     *
     * @param array<string, mixed> $data
     * @return array{name: string, icon: string, subtitle: string, show_version: bool, author: string, author_uri: string, description_suffix: string}
     */
    public static function normalize(array $data): array
    {
        return [
            'name' => sanitize_text_field((string) ($data['name'] ?? '')),
            'icon' => self::sanitizeIcon((string) ($data['icon'] ?? '')),
            'subtitle' => sanitize_text_field((string) ($data['subtitle'] ?? '')),
            'show_version' => (bool) ($data['show_version'] ?? true),
            'author' => sanitize_text_field((string) ($data['author'] ?? '')),
            'author_uri' => esc_url_raw((string) ($data['author_uri'] ?? '')),
            'description_suffix' => sanitize_text_field((string) ($data['description_suffix'] ?? '')),
        ];
    }

    /**
     * Icon ist entweder ein Dashicon-Name (`dashicons-art`) oder SVG-Markup.
     * SVG läuft durch eine enge Whitelist (Formen, keine Skripte, keine Links).
     */
    public static function sanitizeIcon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }

        if (preg_match('/^dashicons-[a-z0-9-]+$/', $icon) === 1) {
            return $icon;
        }

        if (stripos($icon, '<svg') === false) {
            return '';
        }

        $shape = ['fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true, 'opacity' => true, 'class' => true];
        $allowed = [
            'svg' => ['xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'class' => true, 'style' => true],
            'g' => $shape,
            'path' => $shape + ['d' => true],
            'circle' => $shape + ['cx' => true, 'cy' => true, 'r' => true],
            'ellipse' => $shape + ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true],
            'rect' => $shape + ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true],
            'line' => $shape + ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
            'polygon' => $shape + ['points' => true],
            'polyline' => $shape + ['points' => true],
            'title' => [],
        ];

        // Inhalt von script/style muss vorher weg, kses entfernt nur die Tags.
        $icon = (string) preg_replace('#<(script|style)\b[^>]*>[\s\S]*?</\1>#i', '', $icon);
        $clean = trim(wp_kses($icon, $allowed));

        // kses schreibt Attributnamen klein. Als data-URL (XML) zählt die
        // Schreibweise, ohne viewBox skaliert das Menü-Icon nicht.
        $clean = str_replace(' viewbox=', ' viewBox=', $clean);
        if (stripos($clean, '<svg') !== 0) {
            return '';
        }

        // Ohne Namensraum rendert das SVG als data-URL gar nicht.
        return str_contains($clean, 'xmlns=') ? $clean : (string) preg_replace('/^<svg/i', '<svg xmlns="http://www.w3.org/2000/svg"', $clean, 1);
    }

    /**
     * Icon fürs Admin-Menü: Dashicon-Name oder SVG als data-URL, sonst `$fallback`.
     */
    public static function menuIcon(string $fallback): string
    {
        $icon = self::value('icon');
        if ($icon === '') {
            return $fallback;
        }

        return str_starts_with($icon, 'dashicons-')
            ? $icon
            : 'data:image/svg+xml;base64,' . base64_encode($icon);
    }

    /**
     * Icon als Inline-Markup für Seitenkopf, sonst `$fallback`.
     */
    public static function iconMarkup(string $fallback): string
    {
        $icon = self::value('icon');
        if ($icon === '') {
            return $fallback;
        }

        return str_starts_with($icon, 'dashicons-')
            ? '<span class="dashicons ' . esc_attr($icon) . '"></span>'
            : $icon;
    }

    /** Nur für Tests: Cache verwerfen. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
