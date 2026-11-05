<?php

namespace API\Utils;

/**
 * Varovani E_USER_DEPRECATED u API, ktere project-classes 2.0 odstrani (seznam a nahrady: UPGRADE-2.0.md).
 * Kazde varovani jen jednou za pozadavek, aby se log nezahltil. Zapis do logu zavisi na obsluze chyb projektu
 * (Tracy, error_log) - E_USER_DEPRECATED je soucast E_ALL.
 */
final class Deprecation {
    /** @var array<string, bool> uz ohlasena varovani */
    private static $reported = [];

    /**
     * @param string $message co je zastarale a cim ho nahradit
     * @return void
     */
    public static function trigger(string $message): void {
        if (isset(self::$reported[$message])) return;
        self::$reported[$message] = true;

        trigger_error("project-classes: " . $message . " (odstraneno ve 2.0, viz UPGRADE-2.0.md)", E_USER_DEPRECATED);
    }

    /**
     * Zapomene ohlasena varovani (testy, dlouho bezici procesy)
     * @return void
     */
    public static function reset(): void {
        self::$reported = [];
    }
}
