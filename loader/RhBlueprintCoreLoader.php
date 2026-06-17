<?php

/**
 * Version-Negotiation-Loader für den geteilten rh-blueprint Core.
 *
 * Diese Klasse ist die EINZIGE Infrastruktur, die in jedem Bundle byte-identisch
 * vorliegt. Sie wird per `require` (nicht Autoload) geladen, damit sie verfügbar
 * ist, bevor irgendein Core-Autoloader existiert. Der `class_exists`-Guard im
 * Entry-Point sorgt dafür, dass nur die zuerst geladene Kopie aktiv ist.
 *
 * DESHALB IST DIESE DATEI STABIL ZU HALTEN. Ändert sich ihre öffentliche API
 * zwischen Versionen, und gewinnt eine alte Kopie das class_exists-Rennen, bricht
 * die Negotiation. Neue Funktionalität gehört in den Core (src/), nicht hierher.
 *
 * Ablauf:
 *   1. Jedes Bundle ruft beim Composer-Include `declareVersion($version, $dir)`.
 *   2. Beim ersten Aufruf wird `loadLatest()` an `plugins_loaded` (Prio -10) gehängt.
 *   3. `loadLatest()` entdeckt ALLE gebündelten Cores über das Dateisystem,
 *      wählt die höchste Version und lädt deren `bootstrap.php`.
 *
 * Warum die Dateisystem-Entdeckung (Schritt 3) und nicht nur die Selbst-
 * Anmeldung aus Schritt 1: Composers files-autoload vergibt dem Entry-Point
 * `rh-blueprint-core.php` in JEDEM Bundle denselben Hash-Key und führt ihn pro
 * Request nur EINMAL aus (globaler Dedup). Also ruft nur das zuerst geladene
 * Plugin `declareVersion` auf, die Versionen der anderen Bundles blieben sonst
 * unsichtbar und die Negotiation wählte die Version des alphabetisch ersten
 * Plugins statt der höchsten. Bei gemischten Versionen (normaler Teil-Update-
 * Zustand) lud so ein älterer Core und ein neueres Modul fatalte.
 *
 * Plugins, die den Core nutzen, müssen NACH Prio -10 booten (Default 10 ist ok).
 */

declare(strict_types=1);

final class RhBlueprintCoreLoader
{
    /** @var array<string, string> Map Version => Verzeichnis-Pfad */
    private static array $versions = [];

    private static bool $hooked = false;

    private static string $winningVersion = '';

    private static string $winningDir = '';

    /**
     * Meldet eine im Request vorliegende Core-Version mit ihrem Pfad an.
     * Idempotent pro Version.
     */
    public static function declareVersion(string $version, string $dir): void
    {
        if ($version === '' || $dir === '') {
            return;
        }

        self::$versions[$version] = $dir;

        if (! self::$hooked) {
            self::$hooked = true;
            // Prio -10: vor den Plugins, die den Core konsumieren.
            add_action('plugins_loaded', [self::class, 'loadLatest'], -10);
        }
    }

    /**
     * Wählt die höchste angemeldete Version und lädt deren Bootstrap.
     * Wird genau einmal über den plugins_loaded-Hook aufgerufen.
     */
    public static function loadLatest(): void
    {
        if (self::$winningVersion !== '') {
            return;
        }

        self::discoverBundles();

        if (self::$versions === []) {
            return;
        }

        $version = self::pickLatest(array_keys(self::$versions));
        self::$winningVersion = $version;
        self::$winningDir = self::$versions[$version];

        require_once self::$winningDir . '/bootstrap.php';
    }

    /**
     * Entdeckt alle im Request vorliegenden Core-Bundles über das Dateisystem
     * und meldet sie an. Notwendig, weil Composers files-autoload den Entry-Point
     * pro Request nur einmal ausführt (siehe Klassen-Doc), die Selbst-Anmeldung
     * also nur die Version des zuerst geladenen Plugins sieht.
     *
     * Scannt die gebündelten `version.php` aller Plugins. Die Selbst-Anmeldung
     * aus `declareVersion` bleibt als Fallback erhalten, falls WP_PLUGIN_DIR mal
     * nicht greift (Bundle an ungewöhnlichem Ort).
     */
    private static function discoverBundles(): void
    {
        if (! defined('WP_PLUGIN_DIR')) {
            return;
        }

        $pattern = WP_PLUGIN_DIR . '/*/vendor/rh/blueprint-core/version.php';
        $matches = glob($pattern, GLOB_NOSORT);

        if ($matches === false) {
            return;
        }

        foreach ($matches as $versionFile) {
            $version = (string) (include $versionFile);
            if ($version !== '') {
                self::$versions[$version] = dirname($versionFile);
            }
        }
    }

    /**
     * Reine Auswahl-Logik (ohne Seiteneffekte, dadurch testbar).
     * Gibt die höchste Version nach SemVer zurück.
     *
     * @param array<int, string> $versions
     */
    public static function pickLatest(array $versions): string
    {
        usort($versions, 'version_compare');

        return $versions === [] ? '' : (string) end($versions);
    }

    public static function winningVersion(): string
    {
        return self::$winningVersion;
    }

    public static function winningDir(): string
    {
        return self::$winningDir;
    }
}
