<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Mail\Mail;
use RhBlueprint\Core\Mail\MailKind;
use RhBlueprint\Core\Mail\MailSettings;
use RhBlueprint\Core\Mail\ReportSection;
use RhBlueprint\Core\Settings\SettingsPage;

/**
 * Die Mail-Einstellungen eines Moduls, erreichbar über den Briefumschlag in
 * der Werkzeugleiste seines Tabs.
 *
 * Aufgebaut wird das aus der Registry (siehe MailKind): das Modul meldet an,
 * was es verschickt, die Oberfläche entsteht daraus. Ein Modul, das eine neue
 * Mail-Art dazubekommt, braucht dafür keine Zeile Oberfläche.
 *
 * Zwei Sorten Zeilen, sichtbar getrennt:
 *
 *   Geht sofort raus   ein Anlass, eine Mail. Schalter plus Einstellungen.
 *   Im Sammelbericht   was dieses Modul dem gemeinsamen Bericht beisteuert,
 *                      mit Vorschau des aktuellen Stands. Vorgabe: an.
 *
 * Die Vorschau ist der Grund, warum das hier sitzt und nicht im E-Mail-Modul:
 * "was hängt dieses Plugin eigentlich an den Bericht" beantwortet man dort, wo
 * man das Plugin gerade konfiguriert, und nicht drei Tabs weiter.
 */
final class MailPanel
{
    private const ACTION = 'rhbp_save_mail_kind';

    /** Kennung des Reiters. Module bauen ihn in ihre eigene Leiste ein. */
    public const TAB = 'mail';

