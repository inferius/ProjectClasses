<?php

function inMemCache($cache_key) {
    if (!empty(\API\Configurator::$memcache)) {
        $m = \API\Configurator::$memcache->get($cache_key);
        return !empty($m);
    }

    return false;
}

function getMemCache($cache_key) {
    if (!empty(\API\Configurator::$memcache)) {
        $r = \API\Configurator::$memcache->get($cache_key);
        if (!empty($r)) return $r;
    }

    return null;
}

function setMemCache($cache_key, $value, $expirated = null) {
    if (!empty(\API\Configurator::$memcache)) {
        \API\Configurator::$memcache->set($cache_key, $value, null, $expirated);
    }
}


function getUploadedImg($id, $alt = "", $img_config = []) {
    $config = \API\Configurator::$config;

    $default_data = [
        "class_name" => null,
        "width" => null
    ];

    list("class_name" => $class_names, "width" => $width) = $img_config + $default_data;

    $key = "uploaded_file_picture_html:$id";

    if (!empty(getMemCache($key))) return getMemCache($key);

    $file = \API\Configurator::$connection->fetch("SELECT * FROM `fp_final_files` WHERE id = ?", $id);
    $default = "<img". (!empty($class_names) ? " class='$class_names'" : "") ." src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' />";

    if (empty($file["format_info"])) {
        return $default;
    }

    $finfo = json_decode($file["format_info"], true);

    if (empty($finfo) || empty($finfo["supported_width"])) {
        return $default;
    }

    $srcset = [];

    $swidth_list = $finfo["supported_width"];
    asort($swidth_list, SORT_NUMERIC);
    $sizes = [];
    $max_w = min($swidth_list);

    if (empty($width)) {
        foreach ($swidth_list as $w) {
            $w_m = intval($w * 1.55);
            //$sizes[] = $w == $max_w ? "{$w}px" : "(min-width: {$w}px) {$w_m}px";
            $sizes[] = "(min-width: {$w_m}px) {$w}px";
        }
        $sizes = array_reverse($sizes);
        $sizes[] = "100vw";
    }
    else {
        $sizes[] = $width;
    }

    foreach ($finfo["created_files"]["formats"] as $key => $val) {
        ksort($val, SORT_NUMERIC);
        $srcset[$key] = [];
        foreach ($val as $img_size => $one_info) {
            $img_size = intval($img_size * 0.9);
            $srcset[$key][] = $config["path"]["relative"]["uploaded"] . $one_info["relative"] . " {$img_size}w";
        }
    }

    $sources = [];

    foreach ($finfo["supported_formats"] as $f_format) {
        $sources[] = "<source type='".(getImageMime($f_format))."' sizes='" . (join(", ", $sizes)) . "' srcset='". (join(",", $srcset[$f_format])) ."' />";
    }

    $class_str = !empty($class_names) ? " class='$class_names'" : "";
    $p = "<picture>".(join("", $sources))."<img{$class_str} src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' /></picture>";
    setMemCache($key, $p, strtotime("+5 days"));

    return $p;
}

function getImageMime($ext) {
    $arr = [
        'avif' => 'image/avif',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jfif' => 'image/jpeg',

        'heif' => 'image/heif',
        'heic' => 'image/heic',

        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml'
    ];

    return $arr[$ext];

}

function getMimeTypeByPath($path) {
    $pi = pathinfo($path);
    $ext = $pi["extension"];

    $key = "mime_type:$ext";
    if (!empty(inMemCache($key))) return getMemCache($key);

    $mime = getImageMime($ext);

    if (empty($mime)) $mime = mime_content_type($path);

    if ($mime) {
        setMemCache($key, $mime);
    }

    return $mime;
}

function getUpladedImageLatte($id, $alt = "", $config = []) {
    /** @noinspection */
    return new \Latte\Runtime\Html(getUploadedImg($id, $alt, $config));
}

/**
 * Vrátí knihovnu pro manipulaci s obrázkem
 * @return \Imagine\Image\AbstractImagine
 */
function getImageManipulationInstance(): \Imagine\Image\AbstractImagine {
    if (class_exists("Imagick")) {
        return new \Imagine\Imagick\Imagine();
    }
    else if (class_exists("Gmagick")) {
        return new \Imagine\Gmagick\Imagine();
    }
    else {
        return new \Imagine\Gd\Imagine();
    }
}

function getImageDriverInfo($throw = false) {

    if (class_exists("Imagick")) {
        return \Imagine\Imagick\DriverInfo::get($throw);
    }
    else if (class_exists("Gmagick")) {
        return \Imagine\Gmagick\DriverInfo::get($throw);
    }
    else {
        return \Imagine\Gd\DriverInfo::get($throw);
    }

}


function getCMSVar($key) {
    $connection = \API\Configurator::$connection;
    $key_m = "cms_var_key:$key";
    if (getMemCache($key_m)) return getMemCache($key_m);

    $main = $connection->fetch("SELECT * FROM _mct_variables_to_cms WHERE text_id = ?", $key);
    if (empty($main)) return "";

    $lang_v = $connection->fetch("SELECT * FROM _mct_variables_to_cms_lang_data WHERE parent_id = ? AND lang_id = ?", $main["id"], \API\Configurator::$currentLangugageId);
    if (empty($lang_v)) {
        $lang_v = $connection->fetch("SELECT * FROM _mct_variables_to_cms_lang_data WHERE parent_id = ? AND lang_id = ?", $main["id"], 2);
    }
    if (empty($lang_v)) return "";
    setMemCache($key_m, $lang_v["short_text"], strtotime("+1 days"));

    return $lang_v["short_text"];
}

/**
 * Nahradí promenne v textu uvedene jako {$promenna} za hodnotu
 * @param $text string, ktery se ma projit
 * @param $params
 * @return array|string|string[]
 */
function replaceParamsInText($text, $params = []) {
    return preg_replace_callback('/{(?<main>\$(?<plain>[\w_\-]*))(?::(?<param>[\w_\-|:]*))?}/m', function ($matches) use ($params) {
        global $setting;
        $config = \API\Configurator::$config;
        $connection = \API\Configurator::$connection;

        if (!empty($matches)) {
            if ($matches["main"] == '$setting' && !empty($matches["param"])) return $setting->getValue($matches["param"]);
            else if ($matches["main"] == '$var' && !empty($matches["param"])) return getCMSVar($matches["param"]);
            else if ($matches["main"] == '$config' && !empty($matches["param"])) return array_get_value($config, $matches["param"], ":");
            else {
                if (!empty($params[$matches["plain"]])) return $params[$matches["plain"]];
            }
        }

        return "";
    }, $text);


}


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
