<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Branding;

/**
 * Plugin-Liste im Branding: Name, Autor, URIs und Beschreibungs-Suffix aller
 * rh-Module, erkannt am Update-URI auf das Suite-Repo. Ordner, Slugs und
 * Textdomains bleiben unberührt, nur die angezeigten Header-Werte ändern sich.
 */
final class PluginList
{
    private const REPO_PREFIX = 'github.com/herbeckrobin/';

    public function boot(): void
    {
        add_filter('all_plugins', [$this, 'filter']);
        add_filter('plugin_row_meta', [$this, 'rowMeta'], 20, 3);
    }

    /**
     * "Details anzeigen" setzt WordPress selbst, sobald die Update-Daten einen
     * Slug tragen (also erst nach all_plugins). Der Dialog zeigt die Herkunft,
     * darum fliegt der Link bei aktivem Branding raus.
     *
     * @param array<int, string>   $meta
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public function rowMeta(array $meta, string $file, array $data): array
    {
        if (! Branding::isActive() || ! self::isSuitePlugin($file, $data)) {
            return $meta;
        }

        return array_values(array_filter(
            $meta,
            static fn($link) => ! str_contains((string) $link, 'open-plugin-details-modal')
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $plugins
     * @return array<string, array<string, mixed>>
     */
    public function filter(array $plugins): array
    {
        if (! Branding::isActive()) {
            return $plugins;
        }

        foreach ($plugins as $file => $data) {
            if (self::isSuitePlugin((string) $file, $data)) {
                $plugins[$file] = self::rebrand($data);
            }
        }

        return $plugins;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function isSuitePlugin(string $file, array $data): bool
    {
        if (! str_starts_with($file, 'rh-')) {
            return false;
        }

        foreach (['UpdateURI', 'PluginURI'] as $key) {
            if (str_contains((string) ($data[$key] ?? ''), self::REPO_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function rebrand(array $data): array
    {
        $brand = Branding::get();

        foreach (['Name', 'Title'] as $key) {
            $value = (string) ($data[$key] ?? '');
            if (str_starts_with($value, 'RH ')) {
                $data[$key] = $brand['name'] . ' ' . substr($value, 3);
            }
        }

        $author = $brand['author'] !== '' ? $brand['author'] : $brand['name'];
        $data['Author'] = $author;
        $data['AuthorName'] = $author;
        $data['AuthorURI'] = $brand['author_uri'];
        $data['PluginURI'] = $brand['author_uri'];

        $description = trim(str_replace(Branding::DESCRIPTION_SUFFIX, '', (string) ($data['Description'] ?? '')));
        if ($brand['description_suffix'] !== '') {
            $description .= ' ' . $brand['description_suffix'];
        }
        $data['Description'] = $description;

        return $data;
    }
}
