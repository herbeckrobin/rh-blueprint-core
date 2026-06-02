<?php

/**
 * RH Blueprint Core, Entry-Point.
 *
 * Diese Datei wird von JEDEM rh-blueprint-Plugin über Composers files-autoload
 * geladen, also möglicherweise mehrfach pro Request (einmal pro Plugin, das den
 * Core bundlet). Sie tut deshalb nur zwei Dinge, beide idempotent:
 *
 *   1. Die stabile Loader-Klasse einmalig laden (class_exists-Guard).
 *   2. Die hier vorliegende Core-Version + ihren Pfad beim Loader anmelden.
 *
 * Welcher Core am Ende wirklich läuft, entscheidet der Loader auf `plugins_loaded`:
 * die höchste angemeldete Version gewinnt (Version-Negotiation, analog Action Scheduler).
 *
 * WICHTIG: Hier KEINE Core-Klassen laden und KEINE Logik ausführen. Alles was der
 * Gewinner braucht, passiert in bootstrap.php, das nur die Sieger-Version lädt.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    return;
}

if (! class_exists('RhBlueprintCoreLoader', false)) {
    require_once __DIR__ . '/loader/RhBlueprintCoreLoader.php';
}

// Die Version steht in EINER Datei (Single Source), Loader und Bootstrap lesen sie dort.
RhBlueprintCoreLoader::declareVersion(
    (string) (require __DIR__ . '/version.php'),
    __DIR__
);
