<?php

/**
 * Standalone-Test für den Negotiation-Loader + Core-Fundament.
 *
 * Kein PHPUnit nötig, läuft mit purem PHP:
 *   php tests/negotiation-test.php
 *
 * Stubbt die wenigen WP-Funktionen, die der Core berührt, und prüft:
 *   1. pickLatest wählt die höchste SemVer-Version.
 *   2. Der volle Flow (zwei Bundles anmelden -> loadLatest) bootet den
 *      Gewinner-Core und füllt die Service-Registry.
 *   3. Service-Registry Roundtrip inkl. Versions-Gate.
 *   4. Environment-Default ohne WP-Funktion = production.
 */

declare(strict_types=1);

// --- Minimale WP-Stubs -------------------------------------------------------
define('ABSPATH', __DIR__ . '/');

$GLOBALS['__hooks'] = [];

function add_action(string $hook, callable $cb, int $prio = 10, int $args = 1): void
{
    $GLOBALS['__hooks'][$hook][] = $cb;
}

function do_action(string $hook, mixed ...$args): void
{
    foreach ($GLOBALS['__hooks'][$hook] ?? [] as $cb) {
        $cb(...$args);
    }
}

function sanitize_key(string $key): string
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '');
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

$GLOBALS['__options'] = [];

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['__options'][$name] ?? $default;
}

// wp_get_environment_type bewusst NICHT definiert -> Environment muss auf production fallen.

// --- Test-Harness ------------------------------------------------------------
$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    if ($ok) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}\n";
        $failures++;
    }
}

$coreDir = dirname(__DIR__);

// --- 1. pickLatest pur -------------------------------------------------------
require_once $coreDir . '/loader/RhBlueprintCoreLoader.php';

check('pickLatest: 1.4.0 schlägt 1.0.0 und 1.2.0', RhBlueprintCoreLoader::pickLatest(['1.0.0', '1.4.0', '1.2.0']) === '1.4.0');
check('pickLatest: 2.0.0 schlägt 1.9.9', RhBlueprintCoreLoader::pickLatest(['1.9.9', '2.0.0']) === '2.0.0');
check('pickLatest: leer ergibt leeren String', RhBlueprintCoreLoader::pickLatest([]) === '');
check('pickLatest: 1.10.0 schlägt 1.9.0 (numerisch, nicht lexikografisch)', RhBlueprintCoreLoader::pickLatest(['1.9.0', '1.10.0']) === '1.10.0');

// --- 2. Voller Negotiation-Flow ---------------------------------------------
// Zwei Bundles melden sich an (hier beide aus demselben Core-Dir, die Auswahl
// trifft die Version). Der Loader hängt loadLatest an plugins_loaded.
RhBlueprintCoreLoader::declareVersion('1.0.0', $coreDir);
RhBlueprintCoreLoader::declareVersion('1.4.0', $coreDir);

do_action('plugins_loaded'); // löst loadLatest aus -> lädt bootstrap.php (Core::boot)
do_action('init');           // bootFeatures: Support-Tab/Group + core/booted-Hook

check('Negotiation: Version 1.4.0 hat gewonnen', RhBlueprintCoreLoader::winningVersion() === '1.4.0');
check('Negotiation: Gewinner-Dir gesetzt', RhBlueprintCoreLoader::winningDir() === $coreDir);
check('Core ist gebootet', \RhBlueprint\Core\Core::isBooted());
check('rh_blueprint() liefert den Singleton', rh_blueprint() instanceof \RhBlueprint\Core\Core);
check('Core kennt seine Version', rh_blueprint()->version() === '1.4.0');

// --- 3. Service-Registry Roundtrip ------------------------------------------
$fakeApi = new class () {
    public function ping(): string
    {
        return 'pong';
    }
};

rh_blueprint()->services()->register('backup', $fakeApi, 2);

