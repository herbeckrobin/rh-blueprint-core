<?php

/**
 * Core-Bootstrap. Wird NUR für die Gewinner-Version geladen (vom Loader).
 *
 * Registriert den PSR-4-Autoloader für `RhBlueprint\Core\` (bewusst nicht über
 * Composer, sonst würde jedes Bundle seinen eigenen registrieren und die Klassen
 * kollidieren), lädt die globalen Helper und bootet den Core-Singleton.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    return;
}

(static function (): void {
    $srcDir = __DIR__ . '/src/';

    spl_autoload_register(static function (string $class) use ($srcDir): void {
        $prefix = 'RhBlueprint\\Core\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $srcDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });

    require_once __DIR__ . '/functions.php';

    \RhBlueprint\Core\Core::boot(
        RhBlueprintCoreLoader::winningVersion(),
        __DIR__
    );
})();
