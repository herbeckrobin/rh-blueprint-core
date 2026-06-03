<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\GroupInterface;
use RhBlueprint\Core\Settings\SettingField;

/**
 * Settings-Gruppe für optionale Eingriffe in den WordPress-Admin.
 *
 * Beide Schalter sind per Default AUS (opt-in): eine frische Installation lässt
 * das WordPress-Dashboard unangetastet und zeigt kein Support-Widget. Erst wer
 * es bewusst will, schaltet es hier an.
 */
final class AdminAreaGroup implements GroupInterface
{
    public const GROUP_ID = 'admin_area';
    public const FIELD_DASHBOARD_CLEANUP = 'enable_dashboard_cleanup';
    public const FIELD_SUPPORT_WIDGET = 'enable_support_widget';

    public function id(): string
    {
        return self::GROUP_ID;
    }

    public function tab(): string
    {
        return 'general';
    }

    public function title(): string
    {
        return __('Admin-Bereich', 'rh-blueprint-core');
    }

    public function description(): string
    {
        return __('Optionale Eingriffe in den WordPress-Admin. Beide sind standardmäßig aus und müssen bewusst aktiviert werden.', 'rh-blueprint-core');
    }

    public function fields(): array
    {
        return [
            new SettingField(
                id: self::FIELD_DASHBOARD_CLEANUP,
                type: SettingField::TYPE_BOOLEAN,
                label: __('Dashboard aufräumen', 'rh-blueprint-core'),
                description: __('Entfernt die WordPress-Standard-Widgets und das Willkommen-Panel vom Dashboard.', 'rh-blueprint-core'),
                default: false,
                keywords: ['dashboard', 'aufraeumen', 'cleanup', 'widgets', 'willkommen'],
            ),
            new SettingField(
                id: self::FIELD_SUPPORT_WIDGET,
                type: SettingField::TYPE_BOOLEAN,
                label: __('Support-Widget anzeigen', 'rh-blueprint-core'),
                description: __('Zeigt ein Dashboard-Widget mit deinen Support-Kontaktdaten und Schnellzugriffen.', 'rh-blueprint-core'),
                default: false,
                keywords: ['support', 'widget', 'dashboard', 'kontakt', 'hilfe'],
            ),
        ];
    }
}
