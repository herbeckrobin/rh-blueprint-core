<?php

/**
 * Prüft die Umrechnung von php.ini-Grössenangaben.
 *
 * Diese Klasse hat einen einzigen gefährlichen Punkt, und der ist keine
 * Rechenoperation: was "kein Limit" bedeutet. Drei der vier zusammengelegten
 * Kopien gaben dafür 0 zurück, die vierte PHP_INT_MAX. In rh-sync entscheidet
 * der Wert darüber, ob ein Lauf als zu gross abgelehnt wird. Aus PHP_INT_MAX
 * eine 0 zu machen hiesse dort, jeden Sync zu blockieren.
 *
 * Deshalb steht die Bedeutung hier im Vordergrund und nicht die Frage, ob
 * 256M die richtige Zahl ergibt.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Support\Bytes;

$t = new TestErgebnis();

// --- "Kein Limit" bedeutet, was der Aufrufer sagt ----------------------------

$t->pruefe(
    Bytes::fromIni('-1') === 0,
    'ohne Angabe der Bedeutung ist "kein Limit" eine 0'
);
$t->pruefe(
    Bytes::fromIni('-1', Bytes::UNLIMITED_MAX) === PHP_INT_MAX,
    'wer alles durchlassen will, sagt das und bekommt den grössten Wert'
);
$t->pruefe(
    Bytes::UNLIMITED_ZERO !== Bytes::UNLIMITED_MAX,
    'die beiden Bedeutungen sind unterscheidbar, nicht zufällig gleich'
);

// Der Unterschied, der leicht verlorengeht: eine leere Angabe heisst NICHT
// "kein Limit", sondern "nicht ermittelbar". Wer daraus PHP_INT_MAX macht,
// verschluckt eine Warnung, weil niemand mehr etwas zu vergleichen hat.
$t->pruefe(
    Bytes::fromIni('', Bytes::UNLIMITED_MAX) === 0,
    'eine leere Angabe ist unbekannt, nicht unbegrenzt'
);
$t->pruefe(
    Bytes::fromIni('0', Bytes::UNLIMITED_MAX) === 0,
    'eine geschriebene 0 bleibt 0, so hielten es alle vier Kopien'
);

// --- Die Einheiten -----------------------------------------------------------

$t->pruefe(Bytes::fromIni('256M') === 268435456, '256M', (string) Bytes::fromIni('256M'));
$t->pruefe(Bytes::fromIni('1G') === 1073741824, '1G');
$t->pruefe(Bytes::fromIni('512K') === 524288, '512K');
$t->pruefe(Bytes::fromIni('1024') === 1024, 'eine nackte Zahl ist schon in Bytes');

// Kleinschreibung kommt in echten php.ini-Dateien vor.
$t->pruefe(Bytes::fromIni('256m') === 268435456, 'die Einheit darf klein geschrieben sein');
$t->pruefe(Bytes::fromIni('  128M  ') === 134217728, 'Leerzeichen aussen stören nicht');

// --- Was nicht passieren darf ------------------------------------------------

$t->pruefe(Bytes::fromIni('Unsinn') === 0, 'eine unlesbare Angabe ergibt 0, keinen Fehler');

// --- Kopffreiheit ------------------------------------------------------------
//
// Ohne Limit ist immer Platz. Das ist die Stelle, an der die 0 als "kein
// Limit" gelesen wird, und sie muss zur Umrechnung oben passen.

$t->pruefe(
    Bytes::hasHeadroom(PHP_INT_MAX) === (Bytes::memoryLimit() === 0),
    'ohne Speicherlimit passt jede Menge, mit Limit nicht'
);

// --- Anzeige -----------------------------------------------------------------

$t->pruefe(Bytes::toMb(268435456) === '256,0 MB', '256 MB deutsch formatiert', Bytes::toMb(268435456));
$t->pruefe(Bytes::toMb(0) === '0,0 MB', 'auch die Null bekommt ihre Einheit');

$t->abschluss();
