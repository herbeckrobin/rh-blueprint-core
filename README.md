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

## Update-Prüfung mit GitHub-Token

Die Module holen ihre Updates über die GitHub-API. Ohne Token erlaubt GitHub 60 Anfragen je Stunde und IP, und alle Sites eines Servers teilen sich dieses Kontingent. Eine Prüfung kostet pro Modul drei Anfragen, bei 17 Modulen ist das Kontingent nach einer einzigen Site weg und keine Site sieht mehr ein Update.

Mit Token gelten 5.000 Anfragen je Stunde. Ein Fine-grained Token mit Zugriff „Public repositories (read-only)“ und ohne weitere Berechtigung reicht, die Repos sind öffentlich. Nie in die Datenbank, nie ins Repo. Ohne Token verhält sich die Prüfung wie bisher.

Vorgesehen ist die Umgebungsvariable `RH_GITHUB_TOKEN` im Container. Im Image `wordpress:latest` läuft PHP als Apache-Modul (`apache2handler`, kein PHP-FPM), `getenv()` sieht die Container-Umgebung dort direkt.

In Coolify einmal als Team-Variable anlegen (Shared Variables, Team) und pro Site einmalig verknüpfen. Coolify gibt eine Team-Variable nicht von selbst an die Container weiter, sie muss in der Compose-Datei stehen:

```yaml
services:
  wordpress:
    environment:
      RH_GITHUB_TOKEN: ${RH_GITHUB_TOKEN}
```

Danach unter Environment Variables der Site den Wert auf `{{team.RH_GITHUB_TOKEN}}` setzen und neu deployen. Ein Token-Tausch ist dann eine Änderung an der Team-Variable plus ein Redeploy je Site, weil der Container seine Umgebung nur beim Start liest.

Notnagel ohne Coolify, etwa auf einem Host ohne Container: die Konstante in der `wp-config.php`. Sie hat Vorrang vor der Umgebungsvariable.

```php
define('RH_GITHUB_TOKEN', 'github_pat_...');
```

Achtung: einen ungültigen oder abgelaufenen Token weist GitHub nicht ab, die Anfrage läuft dann still anonym mit dem 60er-Limit weiter. Es kommt kein Fehler, nur wieder die 403 aus dem Rate-Limit. Prüfen lässt sich ein Token ohne Kontingent-Verbrauch über `https://api.github.com/rate_limit`: mit gültigem Token steht dort `x-ratelimit-limit: 5000`, sonst `60`. Das Ablaufdatum des Tokens deshalb im Kalender führen und vorher tauschen.

## Versionierung

Die Negotiation wählt immer die höchste geladene Version. Darum gilt: **Die Core-API ist ein Vertrag, nur additive Änderungen.** Ein Breaking Change ist ein neuer Major. `version.php` und der Git-Tag müssen übereinstimmen.

## Test

```bash
php tests/negotiation-test.php
```

## Lizenz

GPL-2.0-or-later
