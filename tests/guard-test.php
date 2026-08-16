<?php

/**
 * Prüft die Rechte- und Nonce-Prüfung.
 *
 * Zwei Zusagen, die beide der Grund für diese Klasse waren und die man einer
 * Codezeile nicht ansieht:
 *
 *   Die Reihenfolge. Rechte VOR Nonce. Andersherum bekommt jemand ohne Rechte
 *   bei abgelaufenem Nonce erst den Bestätigungsdialog zu sehen. Der Test
 *   zeichnet die Aufrufe auf und prüft die Reihenfolge, nicht nur, dass beide
 *   vorkamen.
 *
 *   Der Antwortcode. 403, nicht 500. Eine verweigerte Aktion ist kein
 *   Serverfehler, und Monitoring, das auf 5xx schaut, soll deswegen keinen
 *   Ausfall melden.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Admin\Guard;
use RhBlueprint\Core\Settings\SettingsPage;

$t = new TestErgebnis();

/** Aufgezeichnete Aufrufe, in der Reihenfolge ihres Auftretens. */
$GLOBALS['__ablauf'] = [];

/** Was current_user_can() antworten soll. */
$GLOBALS['__darf'] = true;

function current_user_can(string $cap): bool
{
    $GLOBALS['__ablauf'][] = 'rechte:' . $cap;

    return $GLOBALS['__darf'];
}

function check_admin_referer(string $action, string $feld): void
{
    $GLOBALS['__ablauf'][] = 'nonce:' . $action . ':' . $feld;
}

function check_ajax_referer(string $action, string $feld): void
{
    $GLOBALS['__ablauf'][] = 'nonce:' . $action . ':' . $feld;
}

/** Abbruch nachstellen: wp_die und wp_send_json_error beenden den Request. */
final class Abbruch extends \RuntimeException
{
    /** @param array<string, mixed> $daten */
    public function __construct(public readonly string $art, public readonly array $daten)
    {
        parent::__construct($art);
    }
}

/** @param array<string, mixed> $args */
function wp_die(string $text = '', string $titel = '', array $args = []): never
{
    $GLOBALS['__ablauf'][] = 'abbruch';

    throw new Abbruch('wp_die', $args);
}

/** @param array<string, mixed> $daten */
function wp_send_json_error(array $daten = [], int $code = 0): never
{
    $GLOBALS['__ablauf'][] = 'abbruch';

    throw new Abbruch('json', ['response' => $code]);
}

/**
 * Ruft eine Prüfung auf und gibt zurück, was dabei passiert ist.
 *
 * @return array{ablauf: array<int, string>, abbruch: ?Abbruch}
 */
function lauf(callable $fn): array
{
    $GLOBALS['__ablauf'] = [];
    $abbruch = null;

    try {
        $fn();
    } catch (Abbruch $e) {
        $abbruch = $e;
    }

    return ['ablauf' => $GLOBALS['__ablauf'], 'abbruch' => $abbruch];
}

// --- Der Kernpunkt: Rechte zuerst -------------------------------------------

$GLOBALS['__darf'] = false;

$r = lauf(static fn () => Guard::form('meine-aktion'));

$t->pruefe(
    $r['abbruch'] !== null,
    'ohne Rechte bricht das Formular ab'
);
$t->pruefe(
    $r['ablauf'] === ['rechte:' . SettingsPage::CAPABILITY, 'abbruch'],
    'und zwar bevor der Nonce geprüft wird',
    implode(' > ', $r['ablauf'])
);
$t->pruefe(
    ($r['abbruch']?->daten['response'] ?? null) === 403,
    'mit 403, nicht mit 500',
    var_export($r['abbruch']?->daten['response'] ?? null, true)
);

$r = lauf(static fn () => Guard::ajax('meine-aktion'));

$t->pruefe(
    $r['ablauf'] === ['rechte:' . SettingsPage::CAPABILITY, 'abbruch'],
    'dasselbe beim AJAX-Aufruf',
    implode(' > ', $r['ablauf'])
);
$t->pruefe(
    ($r['abbruch']?->daten['response'] ?? null) === 403,
    'auch dort 403'
);
$t->pruefe(
    $r['abbruch']?->art === 'json',
    'und als JSON, nicht als Seite'
);

// --- Mit Rechten läuft beides durch -----------------------------------------

$GLOBALS['__darf'] = true;

$r = lauf(static fn () => Guard::form('meine-aktion'));

$t->pruefe(
    $r['abbruch'] === null,
    'mit Rechten läuft das Formular durch'
);
$t->pruefe(
    $r['ablauf'] === ['rechte:' . SettingsPage::CAPABILITY, 'nonce:meine-aktion:_wpnonce'],
    'und prüft danach den Nonce',
    implode(' > ', $r['ablauf'])
);

$r = lauf(static fn () => Guard::ajax('meine-aktion'));

$t->pruefe(
    $r['ablauf'] === ['rechte:' . SettingsPage::CAPABILITY, 'nonce:meine-aktion:nonce'],
    'der AJAX-Weg nutzt das Feld "nonce", nicht "_wpnonce"',
    implode(' > ', $r['ablauf'])
);

// --- Eigene Rechte statt der Vorgabe ----------------------------------------

$r = lauf(static fn () => Guard::form('meine-aktion', 'manage_woocommerce'));

$t->pruefe(
    $r['ablauf'][0] === 'rechte:manage_woocommerce',
    'ein Modul kann eigene Rechte verlangen',
    $r['ablauf'][0]
);

$r = lauf(static fn () => Guard::form('meine-aktion', '', 'rhshop_nonce'));

$t->pruefe(
    $r['ablauf'][1] === 'nonce:meine-aktion:rhshop_nonce',
    'und ein eigenes Nonce-Feld',
    $r['ablauf'][1]
);

$t->abschluss();
