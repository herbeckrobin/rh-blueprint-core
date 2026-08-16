<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Mail;

/**
 * Inhalt einer Suite-Mail, unabhängig von der Darstellung.
 *
 * Ein Modul beschreibt hier nur, WAS in der Mail steht. Wie daraus HTML und
 * Plaintext werden, entscheidet MailLayout. Beide Fassungen entstehen aus
 * dieser einen Quelle, damit die Textfassung nie hinter der HTML-Fassung
 * zurückbleibt: eine geteilte Rechnung ist eine Funktion, nicht zwei.
 *
 * Aufbau einer typischen Mail:
 *
 *   (new MailMessage('Wochenbericht Sicherheit', 'www.kunde.de'))
 *       ->status('ok', 'Diese Woche ist nichts Ernstes passiert.')
 *       ->section('Was geprüft wurde')
 *       ->rows(['Dateiprüfung' => 'am 13.08. gelaufen, ohne Befund'])
 *       ->button('Chronik ansehen', $url);
 */
final class MailMessage
{
    /** Tonlagen für status() und pill(). Mehr braucht es nicht. */
    public const TONE_OK = 'ok';
    public const TONE_INFO = 'info';
    public const TONE_WARN = 'warn';
    public const TONE_ALERT = 'alert';

    /** An den Betreiber oder seinen Betreuer. Darf technisch werden. */
    public const AUDIENCE_INTERNAL = 'internal';

    /** An einen Endkunden. Trägt das Aussehen der Website, keine Technik. */
    public const AUDIENCE_EXTERNAL = 'external';

    /** @var array<int, array<string, mixed>> */
    private array $blocks = [];

    private string $audience = self::AUDIENCE_INTERNAL;

    private string $source = '';

    private string $kindId = '';

    private bool $urgent = false;

    /**
     * @param string $title    Überschrift im Kopf der Mail.
     * @param string $subtitle Kleine Zeile darunter, in der Regel die Domain.
     */
    /** @var array<string, string> */
    private array $placeholders = [];

    public function __construct(
        public readonly string $title,
        public readonly string $subtitle = '',
    ) {
    }

    /**
     * Für wen die Mail ist. Entscheidet über das Aussehen und darüber, wie
     * offen sie werden darf: einem Endkunden nützt kein Dateipfad, und er
     * soll ihn auch nicht sehen.
     */
    public function audience(string $audience): self
    {
        $this->audience = $audience === self::AUDIENCE_EXTERNAL
            ? self::AUDIENCE_EXTERNAL
            : self::AUDIENCE_INTERNAL;

        return $this;
    }

    public function isExternal(): bool
    {
        return $this->audience === self::AUDIENCE_EXTERNAL;
    }

    /**
     * Die angemeldete Mail-Art (siehe MailKind), etwa "hardening.alert".
     *
     * Damit weiss der Versand, welche Einstellungen gelten: ob sie überhaupt
     * rausgeht, an wen, unter welchem Betreff. Das Modul ergibt sich daraus,
     * es muss nicht getrennt gesetzt werden.
     */
    public function kind(string $kindId): self
    {
        $this->kindId = $kindId;

        $kind = MailKind::get($kindId);

        if ($kind !== null) {
            $this->source = $kind->module;
            $this->audience = $kind->audience;
            $this->urgent = $kind->urgent;
        }

        return $this;
    }

    /**
     * Werte für die Platzhalter in Betreff und Zusatztext.
     *
     * Ohne die bleibt ein gepflegter Betreff wie "Deine Bestellung
     * {bestellnummer}" genau so im Postfach stehen. rh-shop hatte das gelöst,
     * der Core kannte es nicht: dort war der Betreff eine Zeichenkette, die
     * niemand mehr angefasst hat.
     *
     * @param array<string, string|int|float> $werte Name ohne Klammern => Wert.
     */
    public function placeholders(array $werte): self
    {
        $this->placeholders = array_map('strval', $werte);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function placeholderValues(): array
    {
        return $this->placeholders;
    }

    public function kindId(): string
    {
        return $this->kindId;
    }

    /**
     * Welches Modul die Mail ausgelöst hat, etwa "hardening". Landet im
     * Betreff und im Versandprotokoll.
     */
    public function from(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * Dringend heisst: geht auch dann raus, wenn die Wellenbremse gerade
     * greift. Für alles, was auf einen Einbruch hindeutet.
     */
    public function urgent(bool $urgent = true): self
    {
        $this->urgent = $urgent;

        return $this;
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    /**
     * Die Kernaussage, ganz oben und farbig hinterlegt. Wer nur eine Zeile
     * liest, soll diese lesen. Höchstens eine pro Mail.
     */
    public function status(string $tone, string $text): self
    {
        return $this->add('status', ['tone' => $tone, 'text' => $text]);
    }

    /** Fliesstext. */
    public function text(string $text): self
    {
        return $this->add('text', ['text' => $text]);
    }

    /** Abschnittsüberschrift. */
    public function section(string $text): self
    {
        return $this->add('section', ['text' => $text]);
    }

    /**
     * Schlüssel/Wert-Zeilen, etwa "Dateiprüfung: am 13.08. gelaufen".
     *
     * @param array<string, string> $rows
     */
    public function rows(array $rows): self
    {
        return $this->add('rows', ['rows' => $rows]);
    }

    /**
     * Aufzählung. Jeder Eintrag darf eine Tonlage tragen, dann bekommt er
     * links einen farbigen Balken.
     *
     * @param array<int, string|array{text: string, tone?: string, meta?: string}> $items
     */
    public function bullets(array $items): self
    {
        return $this->add('bullets', ['items' => $items]);
    }

    /** Handlungsaufforderung. Eine pro Mail, sonst weiss niemand wohin. */
    public function button(string $label, string $url): self
    {
        return $this->add('button', ['label' => $label, 'url' => $url]);
    }

    /** Kleingedrucktes am Ende des Inhalts. */
    public function muted(string $text): self
    {
        return $this->add('muted', ['text' => $text]);
    }

    /**
     * Ein Befehl oder Pfad zum Kopieren. Eigener Block, weil so etwas im
     * Fliesstext an der falschen Stelle umbricht und die Anführungszeichen
     * von manchen Clients in typografische verwandelt werden, womit der
     * Befehl nicht mehr läuft.
     */
    public function code(string $text): self
    {
        return $this->add('code', ['text' => $text]);
    }

    /** Trennlinie. */
    public function divider(): self
    {
        return $this->add('divider', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * Übernimmt einen fertigen Block aus einer anderen Nachricht.
     *
     * Braucht der Sammelbericht: er setzt sich aus den Abschnitten mehrerer
     * Module zusammen, und jedes davon hat seinen Beitrag schon als Nachricht
     * gebaut. Ohne das müsste jedes Modul seinen Inhalt zweimal beschreiben,
     * einmal für die eigene Mail und einmal für den Bericht.
     *
     * @param array<string, mixed> $block
     */
    public function raw(array $block): self
    {
        if (isset($block['type']) && is_string($block['type'])) {
            $this->blocks[] = $block;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function add(string $type, array $data): self
    {
        $this->blocks[] = ['type' => $type] + $data;

        return $this;
    }
}
