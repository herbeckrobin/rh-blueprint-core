<?php

/**
 * Prüft die geteilten UI-Bausteine.
 *
 * Der Schwerpunkt liegt auf den Dingen, die in den abgeschriebenen Kopien
 * gefehlt haben: aria-hidden am Schalter, role und aria-modal am Dialog, das
 * Attribut für den Klick daneben, und ein Symbol, das nicht leer ist.
 */

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Admin\Ui;
use RhBlueprint\Core\Support\Bytes;

$t = new TestErgebnis();

// --- Symbole ----------------------------------------------------------------

foreach (['mail', 'gear', 'close', 'check', 'plus', 'trash', 'copy', 'refresh'] as $name) {
    $svg = Ui::icon($name);
    $t->pruefe(
        str_starts_with($svg, '<svg') && str_contains($svg, 'aria-hidden="true"') && strlen($svg) > 80,
        "Symbol $name ist da und aus dem Vorlesen genommen"
    );
}

$t->pruefe(Ui::hasIcon('trash'), 'bekanntes Symbol wird gemeldet');
$t->pruefe(! Ui::hasIcon('gibtesnicht'), 'unbekanntes Symbol wird gemeldet');
$t->pruefe(
    Ui::icon('gibtesnicht') === Ui::icon('gear'),
    'ein Tippfehler im Namen gibt das Zahnrad, nicht nichts'
);

// --- Schalter ---------------------------------------------------------------

$an = Ui::switch(['name' => 'feld[x]', 'checked' => true]);
$aus = Ui::switch(['name' => 'feld[x]']);

$t->pruefe(str_contains($an, 'checked'), 'eingeschalteter Schalter ist angehakt');
$t->pruefe(! str_contains($aus, 'checked'), 'ausgeschalteter Schalter ist nicht angehakt');
$t->pruefe(str_contains($an, 'name="feld[x]" value="1"'), 'der Feldname kommt an');
$t->pruefe(
    substr_count($an, 'aria-hidden="true"') === 1,
    'die Spur des Schalters ist aus dem Vorlesen genommen'
);

// Das war der eigentliche Befund: vier Stellen ohne aria-hidden. Über den
// Baustein kann das keine Variante mehr verlieren.
foreach ([
    ['name' => 'a'],
    ['input' => ['data-toggle' => true]],
    ['class' => 'eigene', 'title' => 'Aktiv'],
    ['label' => 'Schema ausgeben', 'disabled' => true],
] as $i => $variante) {
    $t->pruefe(
        substr_count(Ui::switch($variante), 'aria-hidden="true"') === 1,
        'Schaltervariante ' . ($i + 1) . ' behält aria-hidden'
    );
}

$t->pruefe(
    str_contains(Ui::switch(['input' => ['data-x' => 'y"z']]), 'data-x="y&quot;z"'),
    'zusätzliche Attribute werden escapt'
);
$t->pruefe(
    str_contains(Ui::switch(['input' => ['data-rhseo-toggle' => true]]), ' data-rhseo-toggle>'),
    'ein Attribut ohne Wert bekommt kein leeres Gleichheitszeichen'
);
$t->pruefe(
    ! str_contains(Ui::switch(['input' => ['data-x' => true]]), 'name='),
    'ohne Feldname gibt es kein name-Attribut'
);
$t->pruefe(
    str_contains(Ui::switch(['title' => 'Aktiv', 'class' => 'x__switch']), '<label class="rhbp-switch x__switch" title="Aktiv">'),
    'Zusatzklasse und Beschriftung sitzen am Label, nicht am Feld'
);
$t->pruefe(str_contains(Ui::switch(['disabled' => true]), ' disabled'), 'gesperrt wird gesperrt');
$t->pruefe(
    str_contains(Ui::switch(['label' => 'Text']), '<span class="rhbp-switch__label">Text</span>'),
    'der sichtbare Text steht hinter der Spur'
);

// --- Reiter -----------------------------------------------------------------

$tabs = ['eins' => 'Eins', 'zwei' => 'Zwei'];

