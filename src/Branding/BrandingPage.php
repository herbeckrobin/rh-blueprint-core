<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Branding;

use RhBlueprint\Core\Admin\Guard;

/**
 * Einstellungsseite fürs Branding, ohne Menüeintrag.
 *
 * Erreichbar nur über die direkte URL `admin.php?page=rhbp-identity`, Recht
 * manage_options. Sie wird nirgends verlinkt, damit ein Endkunden-Admin keinen
 * Hinweis auf das White-Label bekommt (Robin, 06.10.2026).
 */
final class BrandingPage
{
    public const SLUG = 'rhbp-identity';
    private const ACTION = 'rhbp_save_identity';
    private const CAPABILITY = 'manage_options';

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'register']);
        add_action('admin_post_' . self::ACTION, [$this, 'save']);
    }

    public function register(): void
    {
        // Leerer Parent: registriert die Seite, ohne sie in ein Menü zu hängen.
        $hook = add_submenu_page('', '', '', self::CAPABILITY, self::SLUG, [$this, 'render']);

        // Ohne Menüeintrag bliebe der Fenstertitel leer.
        if (is_string($hook)) {
            add_action('load-' . $hook, static function (): void {
                $GLOBALS['title'] = __('Darstellung', 'rh-blueprint-core');
            });
        }
    }

    public function save(): void
    {
        Guard::form(self::ACTION, self::CAPABILITY);

        $input = wp_unslash($_POST);
        $field = static fn(string $key): string => isset($input[$key]) ? (string) $input[$key] : '';

        Branding::save([
            'name' => $field('name'),
            'icon' => $field('icon'),
            'subtitle' => $field('subtitle'),
            'show_version' => $field('show_version') === '1',
            'author' => $field('author'),
            'author_uri' => $field('author_uri'),
            'description_suffix' => $field('description_suffix'),
        ]);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $stored = get_option(Branding::OPTION, []);
        $b = Branding::normalize(array_merge(Branding::defaults(), is_array($stored) ? $stored : []));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Darstellung', 'rh-blueprint-core') . '</h1>';

        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Gespeichert.', 'rh-blueprint-core') . '</p></div>';
        }

        echo '<p>' . esc_html__('Ist ein Name gesetzt, erscheinen Menü, Seitenkopf, Dashboard-Widget und Plugin-Liste unter diesem Namen. Leer lassen heißt Standard.', 'rh-blueprint-core') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        wp_nonce_field(self::ACTION);

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->textRow('name', __('Name', 'rh-blueprint-core'), $b['name'], __('Ersetzt "RH Blueprint" und das Präfix "RH" der Module.', 'rh-blueprint-core'));
        $this->textRow('subtitle', __('Untertitel', 'rh-blueprint-core'), $b['subtitle'], __('Zeile unter dem Namen im Seitenkopf.', 'rh-blueprint-core'));
        $this->textRow('author', __('Autor', 'rh-blueprint-core'), $b['author'], __('Leer: der Name.', 'rh-blueprint-core'));
        $this->textRow('author_uri', __('Autor-URL', 'rh-blueprint-core'), $b['author_uri'], '', 'url');
        $this->textRow('description_suffix', __('Beschreibungs-Zusatz', 'rh-blueprint-core'), $b['description_suffix'], __('Ersetzt "Teil der rh-blueprint Kollektion." in der Plugin-Liste. Leer: entfällt.', 'rh-blueprint-core'));

        echo '<tr><th scope="row"><label for="rhbp-icon">' . esc_html__('Icon', 'rh-blueprint-core') . '</label></th><td>';
        echo '<textarea id="rhbp-icon" name="icon" rows="4" class="large-text code">' . esc_textarea($b['icon']) . '</textarea>';
        echo '<p class="description">' . esc_html__('SVG-Markup oder ein Dashicon-Name wie dashicons-art.', 'rh-blueprint-core') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Version', 'rh-blueprint-core') . '</th><td><label>';
        echo '<input type="checkbox" name="show_version" value="1"' . checked($b['show_version'], true, false) . '> ';
        echo esc_html__('Versionsnummer im Seitenkopf zeigen', 'rh-blueprint-core') . '</label></td></tr>';
        echo '</tbody></table>';

        submit_button(__('Speichern', 'rh-blueprint-core'));
        echo '</form></div>';
    }

    private function textRow(string $key, string $label, string $value, string $help = '', string $type = 'text'): void
    {
        printf(
            '<tr><th scope="row"><label for="rhbp-%1$s">%2$s</label></th><td><input type="%3$s" id="rhbp-%1$s" name="%1$s" value="%4$s" class="regular-text">%5$s</td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($type),
            esc_attr($value),
            $help !== '' ? '<p class="description">' . esc_html($help) . '</p>' : ''
        );
    }
}
