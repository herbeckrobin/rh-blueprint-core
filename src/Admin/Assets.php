<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Wann darf ein Modul seine Skripte und Stile laden.
 *
 * `admin_enqueue_scripts` feuert auf jeder Seite im Backend. Wer nicht prüft,
 * wo er gerade ist, hängt seine Dateien auch an die Beitragsliste, den Editor
 * und die Medienübersicht. Zehn Module machen diese Prüfung, jedes mit einer
 * leicht anderen Zeile, und eine davon vergisst den Reiter und lädt auf allen
 * Reitern der Suite mit.
 *
 * Die Prüfung selbst ist drei Zeilen. Der Grund, sie trotzdem hierher zu
 * ziehen: sie liest den Reiter direkt aus der Adresse, und diese Stelle sollte
 * es genau einmal geben, damit ein späterer Umbau der Adressen nicht zehn
 * Module still danebenlaufen lässt.
 */
final class Assets
{
    /**
     * Sind wir auf der Einstellungsseite der Suite?
     *
     * @param string $tab Auf diesen Reiter einschränken. Leer: jeder Reiter.
     */
    public static function onSettings(string $tab = ''): bool
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Ortsbestimmung.
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $aktiv = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($page !== SettingsPage::MENU_SLUG) {
            return false;
        }

        if ($tab === '') {
            return true;
        }

        return self::currentTab() === $tab;
    }

    /**
     * Der aktuell gewählte Reiter.
     *
     * Steht keiner in der Adresse, ist der erste angemeldete gemeint. Diese
     * Regel steckt bisher an drei Stellen, und wer sie beim Prüfen weglässt,
     * lädt auf dem Startreiter nichts oder überall.
     */
    public static function currentTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Ortsbestimmung.
        $aktiv = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';

        if ($aktiv !== '') {
            return $aktiv;
        }

        if (! function_exists('rh_blueprint')) {
            return '';
        }

        $tabs = array_keys(rh_blueprint()->settings()->tabs());

        return (string) ($tabs[0] ?? '');
    }
}
