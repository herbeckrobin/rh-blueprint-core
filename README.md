# RH Blueprint Core

Geteilter Core für die rh-blueprint Plugin-Kollektion. Keine eigenständige Installation, sondern eine Composer-Library, die jedes rh-Plugin (rh-backup, rh-sync) bundelt.

## Was der Core bereitstellt

- **Version-Negotiation-Loader**, mehrere Plugins können den Core bundeln, die höchste Version gewinnt zur Laufzeit (Pattern wie Action Scheduler). Eine Instanz, eine geteilte Registry.
- **Service-Registry**, Plugins melden ihre öffentlichen APIs an und finden sich gegenseitig (`rh_blueprint()->services()`).
- **Settings-Framework**, eine geteilte Settings-Page, an der Plugins Tabs und Gruppen anmelden.
- **Environment-Helper**, Wrapper um `wp_get_environment_type()` für sichere Defaults.
- **Marken-Basics**, Dashboard-Cleanup und Support-Box.

## Einbinden in ein Plugin

```json
{
    "require": {
        "rh/blueprint-core": "^1.0"
    },
    "repositories": [
        { "type": "vcs", "url": "https://github.com/herbeckrobin/rh-blueprint-core" }
    ]
}
```

Der Core lädt sich über Composers `files`-Autoload selbst. Im Plugin nichts weiter nötig, ausser am `rh-blueprint/core/booted`-Hook einzuhaken:

```php
add_action('rh-blueprint/core/booted', function ($core) {
    $core->settings()->registerTab('tools', __('Tools', 'mein-plugin'), 20);
    $core->services()->register('mein-service', new MeineApi(), 1);
});
```

## Versionierung

Die Negotiation wählt immer die höchste geladene Version. Darum gilt: **Die Core-API ist ein Vertrag, nur additive Änderungen.** Ein Breaking Change ist ein neuer Major. `version.php` und der Git-Tag müssen übereinstimmen.

## Test

```bash
php tests/negotiation-test.php
```

## Lizenz

GPL-2.0-or-later
