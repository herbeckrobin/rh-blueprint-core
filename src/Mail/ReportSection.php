<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Mail;

/**
 * Der Beitrag eines Moduls zum Sammelbericht.
 *
 * Abgeholt wird beim Berichtstermin, nicht laufend eingeworfen. Das E-Mail-Modul
 * fragt über den Filter `rh-blueprint/report/sections` alle Module: was hast du
 * für diesen Zeitraum. Jedes Modul baut seinen Abschnitt aus seinen eigenen
 * Daten, die es ohnehin führt (rh-hardening aus der Chronik, rh-seo aus dem
 * 404-Protokoll).
 *
 * Der Vorteil gegenüber einem Puffer, in den Module laufend schreiben: es gibt
 * keinen Zwischenspeicher, der volllaufen, verloren gehen oder doppelt
 * ausgeliefert werden kann. Ist kein Berichtsmodul installiert, fragt niemand,
 * und es geht trotzdem nichts verloren, weil die Daten bei den Modulen liegen.
 *
 * Jeder Abschnitt trägt einen Zustand. Daraus entsteht die Ampel im Kopf des
 * Berichts, mit der man in fünf Sekunden sieht, ob man weiterlesen muss.
 */
final class ReportSection
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_ALERT = 'alert';

    /** Nichts zu berichten, taucht in der Ampel gar nicht auf. */
    public const STATUS_QUIET = 'quiet';

    private ?MailMessage $detail = null;

    private string $url = '';

    /**
     * @param string $module  Kennung des Moduls, etwa "hardening".
     * @param string $label   Überschrift des Abschnitts, etwa "Sicherheit".
     * @param string $status  Einer der STATUS_-Werte.
     * @param string $summary Eine Zeile Kernaussage. Steht in der Ampel.
     */
    public function __construct(
        public readonly string $module,
        public readonly string $label,
        public readonly string $status,
        public readonly string $summary,
    ) {
    }

    /**
     * Die Einzelheiten. Dieselben Bausteine wie in einer eigenständigen Mail,
     * damit der Bericht nichts kennen muss, was eine Mail nicht auch kann.
     */
    public function detail(MailMessage $message): self
    {
        $this->detail = $message;

        return $this;
    }

    public function hasDetail(): bool
    {
        return $this->detail !== null && $this->detail->blocks() !== [];
    }

    public function detailMessage(): ?MailMessage
    {
        return $this->detail;
    }

    /** Wohin man springt, wenn man mehr sehen will. */
    public function link(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * Rangfolge für die Anzeige: was dringend ist, steht oben.
     */
    public function weight(): int
    {
        return match ($this->status) {
            self::STATUS_ALERT => 0,
            self::STATUS_WARN => 1,
            self::STATUS_OK => 2,
            default => 3,
        };
    }
}
