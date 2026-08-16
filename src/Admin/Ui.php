<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

/**
 * Markup für die Bausteine der Einstellungsseite.
 *
 * Der Core brachte bisher das komplette CSS mit, aber nichts, was das passende
 * Markup erzeugt. Also hat jedes Modul die Hüllen für Symbol, Schalter, Reiter
 * und Dialog abgeschrieben, und sie sind auseinandergelaufen:
 *
 *   Der Papierkorb ist in zwei Modulen ein Pfad und in einem dritten ein
 *   Polygon plus Pfad. Derselbe Knopf, zwei Formen.
 *
 *   Vier von dreizehn Schaltern haben kein aria-hidden auf der Spur, ein
 *   Screenreader liest dort einen leeren Knopf vor.
 *
 *   Ein Dialog in rh-hardening hat weder role noch aria-modal. Für einen
 *   Screenreader ist er damit kein Dialog, sondern ein Stück Seite, das
 *   plötzlich da ist.
 *
 * Wer eine dieser Hüllen von Hand schreibt, hat gute Chancen, eine dieser
 * Kleinigkeiten zu vergessen. Deshalb hier, einmal richtig.
 */
final class Ui
{
    /**
     * Der gemeinsame Symbolsatz.
     *
     * Bewusst als Pfade und nicht als Dateien: ein Symbol im Markup erbt die
     * Textfarbe und braucht keinen zweiten Aufruf.
     *
     * @return array<string, string>
     */
    private static function paths(): array
    {
        return [
            'mail' => '<path d="M4 7v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7M4 7l8 6 8-6M4 7h16"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            // Diese drei waren der Anlass. Papierkorb lag in drei Modulen in
            // zwei Formen vor, Kopieren und Neu laden in je zwei. Gewählt ist
            // jeweils die Feather-Vorlage, weil sie sauber im 24er-Raster
            // sitzt und die übrigen Symbole daher stammen.
            'trash' => '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>',
            'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'refresh' => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
            'inbox' => '<path d="M4 7v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7M4 7l8 6 8-6"/>',
            'external' => '<path d="M14 4h6v6M20 4l-9 9M19 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
            'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'site' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            'pull' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
            'push' => '<path d="M12 21V9M7 14l5-5 5 5M5 3h14"/>',
            'report' => '<path d="M4 4h12l4 4v12H4z"/><path d="M8 12h8M8 16h5"/>',
            'warn' => '<path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        ];
    }

    /**
     * Ein Symbol. Unbekannte Namen geben das Zahnrad, damit eine Stelle mit
     * Tippfehler sichtbar bleibt statt leer.
     *
     * @param string $size       Grössenstufe, zurzeit nur 'sm'.
     * @param string $extraClass Zusätzliche Klasse, für Module die eine brauchen.
     */
    public static function icon(string $name, string $size = '', string $extraClass = ''): string
    {
        $path = self::paths()[$name] ?? self::paths()['gear'];

        // Klasse und Strichstärke wie im Bestand: das CSS kennt `rhbp-ico`,
        // nicht `rhbp-icon`, und alle vorhandenen Symbole zeichnen mit 2.
        // Ein Baustein, der die eine Stelle nicht trifft, hat keine Grösse
        // und die andere sähe daneben dünner aus.
        $class = 'rhbp-ico'
            . ($size !== '' ? ' rhbp-ico--' . $size : '')
            . ($extraClass !== '' ? ' ' . $extraClass : '');

        return '<svg class="' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $path
            . '</svg>';
    }

    /**
     * Gibt es dieses Symbol? Für Tests und für Module, die eigene Namen prüfen.
     */
    public static function hasIcon(string $name): bool
    {
        return isset(self::paths()[$name]);
    }

    /**
     * Ein Schalter.
     *
     * Als Optionsfeld und nicht als Parameterliste, weil die vierzehn
     * bestehenden Stellen sich in sechs Merkmalen unterscheiden (Feldname,
     * Beschriftung beim Überfahren, Zusatzklasse, Text im Label, gesperrt,
     * eigene data-Attribute). Eine Liste aus sechs Stellen wäre an der
     * Aufrufstelle nicht mehr lesbar.
     *
     * Schlüssel:
     *   name     Feldname. Leer lassen, wenn der Zustand über data-Attribute
     *            und JavaScript läuft statt über ein Formular.
     *   checked  Zustand.
     *   title    Beschriftung beim Überfahren, sitzt am Label.
     *   class    Zusätzliche Klasse am Label.
     *   label    Sichtbarer Text hinter dem Schalter.
     *   disabled Schalter gesperrt.
     *   input    Weitere Attribute am Eingabefeld. Wert `true` gibt nur den
     *            Namen aus, für Attribute ohne Wert.
     *
     * @param array<string, mixed> $opt
     */
    public static function switch(array $opt = []): string
    {
        $name = (string) ($opt['name'] ?? '');
        $title = (string) ($opt['title'] ?? '');
        $class = (string) ($opt['class'] ?? '');
        $label = (string) ($opt['label'] ?? '');

        $attrs = '';

        if ($name !== '') {
            $attrs .= sprintf(' name="%s" value="1"', esc_attr($name));
        }

        if (! empty($opt['checked'])) {
            $attrs .= ' checked';
        }

        if (! empty($opt['disabled'])) {
            $attrs .= ' disabled';
        }

        /** @var array<string, mixed> $weitere */
        $weitere = is_array($opt['input'] ?? null) ? $opt['input'] : [];

        foreach ($weitere as $key => $value) {
            $attrs .= $value === true
                ? ' ' . esc_attr((string) $key)
                : sprintf(' %s="%s"', esc_attr((string) $key), esc_attr((string) $value));
        }

        $html = sprintf(
            '<label class="rhbp-switch%s"%s>',
            $class !== '' ? ' ' . esc_attr($class) : '',
            $title !== '' ? sprintf(' title="%s"', esc_attr($title)) : ''
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
        $html .= '<input type="checkbox"' . $attrs . '>';

        // Ohne aria-hidden liest ein Screenreader hier einen leeren Knopf vor.
        // Genau das fehlte an vier der bisherigen Stellen.
        $html .= '<span class="rhbp-switch__track" aria-hidden="true"></span>';

        if ($label !== '') {
            $html .= '<span class="rhbp-switch__label">' . esc_html($label) . '</span>';
        }

        return $html . '</label>';
    }

    /**
     * Eine Reiterleiste innerhalb eines Tabs.
     *
     * Zwei Bauarten, und die Wahl hat Folgen. Mit Adressen sind die Reiter
     * verlinkbar und überstehen ein Neuladen, kosten aber je einen Seitenaufbau.
     * Ohne Adressen schaltet das Core-JS um, ohne die Seite neu zu laden.
     *
     * Wer Adressen übergibt, bekommt Verweise, sonst Knöpfe. Das
     * `data-rhbp-subtabs` am Rahmen ist die Marke, an der das JS eine
     * seitenweite Leiste erkennt.
     *
     * Ein Reiter kann ein Abzeichen tragen, etwa die Zahl offener Meldungen.
     * Bewusst als Zahl und nicht als freies Markup: sonst wandert HTML durch
     * eine Beschriftung, die sonst escapt wird, und der Baustein wird zur
     * Lücke statt zum Schutz.
     *
     * Wer eine Kennung in `$badges` nennt, bekommt das Abzeichen immer ins
     * Markup, bei null nur ausgeblendet. Sonst hätte ein Modul, das die Zahl
     * per JavaScript nachträgt, nichts zum Anfassen, solange sie null ist.
     * Ansprechbar über `[data-rhbp-subtab-badge="KENNUNG"]`.
     *
     * @param array<string, string>     $tabs      Kennung => Beschriftung.
     * @param array<string, string>     $urls      Kennung => Adresse. Leer für JS-Umschaltung.
     * @param array<string, int|string> $badges    Kennung => Zahl oder kurzes Zeichen.
     * @param array<string, string>     $badgeTone Kennung => warn, err oder ok. Vorgabe warn.
     */
    public static function subtabs(
        array $tabs,
        string $active,
        array $urls = [],
        array $badges = [],
        array $badgeTone = []
    ): string {
        if ($tabs === []) {
            return '';
        }

        $html = '<div class="rhbp-subtabs" data-rhbp-subtabs>';

        foreach ($tabs as $key => $label) {
            $aktiv = $key === $active ? ' is-active' : '';
            $inhalt = esc_html($label);

            if (array_key_exists($key, $badges)) {
                // Meist eine Zahl, manchmal ein Zeichen wie "!". Beides wird
                // escapt, hier kommt kein Markup durch.
                $wert = (string) $badges[$key];
                $leer = $wert === '' || $wert === '0';
                $ton = (string) ($badgeTone[$key] ?? 'warn');

                $inhalt .= sprintf(
                    ' <span class="rhbp-pill rhbp-pill--%s" data-rhbp-subtab-badge="%s"%s>%s</span>',
                    esc_attr($ton),
                    esc_attr($key),
                    $leer ? ' hidden' : '',
                    esc_html($wert)
                );
            }

            if (isset($urls[$key])) {
                $html .= sprintf(
                    '<a class="rhbp-subtab%s" href="%s"%s>%s</a>',
                    $aktiv,
                    esc_url($urls[$key]),
                    $aktiv !== '' ? ' aria-current="page"' : '',
                    $inhalt // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
                );

                continue;
            }

            $html .= sprintf(
                '<button type="button" class="rhbp-subtab%s" data-rhbp-subtab="%s"%s>%s</button>',
                $aktiv,
                esc_attr($key),
                $aktiv !== '' ? ' aria-current="true"' : '',
                $inhalt // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
            );
        }

        return $html . '</div>';
    }

    /**
     * Ein Bereich, der zu einem Reiter der JS-Variante gehört.
     */
    public static function paneOpen(string $key, bool $active): string
    {
        return sprintf(
            '<div class="rhbp-tabpane%s" data-rhbp-pane="%s">',
            $active ? ' is-active' : '',
            esc_attr($key)
        );
    }

    /**
     * Kopf eines Dialogs samt Rahmen.
     *
     * Enthält alles, was leicht vergessen wird: die Rolle, aria-modal, einen
     * Namen und den Knopf zum Zumachen samt Beschriftung. Genau daran fehlte
     * es an acht der siebzehn bestehenden Stellen.
     *
     * Als Optionsfeld, weil die Hüllen sich in sechs Merkmalen unterscheiden.
     * Die vorherige Fassung hatte sieben Positionsparameter und passte trotzdem
     * nur auf gut die Hälfte:
     *
     *   id            Kennung der Hülle, Ziel von data-rhbp-modal-open.
     *   title         Überschrift und, wenn kein titleId gesetzt ist, der Name.
     *   subtitle      Zeile unter der Überschrift.
     *   icon          Symbolname. Leer lässt den Platz weg.
     *   iconMarkup    Fertiges Markup statt eines Symbols, etwa ein Logo. Wird
     *                 roh ausgegeben, also nur mit selbst gebautem Inhalt.
     *   tone          Färbt das Symbol: ok, err.
     *   open          Von Anfang an offen.
     *   class         Zusätzliche Klasse am Dialog.
     *   backdropClass Zusätzliche Klasse an der Hülle.
     *   backdropAttrs Weitere Attribute an der Hülle, für modul-eigenes JS.
     *   titleTag      h2 oder h3, Vorgabe h3.
     *   titleId       Setzt aria-labelledby statt aria-label.
     *   titleAttrs    Weitere Attribute an der Überschrift, als Anker für JS.
     *   form          Adresse für ein Formular, das den ganzen Dialog umschliesst.
     *                 Ohne das läge ein Knopf im Fuss ausserhalb des Formulars.
     *
     * Das `data-rhbp-modal-backdrop` wertet zurzeit niemand aus, das Core-JS
     * erkennt die Hülle an ihrer Klasse. Es steht an zwölf der siebzehn
     * bestehenden Stellen und bleibt deshalb hier drin: eine Marke, die
     * mancherorts fehlt, verwirrt mehr als eine, die überall steht.
     *
     * @param array<string, mixed> $opt
     */
    public static function modalOpen(array $opt): string
    {
        $id = (string) ($opt['id'] ?? '');
        $title = (string) ($opt['title'] ?? '');
        $subtitle = (string) ($opt['subtitle'] ?? '');
        $icon = array_key_exists('icon', $opt) ? (string) $opt['icon'] : 'gear';
        $iconMarkup = (string) ($opt['iconMarkup'] ?? '');
        $tone = (string) ($opt['tone'] ?? '');
        $klasse = (string) ($opt['class'] ?? '');
        $huelleKlasse = (string) ($opt['backdropClass'] ?? '');
        $titleTag = ($opt['titleTag'] ?? 'h3') === 'h2' ? 'h2' : 'h3';
        $titleId = (string) ($opt['titleId'] ?? '');
        $titleAttrs = (string) ($opt['titleAttrs'] ?? '');
        $form = (string) ($opt['form'] ?? '');

        $huelleAttrs = '';

        /** @var array<string, mixed> $weitere */
        $weitere = is_array($opt['backdropAttrs'] ?? null) ? $opt['backdropAttrs'] : [];

        foreach ($weitere as $key => $value) {
            $huelleAttrs .= $value === true
                ? ' ' . esc_attr((string) $key)
                : sprintf(' %s="%s"', esc_attr((string) $key), esc_attr((string) $value));
        }

        // Kein hidden-Attribut: das CSS versteckt die Hülle über display:none
        // und zeigt sie über die Klasse is-open. Ein zweiter Mechanismus
        // daneben wäre eine Stelle mehr, an der die beiden auseinanderlaufen.
        $html = sprintf(
            '<div class="rhbp-modal-backdrop%s%s"%s data-rhbp-modal-backdrop%s>',
            $huelleKlasse !== '' ? ' ' . esc_attr($huelleKlasse) : '',
            ! empty($opt['open']) ? ' is-open' : '',
            $id !== '' ? ' id="' . esc_attr($id) . '"' : '',
            $huelleAttrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
        );

        $html .= sprintf(
            '<div class="rhbp-modal%s" role="dialog" aria-modal="true" %s="%s">',
            $klasse !== '' ? ' ' . esc_attr($klasse) : '',
            $titleId !== '' ? 'aria-labelledby' : 'aria-label',
            esc_attr($titleId !== '' ? $titleId : $title)
        );

        if ($form !== '') {
            $html .= '<form method="post" action="' . esc_url($form) . '">';
        }

        $html .= '<div class="rhbp-modal__head"><div class="rhbp-modal__head-l">';

        if ($iconMarkup !== '') {
            $html .= $iconMarkup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup des Moduls.
        } elseif ($icon !== '') {
            $iconClass = 'rhbp-modal__head-icon' . ($tone !== '' ? ' rhbp-modal__head-icon--' . $tone : '');
            $html .= '<span class="' . esc_attr($iconClass) . '">' . self::icon($icon) . '</span>';
        }

        $html .= '<div>';
        $html .= sprintf(
            '<%1$s class="rhbp-modal__title"%2$s%3$s>%4$s</%1$s>',
            $titleTag,
            $titleId !== '' ? ' id="' . esc_attr($titleId) . '"' : '',
            $titleAttrs !== '' ? ' ' . $titleAttrs : '',
            esc_html($title)
        );

        if ($subtitle !== '') {
            $html .= '<p class="rhbp-modal__sub">' . esc_html($subtitle) . '</p>';
        }

        $html .= '</div></div>';
        $html .= sprintf(
            '<button type="button" class="rhbp-btn rhbp-btn--ghost rhbp-btn--icon" data-rhbp-modal-close aria-label="%s">%s</button>',
            esc_attr__('Zumachen', 'rh-blueprint-core'),
            self::icon('close')
        );
        $html .= '</div>';

        return $html . '<div class="rhbp-modal__body">';
    }

    /**
     * Schliesst den Rumpf und setzt die Fusszeile.
     *
     *   primary       Beschriftung des bestätigenden Knopfes. Leer: nur zumachen.
     *   primaryAttrs  Weitere Attribute an diesem Knopf.
     *   cancel        Beschriftung des abbrechenden Knopfes.
     *   foot          false lässt die Fusszeile ganz weg. Für Dialoge, deren
     *                 Karten je einen eigenen Knopf mitbringen.
     *   form          Muss gesetzt sein, wenn modalOpen ein Formular geöffnet hat.
     *   extra         Markup zwischen Ausgang und Bestätigung, roh.
     *
     * @param array<string, mixed> $opt
     */
    public static function modalClose(array $opt = []): string
    {
        $primary = (string) ($opt['primary'] ?? '');
        $primaryAttrs = (string) ($opt['primaryAttrs'] ?? '');
        $extra = (string) ($opt['extra'] ?? '');

        if (array_key_exists('foot', $opt) && $opt['foot'] === false) {
            return '</div>' . (! empty($opt['form']) ? '</form>' : '') . '</div></div>';
        }

        $html = '</div><div class="rhbp-modal__foot">';

        $abbruch = (string) ($opt['cancel'] ?? '');

        if ($abbruch === '') {
            $abbruch = $primary === ''
                ? __('Fertig', 'rh-blueprint-core')
                : __('Abbrechen', 'rh-blueprint-core');
        }

        $html .= sprintf(
            '<button type="button" class="rhbp-btn" data-rhbp-modal-close>%s</button>',
            esc_html($abbruch)
        );

        // Zwischen Ausgang und Bestaetigung: weitere Knoepfe des Moduls.
        if ($extra !== '') {
            $html .= $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup des Moduls.
        }

        if ($primary !== '') {
            // Nicht jeder Dialog schickt ein Formular ab, manche speichern
            // über einen eigenen Aufruf. Dann darf der Knopf kein submit sein.
            $typ = ($opt['primaryType'] ?? 'submit') === 'button' ? 'button' : 'submit';

            $html .= sprintf(
                '<button type="%s" class="rhbp-btn rhbp-btn--primary"%s>%s</button>',
                $typ,
                $primaryAttrs !== '' ? ' ' . $primaryAttrs : '',
                esc_html($primary)
            );
        }

        $html .= '</div>';

        if (! empty($opt['form'])) {
            $html .= '</form>';
        }

        return $html . '</div></div>';
    }
}
