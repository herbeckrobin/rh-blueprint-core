<?php

declare(strict_types=1);

namespace RhBlueprint\Core;

/**
 * Dünner Wrapper um `wp_get_environment_type()` (WP-Core seit 5.5).
 *
 * Liefert die Grundlage für sichere Sync-Defaults: eine Produktiv-Site soll von
 * sich aus kein Sync-Ziel sein. Plugins lesen den Typ hier statt selbst zu raten.
 * Steuerbar über die Konstante `WP_ENVIRONMENT_TYPE` bzw. die Umgebungsvariable.
 */
final class Environment
{
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';
    public const DEVELOPMENT = 'development';
    public const LOCAL = 'local';

    public static function type(): string
    {
        if (function_exists('wp_get_environment_type')) {
            return (string) wp_get_environment_type();
        }

        return self::PRODUCTION;
    }

    public static function isProduction(): bool
    {
        return self::type() === self::PRODUCTION;
    }

    public static function isStaging(): bool
    {
        return self::type() === self::STAGING;
    }

    /**
     * Entwicklungs-Umgebung im weiteren Sinn (development oder local).
     */
    public static function isDevelopment(): bool
    {
        $type = self::type();

        return $type === self::DEVELOPMENT || $type === self::LOCAL;
    }
}