    public function boot(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'save']);
    }

    /**
     * Beschriftung für die Reiterleiste des Moduls, oder null wenn dieses
     * Modul gar nichts verschickt.
     *
     * Die Leiste rendert das Modul selbst. Der Core hat es einmal andersherum
     * versucht und eine eigene darüber gesetzt: bei jedem Modul mit eigenen
     * Unterreitern ergab das drei Ebenen übereinander, zwei davon optisch kaum
     * unterscheidbar. Der Punkt gehört in die Leiste, die schon da ist.
     */
    public static function tabLabel(string $module): ?string
    {
        return MailKind::moduleHasMail($module)
            ? __('E-Mail', 'rh-blueprint-core')
            : null;
    }

    /** Adresse des Reiters für die Leiste des Moduls. */
    public static function url(string $tab, string $key = self::TAB): string
    {
        return add_query_arg(
            ['page' => SettingsPage::MENU_SLUG, 'tab' => $tab, 'sub' => $key],
            admin_url('admin.php')
        );
    }

    /**
     * Inhalt des Reiters. Das Modul ruft ihn auf, wenn sein Reiter aktiv ist.
     */
    public function render(string $tab): void
    {
        $kinds = MailKind::all($tab);

        if ($kinds === [] || ! current_user_can('manage_options')) {
            return;
        }

        $sofort = array_filter($kinds, static fn (MailKind $k): bool => ! $k->isReport());
        $bericht = array_filter($kinds, static fn (MailKind $k): bool => $k->isReport());

        echo '<section class="rhbp-card rhbp-mail-section">';

        echo '<p class="rhbp-mail-section__intro">'
            . esc_html__('Was dieses Modul verschickt und wie. Abgeschaltetes geht gar nicht erst raus.', 'rh-blueprint-core')
            . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        wp_nonce_field(self::ACTION);

        echo '<div class="rhbp-mail-section__body">';

        if ($sofort !== []) {
            echo '<p class="rhbp-mail-group">' . esc_html__('Geht sofort raus', 'rh-blueprint-core') . '</p>';
            foreach ($sofort as $kind) {
                $this->renderKind($kind);
            }
        }

        if ($bericht !== []) {
            echo '<p class="rhbp-mail-group">' . esc_html__('Im Sammelbericht', 'rh-blueprint-core') . '</p>';
            foreach ($bericht as $kind) {
                $this->renderKind($kind);
            }

            if (! Mail::hasReporting()) {
                echo '<div class="rhbp-callout rhbp-callout--warn">'
                    . esc_html__('Es verschickt gerade niemand Sammelberichte. Dieser Beitrag wird zwar erzeugt, landet aber nirgends. Dafür ist das E-Mail-Modul zuständig.', 'rh-blueprint-core')
                    . '</div>';
            }
        }

        echo '</div>';

        echo '<div class="rhbp-mail-section__foot">';
        echo '<button type="submit" class="rhbp-btn rhbp-btn--primary">' . esc_html__('E-Mail-Einstellungen speichern', 'rh-blueprint-core') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</section>';
    }

    private function renderKind(MailKind $kind): void
    {
        $feld = 'kinds[' . $kind->id . ']';
        $an = MailSettings::enabled($kind->id);

        echo '<div class="rhbp-mail-row">';

        // Text links, Schalter rechts: dieselbe Leserichtung wie in den Reihen
        // von Tracking, Monitoring und Sync. Der Core stand hier als einziger
        // andersherum.
        echo '<div class="rhbp-mail-row__head">';

        echo '<div class="rhbp-mail-row__text">';
        echo '<strong>' . esc_html($kind->label) . '</strong>';

        if ($kind->urgent) {
            echo ' <span class="rhbp-pill rhbp-pill--warn">' . esc_html__('dringend', 'rh-blueprint-core') . '</span>';
        }

        if ($kind->summary !== '') {
            echo '<div class="rhbp-mail-row__sub">' . esc_html($kind->summary) . '</div>';
        }

        echo '</div>';

        // Eine Pflichtmail zeigt statt eines toten Schalters, dass sie immer
        // rausgeht. Ein gesperrter Schalter sieht aus wie ein Fehler.
        if ($kind->lockable) {
            echo Ui::switch(['name' => $feld . '[enabled]', 'checked' => $an]);
        } else {
            echo '<span class="rhbp-pill rhbp-pill--ok">' . esc_html__('immer aktiv', 'rh-blueprint-core') . '</span>';
        }

        echo '</div>';

        // Ein Berichtsbeitrag hat keinen eigenen Empfänger und keinen eigenen
        // Betreff: er ist ein Abschnitt in einer fremden Mail. Statt leerer
        // Felder steht dort, was er gerade beisteuern würde.
        if ($kind->isReport()) {
            $this->renderPreview($kind);
            echo '</div>';

            return;
        }

        // Eingeklappt: bei acht Mails ist eine Seite mit zweiunddreissig
        // offenen Feldern nicht mehr zu überblicken. Wer etwas ändern will,
        // klappt genau die eine Mail auf.
        echo '<details class="rhbp-mail-row__edit">';
        echo '<summary>' . esc_html__('Empfänger, Betreff und Text bearbeiten', 'rh-blueprint-core') . '</summary>';

        echo '<div class="rhbp-mail-row__fields">';

        $this->field(
            $feld . '[recipient]',
            __('Empfänger', 'rh-blueprint-core'),
            MailSettings::recipient($kind->id),
            __('leer: wie im Modul eingestellt', 'rh-blueprint-core'),
            'email'
        );

        $this->field(
            $feld . '[from_name]',
            __('Absendername', 'rh-blueprint-core'),
            MailSettings::fromName($kind->id),
            __('leer: wie beim Mailversand eingestellt', 'rh-blueprint-core')
        );

        $this->field(
            $feld . '[subject]',
            __('Betreff', 'rh-blueprint-core'),
            MailSettings::subject($kind->id),
            __('leer: Vorgabe des Moduls. Die Domain kommt davor.', 'rh-blueprint-core'),
            'text',
            true,
            $kind->subject
        );

        echo '<label class="rhbp-field rhbp-field--full">';
        echo '<span class="rhbp-field__label">' . esc_html__('Zusatztext am Ende', 'rh-blueprint-core') . '</span>';
        printf(
            '<textarea name="%s[note]" rows="2" placeholder="%s">%s</textarea>',
            esc_attr($feld),
            esc_attr__('etwa ein Hinweis, an wen man sich wenden soll', 'rh-blueprint-core'),
            esc_textarea(MailSettings::note($kind->id))
        );
        echo '</label>';

        // Ein Betreff-Feld ohne die Liste der Platzhalter ist eine Einladung
        // zum Raten. Wer {bestellnummer} nicht kennt, schreibt sie auch nicht.
        if ($kind->placeholders !== []) {
            echo '<p class="rhbp-field__desc rhbp-mail-row__vars">';
            echo esc_html__('Platzhalter:', 'rh-blueprint-core') . ' ';

            foreach ($kind->placeholders as $name) {
                echo '<code>{' . esc_html($name) . '}</code> ';
            }

            echo '</p>';
        }

        echo '</div>';
        echo '</details>';
        echo '</div>';
    }

    /**
     * Zeigt, was dieses Modul dem Bericht gerade anhängen würde. Fragt dafür
     * denselben Filter ab, den auch der Bericht benutzt, damit hier nichts
     * anderes steht als später in der Mail.
     */
    private function renderPreview(MailKind $kind): void
    {
        // Einmal je Seitenaufruf, nicht einmal je Beitrag. Der Filter lässt
        // jedes Modul seine Zahlen zusammensuchen und kostet gemessen vier
        // Abfragen und sechs Millisekunden. Solange nur ein Modul einen
        // Berichtsbeitrag hat, fällt das nicht auf. Beim zweiten wäre es die
        // doppelte Arbeit für dieselbe Antwort, und niemand käme auf die Idee,
        // hier zu suchen.
        static $sections = null;

        if ($sections === null) {
            $gefiltert = apply_filters('rh-blueprint/report/sections', [], time() - WEEK_IN_SECONDS);
            $sections = is_array($gefiltert) ? $gefiltert : [];
        }

        $eigene = null;

        foreach ($sections as $section) {
            if ($section instanceof ReportSection && $section->module === $kind->module) {
                $eigene = $section;
                break;
            }
        }

        echo '<div class="rhbp-mail-preview">';
        echo '<span class="rhbp-mail-preview__label">' . esc_html__('Steuert gerade bei', 'rh-blueprint-core') . '</span>';

        if ($eigene === null) {
            echo '<span class="rhbp-mail-preview__empty">' . esc_html__('im Moment nichts', 'rh-blueprint-core') . '</span>';
            echo '</div>';

            return;
        }

        $ton = match ($eigene->status) {
            ReportSection::STATUS_ALERT => 'rhbp-pill--err',
            ReportSection::STATUS_WARN => 'rhbp-pill--warn',
            ReportSection::STATUS_OK => 'rhbp-pill--ok',
            default => '',
        };

        printf(
            '<span class="rhbp-pill %s">%s</span> <span class="rhbp-mail-preview__text">%s</span>',
            esc_attr($ton),
            esc_html($eigene->label),
            esc_html($eigene->summary)
        );

        echo '</div>';
    }

    /**
     * @param string $vorgabe Steht als Platzhaltertext im leeren Feld. Damit
     *                        sieht man, was ohne eigene Eingabe rausgeht,
     *                        statt nur zu lesen, dass es eine Vorgabe gibt.
     */
    private function field(
        string $name,
        string $label,
        string $value,
        string $hint,
        string $type = 'text',
        bool $full = false,
        string $vorgabe = ''
    ): void {
        echo '<label class="rhbp-field' . ($full ? ' rhbp-field--full' : '') . '">';
        echo '<span class="rhbp-field__label">' . esc_html($label) . '</span>';
        printf(
            '<input type="%s" name="%s" value="%s" placeholder="%s">',
            esc_attr($type),
            esc_attr($name),
            esc_attr($value),
            esc_attr($vorgabe !== '' ? $vorgabe : $hint)
        );

        if ($vorgabe !== '') {
            echo '<span class="rhbp-field__desc">' . esc_html($hint) . '</span>';
        }

        echo '</label>';
    }

    public function save(): void
    {
        check_admin_referer(self::ACTION);

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Fehlende Berechtigung.', 'rh-blueprint-core'));
        }

        $tab = isset($_POST['tab']) ? sanitize_key((string) wp_unslash($_POST['tab'])) : '';

        // Nicht über das Gesendete laufen, sondern über die angemeldeten Arten
        // dieses Tabs: ein abgeschalteter Schalter schickt gar nichts mit, und
        // fremde Kennungen im Formular werden so gar nicht erst betrachtet.
        foreach (MailKind::all($tab) as $kind) {
            $roh = isset($_POST['kinds'][ $kind->id ]) && is_array($_POST['kinds'][ $kind->id ])
                ? wp_unslash($_POST['kinds'][ $kind->id ])
                : [];

            MailSettings::save($kind->id, [
                'enabled' => ! empty($roh['enabled']),
                'recipient' => (string) ($roh['recipient'] ?? ''),
                'subject' => (string) ($roh['subject'] ?? ''),
                'from_name' => (string) ($roh['from_name'] ?? ''),
                'note' => (string) ($roh['note'] ?? ''),
            ]);
        }

        wp_safe_redirect(add_query_arg(
            ['page' => SettingsPage::MENU_SLUG, 'tab' => $tab, 'sub' => self::TAB, 'rhbp-mail' => 'saved'],
            admin_url('admin.php')
        ));
        exit;
    }
}