$resolved = rh_blueprint()->services()->get('backup', 1);
check('Registry: Service abrufbar', $resolved === $fakeApi);
check('Registry: has() true bei erfüllter minVersion', rh_blueprint()->services()->has('backup', 2));
check('Registry: get() null wenn minVersion zu hoch', rh_blueprint()->services()->get('backup', 3) === null);
check('Registry: unbekannter Service ist null', rh_blueprint()->services()->get('sync', 1) === null);
check('Registry: versionOf liefert 2', rh_blueprint()->services()->versionOf('backup') === 2);

// --- 4. Environment-Default --------------------------------------------------
check('Environment: ohne WP-Funktion = production', \RhBlueprint\Core\Environment::type() === 'production');
check('Environment: isProduction true', \RhBlueprint\Core\Environment::isProduction());
check('Environment: isDevelopment false', \RhBlueprint\Core\Environment::isDevelopment() === false);

// --- 5. Settings-Hub + Core-Features ----------------------------------------
$settings = rh_blueprint()->settings();
check('settings() liefert SettingsHub', $settings instanceof \RhBlueprint\Core\Settings\SettingsHub);
check('storage() liefert Storage', rh_blueprint()->storage() instanceof \RhBlueprint\Core\Storage);

// Der Core registriert beim Boot bereits den Support-Tab (Prio 10) + Support-Group.
check('Support-Tab ist als Core-Feature vorhanden', isset($settings->tabs()['support']));
$hasSupportGroup = false;
foreach ($settings->groups() as $g) {
    if ($g->id() === 'support_info' && $g->tab() === 'support') {
        $hasSupportGroup = true;
    }
}
check('Support-Group ist als Core-Feature registriert', $hasSupportGroup);

$settings->registerTab('beta', 'Beta', 30);
$settings->registerTab('alpha', 'Alpha', 15);
$settings->registerTab('gamma', 'Gamma', 20);
check('Tabs nach Priorität sortiert (support, alpha, gamma, beta)', array_keys($settings->tabs()) === ['support', 'alpha', 'gamma', 'beta']);
check('Tab-Label korrekt zugeordnet', $settings->tabs()['gamma'] === 'Gamma');

$group = new class () implements \RhBlueprint\Core\Settings\GroupInterface {
    public function id(): string
    {
        return 'support';
    }

    public function tab(): string
    {
        return 'alpha';
    }

    public function title(): string
    {
        return 'Support';
    }

    public function description(): string
    {
        return '';
    }

    public function fields(): array
    {
        return [];
    }
};
$settings->registerGroup($group);
check('registerGroup landet in groups()', in_array($group, $settings->groups(), true));

check('optionName Konvention', \RhBlueprint\Core\Settings\SettingsHub::optionName('support') === 'rhbp_settings_support');
check('fieldName Konvention', \RhBlueprint\Core\Settings\SettingsHub::fieldName('support', 'email') === 'rhbp_settings_support[email]');
check('optionGroupForTab Konvention', \RhBlueprint\Core\Settings\SettingsHub::optionGroupForTab('tools') === 'rh_blueprint_settings_tools');

// --- 6. Helper rhbp_support_info / rhbp_setting ------------------------------
$GLOBALS['__options']['rhbp_settings_support_info'] = ['name' => 'Robin Herbeck', 'email' => 'hallo@robinherbeck.de'];

check('rhbp_support_info() liefert das ganze Array', rhbp_support_info() === ['name' => 'Robin Herbeck', 'email' => 'hallo@robinherbeck.de']);
check('rhbp_support_info(key) liefert den Einzelwert', rhbp_support_info('name') === 'Robin Herbeck');
check('rhbp_support_info(unbekannt) ist null', rhbp_support_info('telefon') === null);
check('rhbp_setting mit Default greift bei fehlendem Key', rhbp_setting('support_info', 'role', 'unbekannt') === 'unbekannt');

// --- Ergebnis ----------------------------------------------------------------
echo "\n";
if ($failures === 0) {
    echo "OK, alle Checks bestanden.\n";
    exit(0);
}

echo "FEHLER: {$failures} Check(s) fehlgeschlagen.\n";
exit(1);
