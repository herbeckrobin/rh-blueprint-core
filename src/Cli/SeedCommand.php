<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Cli;

/**
 * WP-CLI-Command zum deklarativen Seeden der Suite-Konfiguration.
 *
 * Provisioning für White-Label-Sites: eine JSON-Config beschreibt die
 * Settings-Gruppen, der Command schreibt sie über die `rhbp_update_settings()`-
 * Helper (Merge, idempotent). Siehe ADR 0001.
 *
 * Config-Form:
 *   {
 *     "seo_business": { "name": "...", "locality": "Heilbronn" },
 *     "hardening":    { "disable_xmlrpc": true }
 *   }
 */
final class SeedCommand
{
    /**
     * Seedet die Suite-Konfiguration aus einer JSON-Datei.
     *
     * ## OPTIONS
     *
     * <file>
     * : Pfad zur JSON-Config.
     *
     * [--dry-run]
     * : Zeigt die geplanten Änderungen, ohne zu schreiben.
     *
     * ## EXAMPLES
     *
     *     wp rh seed config.json
     *     wp rh seed config.json --dry-run
     *
     * @param array<int, string>    $args
     * @param array<string, mixed>  $assoc
     */
    public function __invoke(array $args, array $assoc): void
    {
        $file = $args[0] ?? '';

        if ($file === '' || ! is_readable($file)) {
            \WP_CLI::error(sprintf('Config-Datei nicht lesbar: %s', $file));
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            \WP_CLI::error('Config ist kein gültiges JSON-Objekt.');
        }

        $dryRun = isset($assoc['dry-run']);
        $groupCount = 0;
        $fieldCount = 0;

        foreach ($data as $groupId => $values) {
            if (! is_string($groupId) || ! is_array($values) || $values === []) {
                \WP_CLI::warning(sprintf('Übersprungen (kein gültiges Gruppen-Objekt): %s', (string) $groupId));
                continue;
            }

            $clean = [];
            foreach ($values as $field => $value) {
                if (! is_string($field)) {
                    continue;
                }
                $clean[$field] = $value;
                \WP_CLI::log(sprintf('  %s[%s] = %s', $groupId, $field, self::format($value)));
            }

            if ($clean === []) {
                continue;
            }

            if (! $dryRun) {
                rhbp_update_settings($groupId, $clean);
            }

            $groupCount++;
            $fieldCount += count($clean);
        }

        $summary = sprintf('%d Gruppen, %d Felder', $groupCount, $fieldCount);
        \WP_CLI::success($dryRun ? "Dry-Run: $summary würden geschrieben." : "$summary geseedet.");
    }

    private static function format(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) wp_json_encode($value);
    }
}
