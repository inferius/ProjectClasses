<?php

class Globals
{
    private static $vars = array();

    // Sets the global one time.
    public static function set($_name, $_value)
    {
        /*if(array_key_exists($_name, self::$vars))
        {
            throw new Exception('globals::set("' . $_name . '") - Argument already exists and cannot be redefined!');
        }
        else
        {*/
        self::$vars[$_name] = $_value;
        //}
    }

    // Get the global to use.
    public static function get($_name)
    {
        if(array_key_exists($_name, self::$vars))
        {
            return self::$vars[$_name];
        }
        else
        {
            //throw new Exception('globals::get("' . $_name . '") - Argument does not exist in globals!');
        }
    }
}
