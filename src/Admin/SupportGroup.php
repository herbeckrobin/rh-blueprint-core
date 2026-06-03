<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Admin;

use RhBlueprint\Core\Settings\GroupInterface;
use RhBlueprint\Core\Settings\SettingField;

/**
 * Settings-Gruppe für die Support-Kontaktdaten. Liegt im Support-Tab und speist
 * das Dashboard-Widget (SupportWidget) sowie die Helper `rhbp_support_info()`.
 */
final class SupportGroup implements GroupInterface
{
    public function id(): string
    {
        return 'support_info';
    }

    public function tab(): string
    {
        return 'general';
    }

    public function title(): string
    {
        return __('Support-Informationen', 'rh-blueprint-core');
    }

    public function description(): string
    {
        return __('Kontaktdaten für Kunden die Hilfe brauchen. Werden im Dashboard-Widget und optional im Frontend angezeigt.', 'rh-blueprint-core');
    }

    public function fields(): array
    {
        return [
            new SettingField(
                id: 'name',
                type: SettingField::TYPE_TEXT,
                label: __('Name / Agentur', 'rh-blueprint-core'),
                description: __('Name des Ansprechpartners oder der Agentur.', 'rh-blueprint-core'),
                keywords: ['kontakt', 'agentur', 'entwickler'],
            ),
            new SettingField(
                id: 'role',
                type: SettingField::TYPE_TEXT,
                label: __('Rolle', 'rh-blueprint-core'),
                description: __('Funktion oder Position des Ansprechpartners (z.B. "Webentwickler").', 'rh-blueprint-core'),
                keywords: ['rolle', 'position', 'funktion', 'job'],
            ),
            new SettingField(
                id: 'email',
                type: SettingField::TYPE_EMAIL,
                label: __('Support-E-Mail', 'rh-blueprint-core'),
                description: __('Adresse für Supportanfragen.', 'rh-blueprint-core'),
                keywords: ['mail', 'kontakt', 'email'],
            ),
            new SettingField(
                id: 'calendar_url',
                type: SettingField::TYPE_URL,
                label: __('Termin-Kalender', 'rh-blueprint-core'),
                description: __('Link zu einem Buchungskalender (Cal.com, Calendly, etc.).', 'rh-blueprint-core'),
                keywords: ['termin', 'kalender', 'booking', 'cal', 'meeting'],
            ),
            new SettingField(
                id: 'website',
                type: SettingField::TYPE_URL,
                label: __('Webseite', 'rh-blueprint-core'),
                description: __('Link zur Agentur-Webseite oder zum Portfolio.', 'rh-blueprint-core'),
                keywords: ['website', 'url', 'homepage'],
            ),
            new SettingField(
                id: 'phone',
                type: SettingField::TYPE_TEXT,
                label: __('Telefon', 'rh-blueprint-core'),
                description: __('Telefonnummer für dringende Fälle (optional).', 'rh-blueprint-core'),
                keywords: ['telefon', 'nummer', 'phone'],
            ),
        ];
    }
}
