<?php

declare(strict_types=1);

namespace RhBlueprint\Core;

/**
 * Pause der Update-Prüfungen, solange GitHub das Kontingent verweigert.
 *
 * Ohne Token teilen sich alle Sites einer IP 60 Anfragen je Stunde. Ist das
 * Kontingent weg, antwortet GitHub mit 403, und jedes der Module fragt trotzdem
 * weiter, drei Anfragen pro Prüfung. Jede davon verlängert nur den Stau für die
 * anderen Sites auf derselben IP.
 *
 * Deshalb merkt sich die Site nach dem ersten Rate-Limit-403 den Zeitpunkt, zu
 * dem GitHub das Kontingent zurücksetzt, und bis dahin prüft kein Modul mehr.
 * Die Grenze von 60 hebt das nicht auf, dafür gibt es den Token
 * (siehe UpdateChecker::githubToken()).
 */
final class GitHubRateLimit
{
    private const TRANSIENT = 'rhbp_github_ratelimit_until';

    /** Längste Pause, falls GitHub einen unplausiblen Zeitpunkt nennt. */
    private const MAX_PAUSE = 3600;

    private static bool $registered = false;

    /**
     * Einmal je Anfrage, egal wie viele Module den Prüfer anmelden.
     *
     * @param string $owner GitHub-Konto, dessen Repos während der Pause gar nicht erst angefragt werden.
     */
    public static function register(string $owner): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_action('puc_api_error', [self::class, 'onApiError'], 10, 4);

        // Innerhalb einer laufenden Prüfung fragt die Bibliothek nach dem
        // ersten 403 noch Tags und Branch ab. Die fallen während der Pause weg.
        add_filter('pre_http_request', static function ($pre, $args, $url) use ($owner) {
            if ($pre !== false || ! is_string($url) || ! self::isPaused(time())) {
                return $pre;
            }

            if (! str_starts_with($url, 'https://api.github.com/repos/' . $owner . '/')) {
                return $pre;
            }

            return new \WP_Error('rhbp-github-ratelimit', 'GitHub-Kontingent erschöpft, Prüfung pausiert.');
        }, 10, 3);
    }

    /**
     * Rückruf für `puc_api_error`.
     *
     * @internal
     */
    public static function onApiError(mixed $error, mixed $response = null, mixed $url = null, mixed $slug = null): void
    {
        if (! is_array($response) || ! is_string($url) || ! str_starts_with($url, 'https://api.github.com/')) {
            return;
        }

        $headers = wp_remote_retrieve_headers($response);
        $headers = is_object($headers) && method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers;

        $until = self::pauseUntil((int) wp_remote_retrieve_response_code($response), $headers, time());

        if ($until !== null) {
            set_site_transient(self::TRANSIENT, $until, max(60, $until - time()));
        }
    }

    /**
     * Ist die Prüfung gerade pausiert?
     */
    public static function isPaused(int $now): bool
    {
        $until = function_exists('get_site_transient') ? get_site_transient(self::TRANSIENT) : false;

        return is_numeric($until) && (int) $until > $now;
    }

    /**
     * Bis wann pausiert wird, oder null, wenn die Antwort kein Rate-Limit ist.
     *
     * Ein 403 allein reicht nicht, den gibt es auch bei gesperrtem Repo. Erst
     * ein leeres Kontingent oder ein Retry-After macht es zum Rate-Limit.
     *
     * @param array<string, mixed> $headers Header-Namen in Kleinschreibung.
     */
    public static function pauseUntil(int $status, array $headers, int $now): ?int
    {
        if ($status !== 403 && $status !== 429) {
            return null;
        }

        $retryAfter = self::header($headers, 'retry-after');

        if ($retryAfter !== null && ctype_digit($retryAfter)) {
            return $now + min(self::MAX_PAUSE, max(60, (int) $retryAfter));
        }

        if (self::header($headers, 'x-ratelimit-remaining') !== '0') {
            return null;
        }

        $reset = self::header($headers, 'x-ratelimit-reset');

        if ($reset === null || ! ctype_digit($reset) || (int) $reset <= $now) {
            return $now + 60;
        }

        return min((int) $reset, $now + self::MAX_PAUSE);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $wert = $headers[$name] ?? null;

        if (is_array($wert)) {
            $wert = end($wert);
        }

        return is_scalar($wert) ? trim((string) $wert) : null;
    }
}
