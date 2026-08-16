<?php

declare(strict_types=1);

namespace RhBlueprint\Core;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Auto-Update über GitHub-Releases, für jedes Modul der Suite.
 *
 * Diese Klasse lag sechzehnmal in den Modulen, nach Ersetzen der Namen
 * zeichengleich. Drei Angaben unterschieden sich: Repo, Slug und die Konstante
 * mit dem Pfad der Hauptdatei. Der Rest war Abschrift.
 *
 * Der Gewinn ist nicht die Zeilenzahl, sondern dass Verbesserungen am
 * Update-Weg ab jetzt an einer Stelle passieren. Das Leeren des
 * Bytecode-Zwischenspeichers unten ist genau so ein Fall: es stand über
 * mehrere Releases als offener Punkt in den Notizen und wäre sonst
 * sechzehnmal einzubauen gewesen.
 */
final class UpdateChecker
{
    /**
     * @param string $slug       Verzeichnis- und Repo-Name, etwa "rh-motion".
     * @param string $pluginFile Absoluter Pfad der Plugin-Hauptdatei.
     * @param string $owner      GitHub-Konto, falls einmal ein anderes gebraucht wird.
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $pluginFile,
        private readonly string $owner = 'herbeckrobin',
    ) {
    }

    public function boot(): void
    {
        // Im WordPress.org-Build wird die Bibliothek entfernt, dort liefert das
        // Verzeichnis die Updates selbst.
        //
        // WP_PLUGIN_DIR gehört mit in die Prüfung: die Bibliothek liest die
        // Konstante ungeprüft und stirbt ohne sie. Betrifft jede Umgebung, die
        // den Code lädt, ohne WordPress vollständig zu sein, etwa die
        // Standalone-Tests der Module.
        if (
            ! function_exists('add_filter')
            || ! defined('WP_PLUGIN_DIR')
            || ! class_exists(PucFactory::class)
        ) {
            return;
        }

        if (! self::isUpdateContext()) {
            // Auf einem gewöhnlichen Seitenaufruf wird davon nichts gebraucht,
            // gemessen wurden 19 Dateien pro Aufruf, mal sechzehn Module. Für
            // Anfragen über die REST-Schnittstelle wird nachgeladen, sobald
            // feststeht, dass es eine ist: `is_admin()` ist dort falsch, und
            // seit WordPress 5.5 lassen sich Plugins auch darüber aktualisieren.
            add_action('rest_api_init', [$this, 'register'], 0);

            return;
        }

        $this->register();
    }

    /**
     * Meldet den Prüfer bei WordPress an.
     *
     * Öffentlich, weil sie als Rückruf an `rest_api_init` hängt.
     */
    public function register(): void
    {
        // Auch hier, nicht nur in boot(): die Methode hängt als Rückruf an
        // einem Haken und ist damit von aussen erreichbar. Im
        // WordPress.org-Build gibt es die Bibliothek nicht.
        if (! class_exists(PucFactory::class)) {
            return;
        }

        $checker = PucFactory::buildUpdateChecker(
            sprintf('https://github.com/%s/%s/', $this->owner, $this->slug),
            $this->pluginFile,
            $this->slug
        );

        $api = $checker->getVcsApi();

        if ($api !== null && method_exists($api, 'enableReleaseAssets')) {
            // Das Release-ZIP nehmen, nicht den Quell-Tarball: nur im Asset
            // liegt das gebundelte vendor/ mit Core und Bibliotheken.
            $api->enableReleaseAssets();
        }

        add_action('upgrader_process_complete', [$this, 'afterUpdate'], 10, 2);
    }

    /**
     * Wird auf dieser Anfrage überhaupt nach Updates gesehen?
     *
     * Drei Fälle, in denen die Antwort ja ist: das Backend (auch
     * admin-ajax.php), ein Cron-Lauf (dort laufen die automatischen Updates)
     * und die Kommandozeile. Der vierte Fall, die REST-Schnittstelle, wird
     * nicht hier entschieden: bei `plugins_loaded` steht noch nicht fest, ob
     * eine Anfrage dorthin geht, deshalb hängt sich `boot()` für diesen Fall
     * an `rest_api_init` und lädt nach.
     */
    public static function isUpdateContext(): bool
    {
        if (function_exists('is_admin') && is_admin()) {
            return true;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        return defined('WP_CLI') && constant('WP_CLI');
    }

    /**
     * Nach einem Update den Bytecode-Zwischenspeicher des eigenen Verzeichnisses leeren.
     *
     * Ohne das laufen die PHP-Prozesse weiter auf dem alten Stand, obwohl die
     * neuen Dateien längst auf der Platte liegen. Auf Kundenseiten hat das
     * mehrfach dazu geführt, dass ein ausgelieferter Fix nicht griff und der
     * Fehler weiterlief, bis jemand von Hand nachhalf.
     *
     * WordPress invalidiert seit 5.5 selbst schon einzelne Dateien beim
     * Update. Dass es in der Praxis trotzdem nicht reichte, ist mehrfach
     * belegt, der Grund nicht: plausibel ist, dass der auslösende Prozess
     * (etwa ein Aufruf über die Kommandozeile) und der Pool, der die Website
     * bedient, verschiedene Caches haben. Deshalb hier noch einmal, gezielt.
     *
     * Nur beim eigenen Plugin.
     *
     * @param array<string, mixed> $extra
     */
    public function afterUpdate(mixed $upgrader, array $extra): void
    {
        if (! $this->shouldReset($extra)) {
            return;
        }

        // Gezielt statt global. Ein `opcache_reset()` wirft den Bytecode-Cache
        // des ganzen Prozesses weg, auf einem Shared-Host mit gemeinsamem
        // FPM-Pool also auch den fremder Websites, die mit diesem Update
        // nichts zu tun haben. WordPress bringt seit 6.2 die Variante mit, die
        // nur das eigene Verzeichnis anfasst.
        if (function_exists('wp_opcache_invalidate_directory') && defined('WP_PLUGIN_DIR')) {
            wp_opcache_invalidate_directory(WP_PLUGIN_DIR . '/' . $this->slug);

            return;
        }

        // Rückfall für ältere Installationen.
        if (function_exists('opcache_reset') && ! in_array('opcache_reset', $this->gesperrteFunktionen(), true)) {
            @opcache_reset();
        }
    }

    /**
     * Betrifft dieses Ereignis das eigene Plugin?
     *
     * Eigene Methode, weil sich der Reset selbst nicht prüfen lässt: er wirkt
     * erst im nächsten Prozess, im laufenden meldet weder die Zahl der Skripte
     * noch `last_restart_time` eine Änderung. Also wird hier die Entscheidung
     * geprüft und nicht ihre Wirkung.
     *
     * @param array<string, mixed> $extra
     */
    public function shouldReset(array $extra): bool
    {
        if (($extra['action'] ?? '') !== 'update' || ($extra['type'] ?? '') !== 'plugin') {
            return false;
        }

        $betroffen = $extra['plugins'] ?? [];

        if (! is_array($betroffen)) {
            return false;
        }

        return in_array($this->slug . '/' . $this->slug . '.php', $betroffen, true);
    }

    /**
     * Was der Hoster per disable_functions abgeschaltet hat. Ein Aufruf einer
     * gesperrten Funktion ist ein Fatal, den kein @ auffängt.
     *
     * @return array<int, string>
     */
    private function gesperrteFunktionen(): array
    {
        $liste = (string) ini_get('disable_functions');

        return array_map('trim', explode(',', $liste));
    }
}
