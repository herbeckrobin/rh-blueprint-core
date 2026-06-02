<?php

/**
 * Globale Helper des Core. Vom Bootstrap der Gewinner-Version geladen.
 * function_exists-Guards, da theoretisch mehrfach erreichbar.
 */

declare(strict_types=1);

if (! function_exists('rh_blueprint')) {
    /**
     * Zentraler Zugriff auf den Core-Singleton.
     * Über `rh_blueprint()->services()` finden sich die Plugins gegenseitig.
     */
    function rh_blueprint(): \RhBlueprint\Core\Core
    {
        return \RhBlueprint\Core\Core::instance();
    }
}

if (! function_exists('rhbp_support_info')) {
    /**
     * Zugriff auf die Support-Informationen aus den Settings.
     *
     * @param string|null $key Einzelner Feld-Key (name|role|email|calendar_url|website|phone) oder null für alles.
     * @return mixed Array aller Felder, Wert des gesuchten Keys, oder null wenn der Key nicht existiert.
     */
    function rhbp_support_info(?string $key = null): mixed
    {
        $data = (array) get_option(
            \RhBlueprint\Core\Settings\SettingsHub::optionName('support_info'),
            []
        );

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }
}

if (! function_exists('rhbp_setting')) {
    /**
     * Generischer Zugriff auf beliebige Setting-Gruppen.
     */
    function rhbp_setting(string $groupId, ?string $key = null, mixed $default = null): mixed
    {
        $data = (array) get_option(
            \RhBlueprint\Core\Settings\SettingsHub::optionName($groupId),
            []
        );

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }
}
