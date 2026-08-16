<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Mail;

/**
 * Die eine Stelle, über die jedes Modul seine Mails verschickt.
 *
 * Hier steht bewusst fast nichts. Das Aussehen, der Sammelbericht, der
 * Testmodus und die Wellenbremse liegen im E-Mail-Modul (rh-smtp), weil das
 * die Transportebene ist und ohnehin jede Mail sieht.
 *
 * Der Grund für diese Fassade: rh-hardening muss eine Einbruchsmeldung auch
 * dann verschicken können, wenn das E-Mail-Modul nicht installiert ist. Sonst
 * wäre eine Website, auf der nur Hardening läuft, im Ernstfall stumm. Also
 * dasselbe Muster wie beim Transport: ohne rh-smtp geht die Mail über PHP,
 * mit rh-smtp über SMTP, und die Module merken nichts davon.
 *
 * Ist das Modul da, übernimmt es. Ist es nicht da, geht dieselbe Nachricht
 * als schlichter Text raus. Nichts bricht, es sieht nur nüchterner aus.
 */
final class Mail
{
    /**
     * Verschickt eine Nachricht, die sofort raus soll.
     *
     * Ob eine Mail sofort geht oder in den Sammelbericht wandert, entscheidet
     * das Modul im Code, nicht der Kunde in den Einstellungen. Eine
     * Bestellbestätigung sammelt man nicht.
     *
     * @param string|array<int, string> $to
     */
    public static function send(string|array $to, string $subject, MailMessage $message, string $footerNote = ''): bool
    {
        $kindId = $message->kindId();

        if ($kindId !== '') {
            // Abgeschaltet heisst abgeschaltet. Die Prüfung sitzt hier und
            // nicht in den Modulen, sonst müsste jedes daran denken.
            if (! MailSettings::enabled($kindId)) {
                return false;
            }

            $eigenerEmpfaenger = MailSettings::recipient($kindId);
            if ($eigenerEmpfaenger !== '') {
                $to = $eigenerEmpfaenger;
            }

            $kind = MailKind::get($kindId);
            $werte = $message->placeholderValues();

            $eigenerBetreff = MailSettings::subject($kindId);

            // Reihenfolge: was der Betreiber gepflegt hat, sonst die Vorgabe
            // der Mail-Art, sonst was der Aufrufer mitgibt.
            if ($eigenerBetreff === '' && $kind !== null && $kind->subject !== '') {
                $eigenerBetreff = $kind->subject;
            }

            if ($eigenerBetreff !== '') {
                // Platzhalter füllen. Ohne das steht "{bestellnummer}" wörtlich
                // im Postfach, und genau das ist der Grund, warum der Core
                // diesen Schritt braucht und nicht nur ein Textfeld.
                $subject = $kind !== null
                    ? $kind->fillSubject($eigenerBetreff, $werte)
                    : self::fillPlaceholders($eigenerBetreff, $werte);
            }

            $zusatz = MailSettings::note($kindId);
            if ($zusatz !== '') {
                $message->muted(self::fillPlaceholders($zusatz, $werte));
            }
        }

        // Der Betreff wird hier gebaut und nicht im E-Mail-Modul: er gehört zum
        // Kanal, nicht zur Ausstattung. Sonst verlieren Installationen ohne das
        // Modul die Kennzeichnung, und im Postfach lässt sich nicht mehr nach
        // Website sortieren.
        $subject = self::subject($subject, $message);

        /**
         * Das E-Mail-Modul hängt sich hier ein und übernimmt den Versand
         * vollständig. Gibt es keinen Abnehmer, bleibt der Wert null und die
         * Rückfallebene unten greift.
         *
         * @param bool|null                 $handled
         * @param string|array<int, string> $to
         * @param string                    $subject
         * @param MailMessage               $message
         * @param string                    $footerNote
         */
        $handled = apply_filters('rh-blueprint/mail/send', null, $to, $subject, $message, $footerNote);

        if ($handled !== null) {
            return (bool) $handled;
        }

        return self::sendPlain($to, $subject, $message, $footerNote);
    }

    /**
     * Rückfallebene ohne E-Mail-Modul: dieselbe Nachricht als reiner Text.
     *
     * @param string|array<int, string> $to
     */
    private static function sendPlain(string|array $to, string $subject, MailMessage $message, string $footerNote): bool
    {
        return (bool) wp_mail($to, $subject, self::plainText($message, $footerNote));
    }

