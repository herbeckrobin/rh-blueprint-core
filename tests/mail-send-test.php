<?php

/**
 * Prüft den Weg, den eine Mail durch den Core nimmt.
 *
 * Der eine Fall, für den diese Fassade überhaupt existiert: rh-hardening muss
 * eine Einbruchsmeldung auch dann verschicken können, wenn das E-Mail-Modul
 * nicht installiert ist. Eine Website, auf der nur Hardening läuft, darf im
 * Ernstfall nicht stumm sein. Getestet wird deshalb vor allem die
 * Rückfallebene, nicht der bequeme Weg mit rh-smtp.
 *
 * Dazu die Entscheidungen, die hier und nicht im E-Mail-Modul fallen:
 * abgeschaltet heisst abgeschaltet, ein eigener Empfänger schlägt den
 * mitgegebenen, und der Betreff wird gefüllt, bevor er das Haus verlässt.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Mail\Mail;
use RhBlueprint\Core\Mail\MailKind;
use RhBlueprint\Core\Mail\MailMessage;
use RhBlueprint\Core\Mail\MailSettings;

$t = new TestErgebnis();

$GLOBALS['__optionen'] = [];
$GLOBALS['__filter'] = [];

/** Alles, was über wp_mail rausging. */
$GLOBALS['__versand'] = [];

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['__optionen'][$name] ?? $default;
}

function update_option(string $name, mixed $wert, bool $autoload = true): bool
{
    $GLOBALS['__optionen'][$name] = $wert;

    return true;
}

function add_filter(string $hook, callable $cb, int $prio = 10, int $args = 1): void
{
    $GLOBALS['__filter'][$hook][] = $cb;
}

function apply_filters(string $hook, mixed $wert, mixed ...$args): mixed
{
    foreach ($GLOBALS['__filter'][$hook] ?? [] as $cb) {
        $wert = $cb($wert, ...$args);
    }

    return $wert;
}

function home_url(string $pfad = '/'): string
{
    return 'https://kunde.example' . $pfad;
}

function wp_parse_url(string $url, int $teil = -1): mixed
{
    return parse_url($url, $teil);
}

function wp_strip_all_tags(string $text): string
{
    return trim(strip_tags($text));
}

/** @param string|array<int, string> $to */
function wp_mail(string|array $to, string $subject, string $body, mixed $headers = ''): bool
{
    $GLOBALS['__versand'][] = ['to' => $to, 'subject' => $subject, 'body' => $body];

    return true;
}

function sanitize_email(string $wert): string
{
    return trim($wert);
}

function is_email(string $wert): string|false
{
    return filter_var($wert, FILTER_VALIDATE_EMAIL) === false ? false : $wert;
}

function sanitize_text_field(string $wert): string
{
    return trim(strip_tags($wert));
}

function sanitize_textarea_field(string $wert): string
{
    return trim(strip_tags($wert));
}

function zuruecksetzen(): void
{
    $GLOBALS['__versand'] = [];
    $GLOBALS['__optionen'] = [];
    $GLOBALS['__filter'] = [];
    MailSettings::flushCache();
}

/** @return array{to: string|array<int, string>, subject: string, body: string}|null */
function letzte(): ?array
{
    return $GLOBALS['__versand'][count($GLOBALS['__versand']) - 1] ?? null;
}

MailKind::register('hardening.breach', [
    'module' => 'hardening',
    'label' => 'Einbruchsmeldung',
    'subject' => 'Auffälliger Zugriff auf {seite}',
    'lockable' => false,
    'placeholders' => ['seite', 'ip'],
]);

MailKind::register('shop.shipped', [
    'module' => 'shop',
    'label' => 'Versandbestätigung',
    'subject' => 'Bestellung {nummer} ist unterwegs',
    'placeholders' => ['nummer'],
]);

// --- Ohne E-Mail-Modul: die Mail geht trotzdem raus --------------------------

zuruecksetzen();

$m = new MailMessage('Einbruchsversuch');
$m->kind('hardening.breach');
$m->placeholders(['seite' => 'kunde.example', 'ip' => '203.0.113.7']);
$m->text('Fünf Fehlversuche innerhalb einer Minute.');

$erfolg = Mail::send('robin@example.com', 'Fällt zurück auf die Vorgabe', $m);

$t->pruefe($erfolg, 'ohne E-Mail-Modul meldet der Versand trotzdem Erfolg');
$t->pruefe(count($GLOBALS['__versand']) === 1, 'und genau eine Mail geht raus');
$t->pruefe(letzte()['to'] === 'robin@example.com', 'an den mitgegebenen Empfänger');
$t->pruefe(
    str_contains(letzte()['body'], 'Fünf Fehlversuche innerhalb einer Minute.'),
    'der Text steht als Klartext drin',
    letzte()['body']
);
$t->pruefe(
    ! str_contains(letzte()['body'], '<'),
    'und zwar ohne Markup, das im Nur-Text-Postfach niemand lesen kann'
);

// Der Betreff: Vorgabe der Art schlägt den mitgegebenen, Platzhalter gefüllt,
// Domain davor, damit ein Postfach mit fünfzehn Websites sortierbar bleibt.
$t->pruefe(
    letzte()['subject'] === '[kunde.example] Auffälliger Zugriff auf kunde.example',
    'die Vorgabe der Mail-Art schlägt den mitgegebenen Betreff, mit Domain davor',
    letzte()['subject']
);

