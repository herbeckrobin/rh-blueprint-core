<?php

/**
 * Prüft die Entscheidung, wann nach einem Update der Bytecode-Zwischenspeicher
 * geleert wird. Läuft ohne WordPress.
 */

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\UpdateChecker;

$checker = new UpdateChecker('rh-motion', '/pfad/rh-motion/rh-motion.php');

$faelle = [
    'eigenes Plugin aktualisiert' => [
        ['action' => 'update', 'type' => 'plugin', 'plugins' => ['rh-motion/rh-motion.php']],
        true,
    ],
    'eigenes Plugin in einer Sammelaktualisierung' => [
        ['action' => 'update', 'type' => 'plugin', 'plugins' => ['akismet/akismet.php', 'rh-motion/rh-motion.php']],
        true,
    ],
    'fremdes Plugin' => [
        ['action' => 'update', 'type' => 'plugin', 'plugins' => ['akismet/akismet.php']],
        false,
    ],
    'Neuinstallation, kein Update' => [
        ['action' => 'install', 'type' => 'plugin', 'plugins' => ['rh-motion/rh-motion.php']],
        false,
    ],
    'Theme statt Plugin' => [
        ['action' => 'update', 'type' => 'theme', 'plugins' => ['rh-motion/rh-motion.php']],
        false,
    ],
    'Liste fehlt' => [
        ['action' => 'update', 'type' => 'plugin'],
        false,
    ],
    'Liste ist kein Array' => [
        ['action' => 'update', 'type' => 'plugin', 'plugins' => 'rh-motion/rh-motion.php'],
        false,
    ],
    'aehnlicher Name, nicht der eigene' => [
        ['action' => 'update', 'type' => 'plugin', 'plugins' => ['rh-motion-pro/rh-motion-pro.php']],
        false,
    ],
];

$fehler = 0;

foreach ($faelle as $name => [$extra, $erwartet]) {
    $ist = $checker->shouldReset($extra);

    if ($ist === $erwartet) {
        echo "  ok   $name\n";
        continue;
    }

    echo '  FEHL ' . $name . ': erwartet ' . var_export($erwartet, true)
        . ', bekommen ' . var_export($ist, true) . "\n";
    $fehler++;
}

// Der Slug bestimmt, worauf reagiert wird. Ein zweites Modul darf sich nicht
// vom Update des ersten angesprochen fuehlen.
$anderes = new UpdateChecker('rh-seo', '/pfad/rh-seo/rh-seo.php');

if ($anderes->shouldReset(['action' => 'update', 'type' => 'plugin', 'plugins' => ['rh-motion/rh-motion.php']])) {
    echo "  FEHL fremdes Modul reagiert auf ein Update, das ihm nicht gilt\n";
    $fehler++;
} else {
    echo "  ok   jedes Modul reagiert nur auf sein eigenes Update\n";
}

if ($fehler > 0) {
    echo "\n$fehler Fehler.\n";
    exit(1);
}

echo "\nAlles gruen.\n";
