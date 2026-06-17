<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Settings;

/**
 * Immutable Value-Object für ein einzelnes Settings-Feld.
 * Kapselt Typ, Default, Sanitization, Rendering und den Such-Index für die Live-Suche.
 */
final class SettingField
{
    public const TYPE_TEXT = 'text';
    public const TYPE_EMAIL = 'email';
    public const TYPE_URL = 'url';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_MEDIA = 'media';

    /**
     * @param array<string, string> $choices
     * @param array<int, string>    $keywords
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $label,
        public readonly string $description = '',
        public readonly string|int|bool $default = '',
        public readonly array $choices = [],
        public readonly array $keywords = [],
    ) {
    }

    public function searchIndex(): string
    {
        $parts = array_merge([$this->label, $this->description], $this->keywords);

        return strtolower(implode(' ', array_filter($parts)));
    }

    public function sanitize(mixed $value): mixed
    {
        return match ($this->type) {
            self::TYPE_EMAIL => sanitize_email((string) $value),
            self::TYPE_URL => esc_url_raw((string) $value),
            self::TYPE_BOOLEAN => (bool) $value,
            self::TYPE_SELECT => array_key_exists((string) $value, $this->choices) ? (string) $value : (string) $this->default,
            self::TYPE_TEXTAREA => sanitize_textarea_field((string) $value),
            self::TYPE_MEDIA => $this->sanitizeMedia($value),
            default => sanitize_text_field((string) $value),
        };
    }

    /**
     * Ein Media-Feld speichert eine Attachment-ID (portabel, die URL wird beim
     * Rendern aufgelöst). Eine bereits gespeicherte Legacy-URL bleibt erhalten,
     * damit die Migration von früheren URL-Feldern nichts verschluckt.
     */
    private function sanitizeMedia(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }
        if (ctype_digit($value)) {
            return (string) absint($value);
        }

        return esc_url_raw($value);
    }

    public function render(string $name, mixed $value): void
    {
        $id = esc_attr($name);
        $current = $value ?? $this->default;

        switch ($this->type) {
            case self::TYPE_TEXTAREA:
                printf(
                    '<textarea id="%1$s" name="%1$s" rows="5" class="large-text code">%2$s</textarea>',
                    $id,
                    esc_textarea((string) $current)
                );
                break;

            case self::TYPE_BOOLEAN:
                printf(
                    '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label>',
                    $id,
                    checked((bool) $current, true, false),
                    esc_html__('Aktivieren', 'rh-blueprint-core')
                );
                break;

            case self::TYPE_SELECT:
                printf('<select id="%1$s" name="%1$s">', $id);
                foreach ($this->choices as $choiceValue => $choiceLabel) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr($choiceValue),
                        selected((string) $current, (string) $choiceValue, false),
                        esc_html($choiceLabel)
                    );
                }
                echo '</select>';
                break;

            case self::TYPE_MEDIA:
                $this->renderMedia($id, $current);
                break;

            case self::TYPE_EMAIL:
            case self::TYPE_URL:
            case self::TYPE_TEXT:
            default:
                printf(
                    '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text" />',
                    esc_attr($this->type === self::TYPE_TEXT ? 'text' : $this->type),
                    $id,
                    esc_attr((string) $current)
                );
                break;
        }

        if ($this->description !== '') {
            printf('<p class="description">%s</p>', esc_html($this->description));
        }
    }

    /**
     * Media-Feld: versteckte Attachment-ID + Vorschau + wp.media-Picker-Buttons.
     * Die JS-Mechanik (data-rhbp-media) kommt aus dem Core-Settings-Script.
     *
     * @param mixed $current Attachment-ID (bevorzugt) oder Legacy-Bild-URL.
     */
    private function renderMedia(string $id, mixed $current): void
    {
        $stored = trim((string) $current);
        $previewUrl = '';

        if (ctype_digit($stored)) {
            $src = wp_get_attachment_image_url((int) $stored, 'medium');
            $previewUrl = is_string($src) ? $src : '';
        } elseif ($stored !== '') {
            $previewUrl = $stored; // Legacy-URL
        }

        $hasImage = $previewUrl !== '';

        echo '<div class="rhbp-media" data-rhbp-media>';
        printf(
            '<input type="hidden" id="%1$s" name="%1$s" value="%2$s" data-rhbp-media-input />',
            esc_attr($id),
            esc_attr($stored)
        );
        printf(
            '<img src="%1$s" alt="" data-rhbp-media-preview style="max-width:160px;height:auto;display:block;margin:0 0 8px;border-radius:4px"%2$s />',
            esc_url($previewUrl),
            $hasImage ? '' : ' hidden'
        );
        printf(
            '<button type="button" class="button" data-rhbp-media-select>%s</button> ',
            esc_html__('Bild wählen', 'rh-blueprint-core')
        );
        printf(
            '<button type="button" class="button-link" data-rhbp-media-remove%2$s>%1$s</button>',
            esc_html__('Entfernen', 'rh-blueprint-core'),
            $hasImage ? '' : ' hidden'
        );
        echo '</div>';
    }
}
