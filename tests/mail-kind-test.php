<?php

/**
 * Prüft die drei Merkmale, die aus rh-shops Mail-Modell in den Core kamen:
 * Vorgabe-Betreff, Platzhalter und der Schutz von Pflichtmails.
 */

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\Mail\MailKind;

$t = new TestErgebnis();

// --- Anmeldung ---------------------------------------------------------------

$pflicht = MailKind::register('shop.confirmation', [
    'module' => 'shop',
    'label' => 'Bestellbestätigung',
    'subject' => 'Deine Bestellung {bestellnummer}',
    'lockable' => false,
    'placeholders' => ['bestellnummer', 'name', 'summe'],
]);

$frei = MailKind::register('shop.shipped', [
    'module' => 'shop',
    'label' => 'Versandbestätigung',
    'subject' => 'Bestellung {bestellnummer} ist unterwegs',
    'placeholders' => ['bestellnummer', 'sendungsnummer'],
]);

$schlicht = MailKind::register('shop.schlicht', ['module' => 'shop', 'label' => 'Ohne alles']);

$t->pruefe($pflicht->subject === 'Deine Bestellung {bestellnummer}', 'der Vorgabe-Betreff kommt an');
$t->pruefe($pflicht->placeholders === ['bestellnummer', 'name', 'summe'], 'die Platzhalter kommen an');
$t->pruefe(! $pflicht->lockable, 'eine Pflichtmail ist als solche erkennbar');
$t->pruefe($frei->lockable, 'ohne Angabe ist eine Mail abschaltbar');
$t->pruefe($schlicht->subject === '' && $schlicht->placeholders === [], 'ohne Angaben bleibt es leer, nicht null');
$t->pruefe($schlicht->lockable, 'die Vorgabe ist abschaltbar, gesperrt ist die Ausnahme');

// --- Betreff füllen ----------------------------------------------------------

$t->pruefe(
    $pflicht->fillSubject('', ['bestellnummer' => 'RH-000042']) === 'Deine Bestellung RH-000042',
    'ohne eigene Vorlage wird die Vorgabe gefüllt'
);

$t->pruefe(
    $pflicht->fillSubject('Danke, {name}!', ['name' => 'Robin']) === 'Danke, Robin!',
    'eine eigene Vorlage schlägt die Vorgabe'
);

$t->pruefe(
    $pflicht->fillSubject('Nr {bestellnummer} für {name}', ['bestellnummer' => '7', 'name' => 'Robin'])
        === 'Nr 7 für Robin',
    'mehrere Platzhalter in einem Betreff'
);

// Der Fall, der beim Kunden ankommt, wenn niemand aufpasst.
$t->pruefe(
    $pflicht->fillSubject('Deine Bestellung {bestellnummer}', []) === 'Deine Bestellung',
    'ein ungefüllter Platzhalter landet nicht in geschweiften Klammern beim Kunden'
);

$t->pruefe(
    $pflicht->fillSubject('{unbekannt} Text', []) === 'Text',
    'auch ein Platzhalter, den niemand kennt, verschwindet'
);

$t->pruefe(
    $pflicht->fillSubject('Zahl {n}', ['n' => 42]) === 'Zahl 42',
    'Zahlen werden eingesetzt, nicht nur Zeichenketten'
);

// --- Registry ----------------------------------------------------------------

$t->pruefe(count(MailKind::all('shop')) === 3, 'die Registry kennt alle drei');
$t->pruefe(MailKind::get('shop.confirmation') === $pflicht, 'eine Art ist über ihre Kennung erreichbar');
$t->pruefe(MailKind::get('gibtesnicht') === null, 'eine unbekannte Kennung gibt null, nicht irgendwas');
$t->pruefe(MailKind::moduleHasMail('shop'), 'das Modul verschickt etwas');
$t->pruefe(! MailKind::moduleHasMail('motion'), 'ein Modul ohne Anmeldung verschickt nichts');

// --- Der Weg durch MailMessage ----------------------------------------------

// Die Platzhalter reisen an der Nachricht mit, nicht am Betreff: der Betreff
// steht in den Einstellungen, die Werte kennt erst der Aufrufer.
$m = new RhBlueprint\Core\Mail\MailMessage('Titel');
$m->kind('shop.confirmation');
$m->placeholders(['bestellnummer' => 'RH-000042', 'name' => 'Robin']);

$t->pruefe(
    $m->placeholderValues() === ['bestellnummer' => 'RH-000042', 'name' => 'Robin'],
    'die Werte hängen an der Nachricht'
);
$t->pruefe($m->kindId() === 'shop.confirmation', 'und die Art auch');

$m2 = new RhBlueprint\Core\Mail\MailMessage('Titel');
$t->pruefe($m2->placeholderValues() === [], 'ohne Angabe ist die Liste leer, nicht null');

$m3 = new RhBlueprint\Core\Mail\MailMessage('Titel');
$m3->placeholders(['zahl' => 7]);
$t->pruefe($m3->placeholderValues() === ['zahl' => '7'], 'Zahlen werden zu Zeichenketten');

$t->abschluss();