    /**
     * Die Nachricht als reiner Text. Liegt hier und nicht im E-Mail-Modul,
     * weil die Rückfallebene ohne dieses Modul auskommen muss.
     */
    public static function plainText(MailMessage $message, string $footerNote = ''): string
    {
        $lines = [mb_strtoupper($message->title)];

        if ($message->subtitle !== '') {
            $lines[] = $message->subtitle;
        }

        foreach ($message->blocks() as $block) {
            $lines[] = '';

            switch ($block['type']) {
                case 'section':
                    $lines[] = mb_strtoupper((string) $block['text']);
                    break;

                case 'status':
                case 'text':
                case 'muted':
                    $lines[] = (string) $block['text'];
                    break;

                case 'code':
                    $lines[] = '    ' . (string) $block['text'];
                    break;

                case 'rows':
                    /** @var array<string, string> $rows */
                    $rows = $block['rows'];
                    foreach ($rows as $key => $value) {
                        $lines[] = $key . ': ' . $value;
                    }
                    break;

                case 'bullets':
                    /** @var array<int, string|array{text: string, tone?: string, meta?: string}> $items */
                    $items = $block['items'];
                    foreach ($items as $item) {
                        $text = is_array($item) ? (string) $item['text'] : (string) $item;
                        $meta = is_array($item) ? (string) ($item['meta'] ?? '') : '';
                        $lines[] = '* ' . $text . ($meta !== '' ? ' (' . $meta . ')' : '');
                    }
                    break;

                case 'button':
                    $lines[] = (string) $block['label'] . ': ' . (string) $block['url'];
                    break;

                case 'divider':
                    $lines[] = str_repeat('.', 40);
                    break;

                case 'html':
                    // Fertiges HTML eines Moduls. Für die Nur-Text-Fassung
                    // werden die Tags entfernt, sonst steht im Postfach eines
                    // Nur-Text-Lesers eine Wand aus Markup.
                    $klartext = wp_strip_all_tags(
                        str_replace(['</p>', '<br>', '<br/>', '<br />', '</tr>'], "\n", (string) ($block['html'] ?? ''))
                    );
                    $klartext = trim((string) preg_replace('/\n{3,}/', "\n\n", $klartext));

                    if ($klartext !== '') {
                        $lines[] = $klartext;
                    }
                    break;
            }
        }

        if ($footerNote !== '') {
            $lines[] = '';
            $lines[] = str_repeat('.', 40);
            $lines[] = $footerNote;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Einheitliche Betreffzeile.
     *
     * Klingt nach Kosmetik, entscheidet aber darüber, ob ein Postfach
     * sortierbar bleibt. Wer fünfzehn Websites betreut, bekommt Mails aus einem
     * Dutzend Modulen, und die einzige Grösse, die dabei durchgängig trägt, ist
     * die Domain. Deshalb steht sie vorn:
     *
     *     [kunde.de] Wochenbericht Sicherheit
     *     [kunde.de] Sicherung fehlgeschlagen
     *
     * Damit greift im Mailprogramm eine Regel pro Kunde statt eine pro Modul.
     * Mails an Endkunden bleiben unangetastet, dort hat eine Domain in eckigen
     * Klammern nichts zu suchen.
     */
    public static function subject(string $subject, MailMessage $message): string
    {
        if ($message->isExternal()) {
            return $subject;
        }

        $host = self::host();

        if ($host === '' || str_contains($subject, '[' . $host . ']')) {
            return $subject;
        }

        // Frühere Betreffe trugen ihr Thema in Klammern vorn ("[Sicherheit] …").
        // Das fällt weg, sonst stehen zwei Klammerblöcke voreinander.
        $subject = (string) preg_replace('/^\[[^\]]+\]\s*/', '', $subject);

        return '[' . $host . '] ' . $subject;
    }

    /**
     * Setzt Platzhalter ein und entfernt, was niemand gefüllt hat.
     *
     * @param array<string, string> $werte
     */
    private static function fillPlaceholders(string $vorlage, array $werte): string
    {
        foreach ($werte as $name => $wert) {
            $vorlage = str_replace('{' . $name . '}', $wert, $vorlage);
        }

        return trim((string) preg_replace('/\s*\{[a-z0-9_]+\}/i', '', $vorlage));
    }

    public static function host(): string
    {
        $host = wp_parse_url((string) home_url('/'), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }

    /**
     * Gibt es jemanden, der Sammelberichte verschickt?
     *
     * Module fragen das, um im Backend darauf hinzuweisen, dass ihr Beitrag
     * gerade nirgends landet.
     */
    public static function hasReporting(): bool
    {
        return (bool) apply_filters('rh-blueprint/mail/has_reporting', false);
    }
}
