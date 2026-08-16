<?php

/**
 * Core-Klassen laden, ohne WordPress.
 *
 * Für die Standalone-Tests der Module. Die haben ihre Core-Klassen bisher
 * einzeln per require geholt, und das bricht jedes Mal, wenn ein Modul eine
 * weitere Core-Klasse benutzt: der Test stirbt an einer fehlenden Klasse,
 * obwohl im Betrieb alles da ist. Genau das ist beim Zusammenlegen der
 * Byte-Umrechnung passiert, an drei Tests gleichzeitig.
 *
 * `bootstrap.php` geht hier nicht: die Datei steigt ohne ABSPATH aus und
 * bootet den Core-Singleton, der WordPress-Funktionen erwartet. Hier soll nur
 * geladen werden, nichts starten.
 *
 * Aufruf im Test:
 *   require $base . '/vendor/rh/blueprint-core/autoload-src.php';
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'RhBlueprint\\Core\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $datei = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($datei)) {
        require_once $datei;
    }
});
