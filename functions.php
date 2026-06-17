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
     * Generischer Lesezugriff auf beliebige Setting-Gruppen.
     *
     * Liest die Option `rhbp_settings_<groupId>`. Wichtig: der zurückgegebene
     * Default ist der HIER übergebene Wert, NICHT der `default:` aus dem
     * SettingField. Solange die Gruppe nie gespeichert wurde, ist die Option
     * leer, darum beim Auslesen denselben Default übergeben wie im Feld steht.
     *
     * @param string      $groupId Gruppen-ID (z.B. 'hardening', 'seo_tech').
     * @param string|null $key     Feld-Key oder null für die ganze Gruppe.
     * @param mixed       $default Fallback wenn der Key fehlt.
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

if (! function_exists('rhbp_update_setting')) {
    /**
     * Schreibt einen einzelnen Setting-Wert in die Gruppe `rhbp_settings_<groupId>`.
     *
     * Gedacht für Provisioning/Code-Setup, damit der echte Optionsname nicht
     * geraten werden muss. Bestehende Werte der Gruppe bleiben erhalten (Merge).
     *
     * @return bool true, wenn der Wert geschrieben wurde (false nur, wenn der
     *              Wert bereits identisch war, siehe update_option()).
     */
    function rhbp_update_setting(string $groupId, string $key, mixed $value): bool
    {
        return rhbp_update_settings($groupId, [$key => $value]);
    }
}

if (! function_exists('rhbp_update_settings')) {
    /**
     * Schreibt mehrere Setting-Werte einer Gruppe in einem Rutsch (Bulk-Merge).
     *
     * Bestehende, nicht übergebene Keys bleiben unangetastet. Spart für
     * Provisioning die N Read/Write-Zyklen der Einzel-Variante.
     *
     * @param array<string, mixed> $values Feld-Key => Wert.
     */
    function rhbp_update_settings(string $groupId, array $values): bool
    {
        $optionName = \RhBlueprint\Core\Settings\SettingsHub::optionName($groupId);
        $current = (array) get_option($optionName, []);

        return update_option($optionName, array_merge($current, $values));
    }
}
