<?php

declare(strict_types=1);

namespace RhBlueprint\Core;

use RhBlueprint\Core\Admin\DashboardCleanup;
use RhBlueprint\Core\Admin\SupportGroup;
use RhBlueprint\Core\Admin\SupportWidget;
use RhBlueprint\Core\Settings\SettingsHub;
use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Core-Singleton der rh-blueprint Kollektion.
 *
 * Wird genau einmal pro Request gebootet (von der Negotiation-Gewinner-Version).
 * Hält die geteilte Service-Registry und den Settings-Hub und ist über
 * `rh_blueprint()` erreichbar.
 *
 * Infrastruktur (Settings-Framework) bootet der Core direkt. Optionale Features
 * (Dashboard-Cleanup, Support-Box) und Plugins docken über den Hook
 * `rh-blueprint/core/booted` an, statt hier hart verdrahtet zu sein.
 */
final class Core
{
    private static ?self $instance = null;

    private ServiceRegistry $services;

    private SettingsHub $settings;

    private function __construct(
        private readonly string $version,
        private readonly string $dir,
    ) {
        $this->services = new ServiceRegistry();
        $this->settings = new SettingsHub();
    }

    /**
     * Bootet den Core. Idempotent: ein zweiter Aufruf (z.B. falls zwei Bootstraps
     * durchrutschen) ist ein No-Op.
     */
    public static function boot(string $version, string $dir): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self($version, $dir);
        self::$instance->bootSubsystems();

        // Tabs/Gruppen und der booted-Hook nutzen Übersetzungsfunktionen (__()),
        // die ab WP 6.7 nicht vor `init` laufen dürfen. Darum erst auf init.
        add_action('init', [self::$instance, 'bootFeatures'], 1);
    }

    /**
     * Bootet die Kern-Infrastruktur. Läuft früh (plugins_loaded) und nutzt
     * bewusst KEINE Übersetzungsfunktionen, nur Hook-Registrierungen.
     */
    private function bootSubsystems(): void
    {
        $this->settings->boot();
        (new SettingsPage($this->settings))->boot();
        (new DashboardCleanup())->boot();
        (new SupportWidget())->boot();
    }

    /**
     * Registriert Core-Tabs/Gruppen (mit __()) und feuert den booted-Hook, über
     * den Plugins ihre eigenen Tabs/Gruppen/Services anmelden. Läuft auf `init`.
     */
    public function bootFeatures(): void
    {
        $this->settings->registerTab('general', __('Allgemein', 'rh-blueprint-core'), 10);
        $this->settings->registerGroup(new SupportGroup());

        /**
         * Core ist bereit, Settings-Hub steht, Textdomain darf geladen werden.
         *
         * @param Core $core
         */
        do_action('rh-blueprint/core/booted', $this);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('RH Blueprint Core wurde noch nicht gebootet.');
        }

        return self::$instance;
    }

    public static function isBooted(): bool
    {
        return self::$instance !== null;
    }

    public function services(): ServiceRegistry
    {
        return $this->services;
    }

    public function settings(): SettingsHub
    {
        return $this->settings;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * URL zu einer Datei innerhalb des Core-Verzeichnisses (liegt unter vendor/
     * eines Plugins). Löst über plugins_url auf, das auch in vendor/ funktioniert.
     */
    public function assetUrl(string $relative = ''): string
    {
        return plugins_url(ltrim($relative, '/'), $this->dir . '/rh-blueprint-core.php');
    }

    /**
     * Cache-Buster für ein Core-Asset: filemtime wenn vorhanden, sonst die
     * übergebene Fallback-Version.
     */
    public function assetVersion(string $relative, string $fallback): string
    {
        $file = rtrim($this->dir, '/') . '/' . ltrim($relative, '/');

        return is_file($file) ? (string) filemtime($file) : $fallback;
    }
}
