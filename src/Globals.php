<?php

declare(strict_types=1);

namespace API;

class Globals
{
    private static $vars = [];

    // Sets the global one time.
    public static function set($_name, $_value): void
    {
        /*if(array_key_exists($_name, self::$vars))
        {
            throw new \Exception('globals::set("' . $_name . '") - Argument already exists and cannot be redefined!');
        }
        else
        {*/
        self::$vars[$_name] = $_value;
        //}
    }

    // Get the global to use.
    public static function &get($_name)
    {
        if(array_key_exists($_name, self::$vars))
        {
            return self::$vars[$_name];
        }
        // chybejici klic: null (reference na promennou - funkce vraci referenci, vyraz by hodil Notice);
        // klic se nezaklada, is_set() dal vraci false
        $missing = null;
        return $missing;
    }

    public static function get_all() {
        return self::$vars;
    }

    public static function clear(): void {
        self::$vars = [];
    }

    public static function is_set($_name): bool {
        return array_key_exists($_name, self::$vars);
    }
}
