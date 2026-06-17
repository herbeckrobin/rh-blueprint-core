<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Settings;

/**
 * Geteilter Settings-Hub für die rh-blueprint Kollektion.
 *
 * Anders als der frühere Ein-Plugin-Aufbau (Groups per glob, Tabs hartcodiert)
 * melden hier mehrere Plugins ihre Tabs und Gruppen programmatisch an. Damit
 * teilen sich alle rh-Plugins EINE Settings-Page mit konsistenter Optik.
 *
 *   add_action('rh-blueprint/core/booted', function ($core) {
 *       $core->settings()->registerTab('tools', __('Tools', 'mein-plugin'), 20);
 *       $core->settings()->registerGroup(new MeineToolsGruppe());
 *   });
 *
 * Ein Tab kann auch ohne Gruppen existieren und nur über die Hooks
 * `rh-blueprint/settings/tab_content_after|before` mit eigenem Content gefüllt
 * werden (z.B. DB-Tools, Sync-Peers).
 *
 * Option-Konvention bleibt erhalten:
 *   - Eine Option pro Gruppe: `rhbp_settings_<group_id>` (Array).
 *   - Eine Settings-Option-Group pro Tab: `rh_blueprint_settings_<tab_id>`,
 *     damit ein Tab-Save nicht die Options anderer Tabs auf null setzt.
 */
final class SettingsHub
{
    public const OPTION_GROUP_PREFIX = 'rh_blueprint_settings_';
    public const OPTION_PREFIX = 'rhbp_settings_';

    /** @var array<int, GroupInterface> */
    private array $groups = [];

    /** @var array<string, array{label: string, priority: int}> */
    private array $tabs = [];

    public function boot(): void
    {
        add_action('admin_init', [$this, 'register']);
    }

    /**
     * Meldet einen Tab an. Niedrigere Priorität = weiter links.
     * Erneutes Anmelden derselben ID aktualisiert Label/Priorität.
     */
    public function registerTab(string $id, string $label, int $priority = 50): void
    {
        $id = sanitize_key($id);
        if ($id === '') {
            return;
        }

        $this->tabs[$id] = [
            'label' => $label,
            'priority' => $priority,
        ];
    }

    public function registerGroup(GroupInterface $group): void
    {
        $this->groups[] = $group;
    }

    /**
     * Tabs als id => label, sortiert nach Priorität.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        $tabs = $this->tabs;
        uasort($tabs, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(static fn (array $tab): string => $tab['label'], $tabs);
    }

    /**
     * @return array<int, GroupInterface>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public static function optionName(string $groupId): string
    {
        return self::OPTION_PREFIX . $groupId;
    }

    public static function fieldName(string $groupId, string $fieldId): string
    {
        return sprintf('%s[%s]', self::optionName($groupId), $fieldId);
    }

    public static function optionGroupForTab(string $tabId): string
    {
        return self::OPTION_GROUP_PREFIX . sanitize_key($tabId);
    }

    public function register(): void
    {
        foreach ($this->groups as $group) {
            $optionName = self::optionName($group->id());
            $section = 'rhbp_section_' . $group->id();
            $page = 'rh-blueprint-' . $group->tab();

            register_setting(
                self::optionGroupForTab($group->tab()),
                $optionName,
                [
                    'type' => 'array',
                    'sanitize_callback' => function (mixed $input) use ($group): array {
                        return $this->sanitizeGroup($group, $input);
                    },
                    'default' => [],
                ]
            );

            add_settings_section(
                $section,
                $group->title(),
                static function () use ($group, $optionName): void {
                    if ($group->description() !== '') {
                        printf('<p>%s</p>', esc_html($group->description()));
                    }

                    // Marker, damit das Options-Array auch dann im POST landet,
                    // wenn ALLE Checkboxen abgewählt sind. Ohne ihn fehlt der
                    // Array-Key komplett, die Sanitize bekommt null und gibt []
                    // zurück, wodurch die Gruppe beim nächsten Laden auf ihre
                    // Defaults zurückfällt statt "alles aus" zu speichern.
                    printf(
                        '<input type="hidden" name="%s[__present]" value="1" />',
                        esc_attr($optionName)
                    );
                },
                $page
            );

            $stored = (array) get_option($optionName, []);

            foreach ($group->fields() as $field) {
                add_settings_field(
                    $optionName . '_' . $field->id,
                    esc_html($field->label),
                    static function () use ($field, $optionName, $stored): void {
                        $value = $stored[$field->id] ?? $field->default;
                        $name = sprintf('%s[%s]', $optionName, $field->id);
                        echo '<div class="rhbp-field" data-search-index="' . esc_attr($field->searchIndex()) . '">';
                        $field->render($name, $value);
                        echo '</div>';
                    },
                    $page,
                    $section,
                    ['label_for' => $optionName . '_' . $field->id]
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeGroup(GroupInterface $group, mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $clean = [];

        foreach ($group->fields() as $field) {
            $raw = $input[$field->id] ?? null;

            if ($field->type === SettingField::TYPE_BOOLEAN) {
                $clean[$field->id] = ! empty($raw);
                continue;
            }

            $clean[$field->id] = $field->sanitize($raw);
        }

        return $clean;
    }
}
