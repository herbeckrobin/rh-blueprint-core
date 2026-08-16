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
 *
 * Drei Angaben stammen aus rh-shop, das sein Mail-Modell unabhängig gebaut
 * hatte und dabei an drei Stellen weiter war:
 *
 *   subject       Ein Vorgabe-Betreff je Art. Vorher musste der Betreff
 *                 entweder leer bleiben oder an der Sendestelle stehen, wo
 *                 ihn niemand ändern konnte.
 *   placeholders  Was im Betreff einsetzbar ist. Ohne diese Liste ist ein
 *                 Betreff-Feld eine Einladung zum Raten.
 *   lockable      Ob die Mail abgeschaltet werden darf. Eine
 *                 Bestellbestätigung ist Pflicht, der Schalter dafür wäre
 *                 eine Falle. Vorgabe true, gesperrt ist die Ausnahme.
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
     * @param string $subject  Vorgabe-Betreff, mit {platzhaltern}.
     * @param bool   $lockable Darf abgeschaltet werden. False bei Pflichtmails.
     * @param array<int, string> $placeholders Erlaubte Platzhalter ohne Klammern.
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
        public readonly string $subject,
        public readonly bool $lockable,
        public readonly array $placeholders,
    ) {
    }

    /**
     * @param array{module: string, label: string, summary?: string, timing?: string, default?: bool, urgent?: bool, audience?: string, subject?: string, lockable?: bool, placeholders?: array<int, string>} $args
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
            (string) ($args['subject'] ?? ''),
            (bool) ($args['lockable'] ?? true),
            array_values(array_map('strval', (array) ($args['placeholders'] ?? []))),
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

    /**
     * Der Betreff mit eingesetzten Werten.
     *
     * @param array<string, string|int|float> $werte Platzhalter ohne Klammern => Wert.
     */
    public function fillSubject(string $vorlage, array $werte): string
    {
        $vorlage = $vorlage !== '' ? $vorlage : $this->subject;

        foreach ($werte as $name => $wert) {
            $vorlage = str_replace('{' . $name . '}', (string) $wert, $vorlage);
        }

        // Was niemand gefüllt hat, soll nicht als {klammer} beim Kunden landen.
        return trim((string) preg_replace('/\s*\{[a-z0-9_]+\}/i', '', $vorlage));
    }
}
