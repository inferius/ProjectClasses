<?php
/**
 * File for function when not required any part of framework, only plane php
 */


/**
 * Get a value from nested array based on path
 * @param array $array The array t o read
 * @param array|string $path Path to read item.subitem.subsubitem or ["item", "subitem", "subsubitem"]
 * @param string $separator Separator
 * @return mixed
 */
function array_get_value(array &$array, $path, $separator = '.')
{
    if (!is_array($path)) {
        $path = explode($separator, $path);
    }

    $ref = &$array;

    foreach ((array) $path as $parent) {
        if (is_array($ref) && array_key_exists($parent, $ref)) {
            $ref = &$ref[$parent];
        } else {
            return null;
        }
    }
    return $ref;
}

/**
 * Sets a value in a nested array based on path
 * @param array $array The array to modify
 * @param array|string $path
 * @param mixed $value
 * @param string $separator
 */

function array_set_value(array &$array, $path, $value, $separator = '.')
{
    if (!is_array($path)) {
        $path = explode($separator, (string) $path);
    }

    $ref = &$array;

    foreach ($path as $parent) {
        if (isset($ref) && !is_array($ref)) {
            $ref = array();
        }

        $ref = &$ref[$parent];
    }

    $ref = $value;
}

/**
 * @param array $array
 * @param array|string $path
 * @param string $separator
 */
function array_unset_value(&$array, $path, $separator = '.')
{
    if (!is_array($path)) {
        $path = explode($separator, $path);
    }

    $key = array_shift($path);

    if (empty($path)) {
        unset($array[$key]);
    } else {
        array_unset_value($array[$key], $path);
    }
}
