<?php

declare(strict_types=1);

namespace RhBlueprint\Core;

/**
 * Cross-Plugin Service-Locator.
 *
 * Jedes Plugin meldet seine öffentliche API hier an, jedes andere kann sie
 * abrufen. Da nur ein Core-Singleton existiert (Negotiation-Gewinner), ist
 * die Registry im ganzen Request geteilt, unabhängig von der Lade-Reihenfolge
 * der Plugins.
 *
 * Beispiel:
 *   rh_blueprint()->services()->register('backup', new \RhBackup\Api(), 1);
 *   $backup = rh_blueprint()->services()->get('backup', 1); // ?object, null wenn fehlt/zu alt
 */
final class ServiceRegistry
{
    /** @var array<string, array{service: object, version: int}> */
    private array $services = [];

    /**
     * Registriert einen Service unter einer ID mit API-Version.
     * Eine erneute Registrierung derselben ID überschreibt (höhere Version gewinnt nicht automatisch,
     * der Aufrufer ist verantwortlich, nicht doppelt zu registrieren).
     */
    public function register(string $id, object $service, int $version = 1): void
    {
        $this->services[$id] = [
            'service' => $service,
            'version' => $version,
        ];
    }

    /**
     * Liefert den Service, sofern vorhanden und mindestens `minVersion`.
     * Gibt `null` zurück, wenn der Service fehlt oder zu alt ist.
     */
    public function get(string $id, int $minVersion = 1): ?object
    {
        if (! isset($this->services[$id])) {
            return null;
        }

        $entry = $this->services[$id];

        return $entry['version'] >= $minVersion ? $entry['service'] : null;
    }

    public function has(string $id, int $minVersion = 1): bool
    {
        return $this->get($id, $minVersion) !== null;
    }

    /**
     * Registrierte API-Version eines Service, oder 0 wenn nicht vorhanden.
     */
    public function versionOf(string $id): int
    {
        return isset($this->services[$id]) ? $this->services[$id]['version'] : 0;
    }
}
