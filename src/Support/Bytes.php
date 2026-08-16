<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Support;

/**
 * PHP-Grenzwerte in Bytes umrechnen.
 *
 * Diese Umrechnung lag viermal in der Suite, und die Kopien sind nicht
 * austauschbar: drei geben für "kein Limit" eine 0 zurück, die vierte
 * PHP_INT_MAX. Wer die Kopien blind zusammenlegt, verdreht in einem Modul die
 * Bedeutung von unbegrenzt in ihr Gegenteil. In rh-sync entscheidet der Wert
 * darüber, ob ein Sync als zu gross abgelehnt wird: aus PHP_INT_MAX eine 0 zu
 * machen hiesse, jeden Lauf zu blockieren.
 *
 * Deshalb ist die Bedeutung hier ein Parameter und keine stille Annahme. Wer
 * `fromIni()` aufruft, sagt dazu, wofür er die Zahl braucht.
 */
final class Bytes
{
    /** Kein Limit heisst: nichts zu vergleichen, also 0. */
    public const UNLIMITED_ZERO = 0;

    /** Kein Limit heisst: alles passt, also der grösstmögliche Wert. */
    public const UNLIMITED_MAX = PHP_INT_MAX;

    /**
     * Wandelt eine Grössenangabe aus der php.ini ("256M", "1G", "-1") in Bytes.
     *
     * Nur "-1" bedeutet ausdrücklich "kein Limit" und bekommt den Wert aus
     * `$unlimited`. Eine leere Angabe heisst dagegen "nicht ermittelbar" und
     * gibt 0. Der Unterschied ist wichtig: aus einem unbekannten Wert ein
     * unbegrenztes zu machen hiesse, eine Warnung zu verschlucken, weil
     * niemand mehr etwas zu vergleichen hat. Eine geschriebene 0 bleibt
     * ebenfalls 0, so haben es alle vier bisherigen Kopien gehalten.
     *
     * @param int $unlimited Rückgabewert für "-1". Siehe die Konstanten oben.
     */
    public static function fromIni(string $value, int $unlimited = self::UNLIMITED_ZERO): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return $unlimited;
        }

        if ($value === '' || $value === '0') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    /**
     * Das Speicherlimit dieses Prozesses in Bytes.
     */
    public static function memoryLimit(int $unlimited = self::UNLIMITED_ZERO): int
    {
        return self::fromIni((string) ini_get('memory_limit'), $unlimited);
    }

    /**
     * Ist noch so viel Speicher frei, wie gebraucht wird?
     *
     * Ohne Limit immer ja. Sonst wird gegen den tatsächlich belegten Speicher
     * gerechnet, nicht gegen den von PHP gemeldeten Verbrauch ohne Zuschlag.
     */
    public static function hasHeadroom(int $needed): bool
    {
        $limit = self::memoryLimit();

        if ($limit === 0) {
            return true;
        }

        return ($limit - memory_get_usage(true)) > $needed;
    }

    /**
     * Als Megabyte mit einer Nachkommastelle, für die Anzeige.
     */
    public static function toMb(int $bytes): string
    {
        return number_format_i18n($bytes / 1024 / 1024, 1) . ' MB';
    }
}