$mitLinks = Ui::subtabs($tabs, 'zwei', ['eins' => '/a', 'zwei' => '/b']);
$t->pruefe(str_contains($mitLinks, '<a class="rhbp-subtab" href="/a"'), 'Reiter mit Adresse wird ein Verweis');
$t->pruefe(str_contains($mitLinks, 'aria-current="page"'), 'der aktive Verweis ist als solcher gekennzeichnet');

$ohneLinks = Ui::subtabs($tabs, 'eins');
$t->pruefe(str_contains($ohneLinks, '<button type="button"'), 'Reiter ohne Adresse wird ein Knopf');
$t->pruefe(str_contains($ohneLinks, 'data-rhbp-subtabs'), 'die Leiste trägt die Marke für das Skript');
$t->pruefe(substr_count($ohneLinks, 'is-active') === 1, 'genau ein Reiter ist aktiv');
$t->pruefe(Ui::subtabs([], 'x') === '', 'ohne Reiter kommt keine leere Leiste');

// --- Dialog -----------------------------------------------------------------

$dialog = Ui::modalOpen('mein-dialog', 'Titel', 'Untertitel', 'mail', 'ok') . 'Inhalt' . Ui::modalClose('Speichern');

foreach ([
    'data-rhbp-modal-backdrop' => 'der Klick daneben kann zumachen',
    'role="dialog"' => 'die Rolle ist gesetzt',
    'aria-modal="true"' => 'der Rest der Seite ist als abgeschirmt gekennzeichnet',
    'aria-label="Titel"' => 'der Dialog hat einen Namen',
    'data-rhbp-modal-close' => 'es gibt einen Weg heraus',
    'rhbp-modal__head-icon--ok' => 'die Färbung kommt an',
] as $nadel => $name) {
    $t->pruefe(str_contains($dialog, $nadel), $name);
}

$t->pruefe(
    ! str_contains($dialog, ' hidden>') && ! str_contains($dialog, 'is-open'),
    'zu ist die Abwesenheit von is-open, kein zweiter Mechanismus daneben'
);

$t->pruefe(
    substr_count($dialog, '<div') === substr_count($dialog, '</div>'),
    'Kopf und Fuss ergeben zusammen ein geschlossenes Element',
    substr_count($dialog, '<div') . ' auf gegen ' . substr_count($dialog, '</div>') . ' zu'
);

$t->pruefe(
    str_contains(Ui::modalOpen('x', 'Titel', '', 'gear', '', true), 'is-open'),
    'ein von Anfang an offener Dialog ist offen'
);

$t->pruefe(
    ! str_contains(Ui::modalClose(), 'type="submit"'),
    'ohne bestätigenden Knopf gibt es keinen Absende-Knopf'
);

// --- Bytes ------------------------------------------------------------------

$t->pruefe(Bytes::fromIni('256M') === 268435456, '256M sind 268435456 Bytes');
$t->pruefe(Bytes::fromIni('1G') === 1073741824, '1G sind 1073741824 Bytes');
$t->pruefe(Bytes::fromIni('512K') === 524288, '512K sind 524288 Bytes');
$t->pruefe(Bytes::fromIni('1024') === 1024, 'eine nackte Zahl bleibt sie selbst');
$t->pruefe(Bytes::fromIni('  128M  ') === 134217728, 'Leerzeichen stören nicht');

// Der Kern: dieselbe Eingabe, zwei Bedeutungen, und beide müssen stimmen.
$t->pruefe(Bytes::fromIni('-1') === 0, 'kein Limit ist 0, wenn 0 gemeint ist');
$t->pruefe(Bytes::fromIni('-1', Bytes::UNLIMITED_MAX) === PHP_INT_MAX, 'kein Limit ist PHP_INT_MAX, wenn das gemeint ist');
$t->pruefe(Bytes::fromIni('') === 0, 'leer ist 0, nicht unbegrenzt');
$t->pruefe(
    Bytes::fromIni('', Bytes::UNLIMITED_MAX) === 0,
    'leer bleibt 0, auch wenn -1 zu PHP_INT_MAX würde: unbekannt ist nicht unbegrenzt'
);
$t->pruefe(
    Bytes::fromIni('0', Bytes::UNLIMITED_MAX) === 0,
    'eine geschriebene 0 bleibt 0, auch wenn -1 zu PHP_INT_MAX würde'
);

$t->abschluss();
