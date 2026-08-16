<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Mail;

/**
 * Eine Mail-Art, die ein Modul verschicken kann.
 *
 * Module melden hier an, was sie überhaupt verschicken. Daraus entsteht die
 * Oberfläche von selbst: eine Zeile je Art, mit Schalter und Einstellungen.
 * Ohne diese Anmeldung müsste jedes Modul dieselben vier Felder von Hand
 * bauen, und am Ende sähe jedes anders aus und könnte etwas anderes.
 *
 * Ob eine Mail sofort rausgeht oder in den Sammelbericht wandert, steht im
 * Code und nicht in den Einstellungen: eine Bestellbestätigung sammelt man
 * nicht. Der Kunde entscheidet nur, OB er sie bekommt und wie sie aussieht.
 */
final class MailKind
{
    /** Geht raus, sobald der Anlass eintritt. */
    public const TIMING_IMMEDIATE = 'immediate';

    /** Wandert in den Sammelbericht. */
    public const TIMING_REPORT = 'report';

    /** @var array<string, self> */
    private static array $registry = [];

    /**
     * @param string $id       Eindeutig, Schema "modul.sache", etwa "hardening.alert".
     * @param string $module   Kennung des Moduls, muss zum Tab passen.
     * @param string $label    Kurz, erscheint als Zeilentitel.
     * @param string $summary  Ein Satz: wann geht diese Mail raus.
     * @param string $timing   TIMING_IMMEDIATE oder TIMING_REPORT.
     * @param bool   $default  Vorgabe für den Schalter.
     * @param bool   $urgent   Läuft an der Wellenbremse vorbei.
     * @param string $audience MailMessage::AUDIENCE_*.
     */
    private function __construct(
        public readonly string $id,
        public readonly string $module,
        public readonly string $label,
        public readonly string $summary,
        public readonly string $timing,
        public readonly bool $default,
        public readonly bool $urgent,
        public readonly string $audience,
    ) {
    }

    /**
     * @param array{module: string, label: string, summary?: string, timing?: string, default?: bool, urgent?: bool, audience?: string} $args
     */
    public static function register(string $id, array $args): self
    {
        $kind = new self(
            $id,
            (string) $args['module'],
            (string) $args['label'],
            (string) ($args['summary'] ?? ''),
            ($args['timing'] ?? self::TIMING_IMMEDIATE) === self::TIMING_REPORT
                ? self::TIMING_REPORT
                : self::TIMING_IMMEDIATE,
            (bool) ($args['default'] ?? true),
            (bool) ($args['urgent'] ?? false),
            (string) ($args['audience'] ?? MailMessage::AUDIENCE_INTERNAL),
        );

        self::$registry[$id] = $kind;

        return $kind;
    }

    public static function get(string $id): ?self
    {
        return self::$registry[$id] ?? null;
    }

    /**
     * Alle Arten, auf Wunsch nur die eines Moduls.
     *
     * @return array<string, self>
     */
    public static function all(?string $module = null): array
    {
        if ($module === null) {
            return self::$registry;
        }

        return array_filter(
            self::$registry,
            static fn (self $kind): bool => $kind->module === $module
        );
    }

    /**
     * Verschickt dieses Modul überhaupt etwas? Entscheidet, ob im Tab ein
     * Briefsymbol erscheint.
     */
    public static function moduleHasMail(string $module): bool
    {
        return self::all($module) !== [];
    }

    public function isReport(): bool
    {
        return $this->timing === self::TIMING_REPORT;
    }
}
