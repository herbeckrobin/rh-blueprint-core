<?php

/**
 * Prüft, wann eine GitHub-Antwort die Update-Prüfungen pausiert. Läuft ohne WordPress.
 */

require_once __DIR__ . '/bootstrap.php';

use RhBlueprint\Core\GitHubRateLimit;

$jetzt = 1_800_000_000;

$faelle = [
    'Kontingent leer, Reset in 20 Minuten' => [403, ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string) ($jetzt + 1200)], $jetzt + 1200],
    '429 mit Retry-After' => [429, ['retry-after' => '120'], $jetzt + 120],
    'Retry-After unter einer Minute wird auf 60 angehoben' => [403, ['retry-after' => '5'], $jetzt + 60],
    'Reset weit in der Zukunft wird auf eine Stunde begrenzt' => [403, ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string) ($jetzt + 86400)], $jetzt + 3600],
    'Reset fehlt, kurze Pause' => [403, ['x-ratelimit-remaining' => '0'], $jetzt + 60],
    'Reset schon vorbei, kurze Pause' => [403, ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string) ($jetzt - 10)], $jetzt + 60],
    '403 mit Restkontingent ist kein Rate-Limit' => [403, ['x-ratelimit-remaining' => '42'], null],
    '403 ganz ohne Header ist kein Rate-Limit' => [403, [], null],
    '404 pausiert nicht' => [404, ['x-ratelimit-remaining' => '0'], null],
    '200 pausiert nicht' => [200, ['x-ratelimit-remaining' => '0'], null],
];

$fehler = 0;

foreach ($faelle as $name => [$status, $header, $erwartet]) {
    $ist = GitHubRateLimit::pauseUntil($status, $header, $jetzt);

    if ($ist === $erwartet) {
        echo "  ok   $name\n";
        continue;
    }

    echo '  FEHL ' . $name . ': erwartet ' . var_export($erwartet, true)
        . ', bekommen ' . var_export($ist, true) . "\n";
    $fehler++;
}

if ($fehler > 0) {
    echo "\n$fehler Fehler.\n";
    exit(1);
}

echo "\nAlles gruen.\n";
