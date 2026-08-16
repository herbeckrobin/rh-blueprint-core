<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Mail;

/**
 * Was der Betreiber je Mail-Art einstellen kann.
 *
 * Vier Dinge, mehr braucht es nicht: ob sie überhaupt rausgeht, an wen,
 * unter welchem Betreff und mit welchem Zusatztext. Alles leer heisst: nimm
 * die Vorgabe des Moduls. Das ist wichtiger als es klingt, denn eine
 * gespeicherte Kopie des Vorgabetexts würde bei einem Update nicht
 * mitwandern und irgendwann vom Rest der Suite abweichen.
 *
 * Liegt im Core und nicht im E-Mail-Modul, weil der Schalter auch dann
 * greifen muss, wenn das Modul nicht installiert ist. Wer eine Meldung
 * abgeschaltet hat, will sie nicht wiederhaben, nur weil er ein Plugin
 * deinstalliert.
 */
final class MailSettings
{
    public const OPTION = 'rhbp_mail_kinds';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function for(string $kindId): array
    {
        $all = self::all();

        return is_array($all[$kindId] ?? null) ? $all[$kindId] : [];
    }

    /**
     * Geht diese Mail-Art raus?
     */
    public static function enabled(string $kindId): bool
    {
        $stored = self::for($kindId);

        if (array_key_exists('enabled', $stored)) {
            return (bool) $stored['enabled'];
        }

        $kind = MailKind::get($kindId);

        // Unbekannte Art nicht verschlucken: lieber eine Mail zu viel als eine
        // Meldung, die niemand je sieht.
        return $kind === null ? true : $kind->default;
    }

    /**
     * Empfänger, leer heisst: der Aufrufer entscheidet.
     */
    public static function recipient(string $kindId): string
    {
        $value = (string) (self::for($kindId)['recipient'] ?? '');

        return is_email($value) ? $value : '';
    }

    /**
     * Eigener Betreff. Leer heisst: der des Moduls.
     */
    public static function subject(string $kindId): string
    {
        return trim((string) (self::for($kindId)['subject'] ?? ''));
    }

    /**
     * Zusatztext, der unter den Inhalt gesetzt wird. Für Hinweise, die nur auf
     * dieser Website gelten ("bei Rückfragen an die Zentrale").
     */
    public static function note(string $kindId): string
    {
        return trim((string) (self::for($kindId)['note'] ?? ''));
    }

    /**
     * Absendername. Leer heisst: der Vorgabe des Mailversands folgen.
     */
    public static function fromName(string $kindId): string
    {
        return trim((string) (self::for($kindId)['from_name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function save(string $kindId, array $values): void
    {
        $all = self::all();

        $all[$kindId] = [
            'enabled' => ! empty($values['enabled']),
            'recipient' => sanitize_email((string) ($values['recipient'] ?? '')),
            'subject' => sanitize_text_field((string) ($values['subject'] ?? '')),
            'from_name' => sanitize_text_field((string) ($values['from_name'] ?? '')),
            'note' => sanitize_textarea_field((string) ($values['note'] ?? '')),
        ];

        update_option(self::OPTION, $all, false);
        self::$cache = $all;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = get_option(self::OPTION, []);
        self::$cache = is_array($stored) ? $stored : [];

        return self::$cache;
    }

    /** Nur für Tests und nach einem Import. */
    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
