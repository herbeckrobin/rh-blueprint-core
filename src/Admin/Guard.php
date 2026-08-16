<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Rechte- und Nonce-Prüfung für Aktionen der Einstellungsseite.
 *
 * Sechs Module prüfen dasselbe, auf sechs Arten, und zwei der Unterschiede
 * sind keine Geschmacksfrage:
 *
 *   Die Reihenfolge. Die Hälfte prüft zuerst den Nonce, die andere Hälfte
 *   zuerst die Rechte. Erst der Nonce ist die schlechtere Wahl: bei einem
 *   abgelaufenen Nonce zeigt `check_admin_referer` einen Bestätigungsdialog
 *   mit einem Weiter-Knopf, und den bekommt dann auch jemand zu sehen, der die
 *   Aktion gar nicht ausführen darf. Der Klick scheitert danach zwar, aber die
 *   Aktion war für ihn sichtbar. Rechte zuerst.
 *
 *   Der Antwortcode. Zwei Module rufen `wp_die()` ohne Code auf, das ergibt
 *   eine 500. Eine verweigerte Aktion ist aber kein Serverfehler, sondern eine
 *   403. Monitoring, das auf 5xx schaut, sieht sonst einen Ausfall, wo nur
 *   jemandem die Rechte fehlen.
 *
 * Die Prüfung ist zweigeteilt, weil ein Formular und ein AJAX-Aufruf
 * verschieden antworten müssen: das eine mit einer Seite, das andere mit JSON.
 */
final class Guard
{
    /**
     * Für Formulare, die auf admin-post.php zeigen.
     *
     * Bricht ab, wenn etwas nicht stimmt, und kehrt sonst zurück.
     */
    public static function form(string $action, string $capability = '', string $nonceField = '_wpnonce'): void
    {
        if (! current_user_can($capability !== '' ? $capability : SettingsPage::CAPABILITY)) {
            wp_die(esc_html__('Dazu fehlen die Rechte.', 'rh-blueprint-core'), '', ['response' => 403]);
        }

        check_admin_referer($action, $nonceField);
    }

    /**
     * Für AJAX-Aufrufe. Antwortet mit JSON statt mit einer Seite.
     */
    public static function ajax(string $action, string $capability = '', string $nonceField = 'nonce'): void
    {
        if (! current_user_can($capability !== '' ? $capability : SettingsPage::CAPABILITY)) {
            wp_send_json_error(['message' => __('Dazu fehlen die Rechte.', 'rh-blueprint-core')], 403);
        }

        check_ajax_referer($action, $nonceField);
    }

    /**
     * Zurück auf die Einstellungsseite, mit einer Meldung im Gepäck.
     *
     * @param array<string, string> $args Zusätzliche Parameter, etwa der Unterreiter.
     */
    public static function redirect(string $tab, array $args = []): never
    {
        wp_safe_redirect(add_query_arg(
            array_merge(['page' => SettingsPage::MENU_SLUG, 'tab' => $tab], $args),
            admin_url('admin.php')
        ));

        exit;
    }
}
