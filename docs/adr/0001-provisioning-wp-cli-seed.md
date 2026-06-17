# ADR 0001: Provisioning der Suite-Konfiguration über einen WP-CLI-Seed-Command

Status: angenommen (2026-06-17)

## Kontext

Robin betreibt die rh-blueprint Suite als White-Label-Partner für mehrere Kundensites. Jede neue Site braucht dieselben Module in derselben Grundkonfiguration (Stammdaten, Hardening-Schalter, SEO-Technik, Tracking-IDs). Das bisher manuell pro Site über die Settings-Seite zu klicken ist fehleranfällig und skaliert nicht.

Die Settings liegen pro Gruppe in einer Option `rhbp_settings_<group>`. Seit 2026-06-17 gibt es die Schreib-Helper `rhbp_update_setting()` / `rhbp_update_settings()`, die den Optionsnamen selbst auflösen und in die Gruppe mergen.

Zwei Wege standen zur Wahl:

1. **WP-CLI-Command** (`wp rh seed <config.json>`): explizit, wiederholbar, läuft wann Robin es auslöst.
2. **Deklaratives Boot-Seed** (Konstante/JSON, die der Core beim ersten Boot einmalig anwendet): vollautomatisch, braucht aber ein "schon geseedet"-Flag und arbeitet implizit beim Boot.

## Entscheidung

Provisioning läuft über einen **WP-CLI-Command** im Core: `wp rh seed <config.json>`.

- Liest eine JSON-Datei der Form `{ "<group_id>": { "<field>": <value> } }`.
- Schreibt jede Gruppe über `rhbp_update_settings()` (Merge, idempotent).
- `--dry-run` zeigt die geplanten Änderungen ohne zu schreiben.
- Registrierung nur unter `defined('WP_CLI') && WP_CLI`, kein Overhead im Web-Request.

## Begründung

- **Explizit statt Magie:** der Seed läuft wann Robin ihn auslöst, passt in seinen Deploy-Flow. Kein verstecktes Verhalten beim ersten Boot, das später schwer nachzuvollziehen ist.
- **Idempotent + wiederholbar:** derselbe Command kann nach Modul-Updates erneut laufen, ohne ein Flag prüfen zu müssen. Der Merge in `rhbp_update_settings()` lässt nicht-geseedete Felder unangetastet.
- **Kein Boot-Overhead:** ein deklaratives Boot-Seed müsste bei jedem Request den "schon geseedet"-Zustand prüfen. Der CLI-Command kostet im Web-Pfad nichts.
- **Single Source bleibt die Settings-Option:** der Command füttert nur die bestehenden Helper, keine zweite Konfigurations-Wahrheit.

## Konsequenzen

- Provisioning braucht WP-CLI-Zugang auf der Zielumgebung (bei Robins Hosting gegeben).
- Die JSON-Config wird pro Kundensite gepflegt (Vorlage im Repo, Werte pro Kunde).
- Der Command schreibt roh über die Helper, die Settings-API-Sanitize läuft dabei nicht. Für deklaratives Provisioning ist das akzeptiert (gleiche Semantik wie die Helper). Sensible Felder validiert das jeweilige Modul beim Lesen.
- Ein späteres Boot-Seed bleibt möglich, falls je eine Site ohne CLI provisioniert werden muss. Bis dahin nicht gebaut (YAGNI).