// --- Abgeschaltet heisst abgeschaltet ----------------------------------------

zuruecksetzen();

MailSettings::save('shop.shipped', ['enabled' => false]);

$m = new MailMessage('Unterwegs');
$m->kind('shop.shipped');

$erfolg = Mail::send('kunde@example.com', 'Unterwegs', $m);

$t->pruefe(! $erfolg, 'eine abgeschaltete Mail meldet keinen Erfolg');
$t->pruefe($GLOBALS['__versand'] === [], 'und geht nicht raus');

// Eine Pflichtmail lässt sich nicht abschalten, auch wenn es jemand versucht.
MailSettings::save('hardening.breach', ['enabled' => false]);

$m = new MailMessage('Einbruchsversuch');
$m->kind('hardening.breach');

Mail::send('robin@example.com', 'Meldung', $m);

$t->pruefe(
    count($GLOBALS['__versand']) === 1,
    'eine Pflichtmail geht raus, auch wenn der Schalter auf aus steht'
);

// --- Was der Betreiber gepflegt hat, gewinnt ---------------------------------

zuruecksetzen();

MailSettings::save('shop.shipped', [
    'enabled' => true,
    'recipient' => 'lager@example.com',
    'subject' => 'Paket {nummer} raus',
    'note' => 'Rückfragen an das Lager, nicht an die Zentrale.',
]);

$m = new MailMessage('Unterwegs');
$m->kind('shop.shipped');
$m->placeholders(['nummer' => 'RH-000042']);
$m->text('Das Paket hat das Haus verlassen.');

Mail::send('kunde@example.com', 'Wird überschrieben', $m);

$t->pruefe(
    letzte()['to'] === 'lager@example.com',
    'ein gepflegter Empfänger schlägt den mitgegebenen',
    is_string(letzte()['to']) ? letzte()['to'] : ''
);
$t->pruefe(
    letzte()['subject'] === '[kunde.example] Paket RH-000042 raus',
    'ein gepflegter Betreff schlägt die Vorgabe, Platzhalter gefüllt',
    letzte()['subject']
);
$t->pruefe(
    str_contains(letzte()['body'], 'Rückfragen an das Lager'),
    'der gepflegte Zusatztext landet in der Mail'
);

// Ein Platzhalter ohne Wert darf nicht in geschweiften Klammern beim Kunden
// ankommen. Das ist der Fehler, den man erst im fremden Postfach sieht.
zuruecksetzen();

MailSettings::save('shop.shipped', ['enabled' => true]);

$m = new MailMessage('Unterwegs');
$m->kind('shop.shipped');

Mail::send('kunde@example.com', 'egal', $m);

$t->pruefe(
    ! str_contains(letzte()['subject'], '{'),
    'ein Platzhalter ohne Wert erscheint nicht in Klammern im Betreff',
    letzte()['subject']
);

// --- Mit E-Mail-Modul: das übernimmt vollständig -----------------------------

zuruecksetzen();

$uebernommen = [];

add_filter('rh-blueprint/mail/send', static function (
    mixed $handled,
    string|array $to,
    string $subject,
    MailMessage $message,
    string $footerNote
) use (&$uebernommen): bool {
    $uebernommen[] = ['to' => $to, 'subject' => $subject];

    return true;
});

$m = new MailMessage('Unterwegs');
$m->kind('shop.shipped');
$m->placeholders(['nummer' => 'RH-000042']);

$erfolg = Mail::send('kunde@example.com', 'egal', $m);

$t->pruefe($erfolg, 'mit E-Mail-Modul meldet der Versand Erfolg');
$t->pruefe($GLOBALS['__versand'] === [], 'und wp_mail wird nicht mehr angefasst');
$t->pruefe(count($uebernommen) === 1, 'das Modul hat übernommen');
$t->pruefe(
    $uebernommen[0]['subject'] === '[kunde.example] Bestellung RH-000042 ist unterwegs',
    'und bekommt den fertigen Betreff, nicht die Vorlage',
    $uebernommen[0]['subject']
);

// Sagt das Modul nein, geht die Mail nicht doppelt über die Rückfallebene raus.
zuruecksetzen();

add_filter('rh-blueprint/mail/send', static fn (): bool => false);

$m = new MailMessage('Unterwegs');
$m->kind('shop.shipped');

$erfolg = Mail::send('kunde@example.com', 'egal', $m);

$t->pruefe(! $erfolg, 'lehnt das Modul ab, meldet der Versand das weiter');
$t->pruefe(
    $GLOBALS['__versand'] === [],
    'und die Mail geht nicht zusätzlich über die Rückfallebene raus'
);

// --- Eine Mail ohne angemeldete Art ------------------------------------------
//
// Nicht jede Mail gehört in die Registry. Eine einmalige Testmail etwa hat
// keine Art, und der Weg muss trotzdem tragen.

zuruecksetzen();

$m = new MailMessage('Testmail');
$m->text('Wenn du das liest, geht der Versand.');

$erfolg = Mail::send('robin@example.com', 'Test', $m);

$t->pruefe($erfolg && count($GLOBALS['__versand']) === 1, 'eine Mail ohne Art geht raus');
$t->pruefe(
    letzte()['subject'] === '[kunde.example] Test',
    'und bekommt trotzdem die Domain davor',
    letzte()['subject']
);

$t->abschluss();
