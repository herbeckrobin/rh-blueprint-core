<?php

/**
 * Gemeinsame Grundlage für die Standalone-Tests des Core.
 *
 * Lädt die Core-Klassen und stellt die WordPress-Funktionen bereit, die sie
 * berühren. Bewusst nur die, die wirklich gebraucht werden: ein grosser
 * Nachbau von WordPress wäre ein zweites Stück Software, das selbst falsch
 * sein kann.
 *
 * `negotiation-test.php` nutzt diese Datei nicht, der prüft den Ladevorgang
 * selbst und muss dafür seine eigene Umgebung stellen.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'RhBlueprint\\Core\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $datei = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($datei)) {
        require_once $datei;
    }
});

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = ''): string
    {
        return esc_attr($text);
    }
}

if (! function_exists('number_format_i18n')) {
    function number_format_i18n(float $zahl, int $stellen = 0): string
    {
        return number_format($zahl, $stellen, ',', '.');
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $wert): mixed
    {
        return is_string($wert) ? stripslashes($wert) : $wert;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $cb, int $prio = 10, int $args = 1): void
    {
    }
}

/**
 * Kleiner Zähler für die Testdateien, damit jede dasselbe Muster nutzt.
 */
final class TestErgebnis
{
    private int $fehler = 0;

    public function pruefe(bool $bedingung, string $name, string $detail = ''): void
    {
        if ($bedingung) {
            echo "  ok   $name\n";

            return;
        }

        echo "  FEHL $name" . ($detail !== '' ? ": $detail" : '') . "\n";
        $this->fehler++;
    }

    public function abschluss(): never
    {
        if ($this->fehler > 0) {
            echo "\n{$this->fehler} Fehler.\n";
            exit(1);
        }

        echo "\nAlles gruen.\n";
        exit(0);
    }
}
