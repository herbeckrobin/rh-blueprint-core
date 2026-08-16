<?php

/**
 * Prüft die Abhängigkeiten zwischen Modulen.
 *
 * Der Kern: ein Modul soll fragen können, ob es eine Funktion überhaupt
 * anbieten darf. Eine Schaltfläche, die nichts tut, ist schlimmer als eine,
 * die gar nicht da ist.
 */

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Admin\Dependencies;

$t = new TestErgebnis();

// --- WordPress-Ersatz --------------------------------------------------------

$GLOBALS['__filter'] = [];
$GLOBALS['__aktive'] = [];

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $wert): mixed
    {
        foreach ($GLOBALS['__filter'][$hook] ?? [] as $cb) {
            $wert = $cb($wert);
        }

        return $wert;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $cb, int $prio = 10, int $args = 1): void
    {
        $GLOBALS['__filter'][$hook][] = $cb;
    }
}

if (! function_exists('is_plugin_active')) {
    function is_plugin_active(string $datei): bool
    {
        return in_array($datei, $GLOBALS['__aktive'], true);
    }
}

// --- Anmeldungen -------------------------------------------------------------

add_filter('rh-blueprint/dependencies', static function (array $d): array {
    $d[] = ['module' => 'rh-shop', 'needs' => 'rh-smtp', 'for' => 'Bestellbestätigung', 'tab' => 'shop', 'required' => true];
    $d[] = ['module' => 'rh-shop', 'needs' => 'rh-consent', 'for' => 'Cookie-Hinweis', 'tab' => 'shop', 'required' => false];
    $d[] = ['module' => 'rh-backup', 'needs' => 'rh-smtp', 'for' => 'Fehlermeldung', 'tab' => 'backup', 'required' => true];
    // Unvollständig, muss aussortiert werden.
    $d[] = ['module' => 'rh-kaputt'];
    $d[] = 'gar kein Feld';

    return $d;
});

$t->pruefe(count(Dependencies::all()) === 3, 'unvollständige Anmeldungen fallen raus', 'gefunden: ' . count(Dependencies::all()));

// --- Nichts installiert ------------------------------------------------------

$GLOBALS['__aktive'] = [];

$t->pruefe(count(Dependencies::missing('rh-shop')) === 1, 'ohne das Mail-Modul fehlt dem Shop genau eine Voraussetzung');
$t->pruefe(! Dependencies::satisfied('rh-shop'), 'der Shop meldet sich als nicht bedient');
$t->pruefe(
    count(Dependencies::missing('rh-shop', false)) === 2,
    'mit den Empfehlungen sind es zwei'
);
$t->pruefe(Dependencies::satisfied('rh-motion'), 'ein Modul ohne Anmeldung ist immer bedient');

// --- Voraussetzung erfüllt ---------------------------------------------------

$GLOBALS['__aktive'] = ['rh-smtp/rh-smtp.php'];

$t->pruefe(Dependencies::satisfied('rh-shop'), 'mit dem Mail-Modul ist die Voraussetzung erfüllt');
$t->pruefe(Dependencies::satisfied('rh-backup'), 'das gilt für jedes Modul, das dasselbe braucht');
$t->pruefe(
    count(Dependencies::missing('rh-shop', false)) === 1,
    'die Empfehlung bleibt offen und wird auch so gemeldet'
);

// --- Ein anderes Modul deckt die Empfehlung ----------------------------------

$GLOBALS['__aktive'] = ['rh-smtp/rh-smtp.php', 'rh-consent/rh-consent.php'];

$t->pruefe(
    Dependencies::missing('rh-shop', false) === [],
    'sind beide da, ist nichts mehr offen'
);

// --- Der Name täuscht nicht --------------------------------------------------

$GLOBALS['__aktive'] = ['rh-smtp-pro/rh-smtp-pro.php'];

$t->pruefe(
    ! Dependencies::satisfied('rh-shop'),
    'ein Modul mit ähnlichem Namen zählt nicht als das gesuchte'
);

$t->abschluss();
