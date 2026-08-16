<?php

/**
 * Prüft jede Settings-Gruppe der Suite gegen die echte Instanz.
 *   ddev wp eval-file rh-blueprint-core/tests/settings-groups-check.php
 *
 * Anlass war ein Fatal Error im laufenden Backend: ein Feld wurde mit einem
 * benannten Parameter angelegt, den es nicht gibt (`options` statt `choices`).
 * Auffällig daran ist nicht der Tippfehler, sondern dass ihn nichts gefangen
 * hat. Die Standalone-Tests luden die Klasse zwar, riefen aber nur Konstanten
 * ab. Ein Feldfehler zeigt sich erst, wenn `fields()` tatsächlich läuft, und
 * das passierte zum ersten Mal beim Aufruf des Backends.
 *
 * Diese Prüfung ruft es auf. Für alle Gruppen aller installierten Module,
 * damit derselbe Fehler in keinem Modul mehr bis ins Backend durchkommt.
 */

// Kein declare(strict_types=1): wp eval-file wertet die Datei aus, dort darf
// die Anweisung nicht stehen.

use RhBlueprint\Core\Settings\SettingField;

if (! function_exists('rh_blueprint')) {
    echo "FEHLER: Der Core ist nicht geladen. Diese Prüfung braucht WordPress.\n";
    exit(1);
}

$failures = 0;
$geprueft = 0;

function pruefe(string $name, bool $ok, string $hinweis = ''): void
{
    global $failures;

    if (! $ok) {
        $failures++;
    }

    printf("  %-58s %s%s\n", $name, $ok ? 'PASS' : 'FAIL', $ok || $hinweis === '' ? '' : '  ' . $hinweis);
}

$hub = rh_blueprint()->settings();

// Die registrierten Gruppen liegen privat im Hub. Für eine Prüfung ist das der
// richtige Weg: sie soll genau das sehen, was das Backend auch sieht.
$reflection = new ReflectionObject($hub);

$groups = [];
foreach (['groups', 'registeredGroups', 'items'] as $kandidat) {
    if ($reflection->hasProperty($kandidat)) {
        $property = $reflection->getProperty($kandidat);
        $property->setAccessible(true);
        $value = $property->getValue($hub);
        if (is_array($value)) {
            $groups = $value;
            break;
        }
    }
}

if ($groups === []) {
    echo "FEHLER: Keine Settings-Gruppen gefunden. Ist die Struktur des Hubs anders?\n";
    exit(1);
}

echo "\nJede Gruppe baut ihre Felder ohne Fehler\n";

foreach ($groups as $group) {
    if (! is_object($group) || ! method_exists($group, 'fields')) {
        continue;
    }

    $klasse = get_class($group);
    $geprueft++;

    try {
        $fields = $group->fields();
    } catch (\Throwable $e) {
        pruefe($klasse, false, $e->getMessage());
        continue;
    }

    if (! is_array($fields)) {
        pruefe($klasse, false, 'fields() liefert kein Array');
        continue;
    }

    $probleme = [];
    $ids = [];

    foreach ($fields as $field) {
        if (! $field instanceof SettingField) {
            $probleme[] = 'Eintrag ist kein SettingField';
            continue;
        }

        if ($field->id === '') {
            $probleme[] = 'Feld ohne Kennung';
        }

        if (isset($ids[$field->id])) {
            $probleme[] = 'Kennung doppelt: ' . $field->id;
        }
        $ids[$field->id] = true;

        // Eine Auswahl ohne Auswahlmöglichkeiten rendert ein leeres Feld, und
        // der gespeicherte Wert fällt bei jedem Speichern auf den Standard
        // zurück. Das sieht man der Oberfläche nicht an.
        if ($field->type === SettingField::TYPE_SELECT && $field->choices === []) {
            $probleme[] = 'Auswahlfeld ohne Auswahl: ' . $field->id;
        }

        // Der Standard eines Auswahlfelds muss in der Auswahl vorkommen, sonst
        // steht beim ersten Speichern etwas anderes drin als angezeigt wurde.
        if ($field->type === SettingField::TYPE_SELECT
            && $field->choices !== []
            && ! array_key_exists((string) $field->default, $field->choices)
        ) {
            $probleme[] = 'Standard steht nicht zur Auswahl: ' . $field->id;
        }
    }

    pruefe(
        sprintf('%s (%d Felder)', $klasse, count($fields)),
        $probleme === [],
        implode(' | ', $probleme)
    );
}

echo "\n";
printf("%d Gruppen geprüft.\n", $geprueft);

if ($failures > 0) {
    echo "FEHLER: {$failures} Gruppe(n) mit Problemen.\n";
    exit(1);
}

echo "OK, alle Gruppen bauen sauber.\n";
