<?php

namespace API;

class FunctionCore {
    public static function inMemCache($cache_key) {
        if (!empty(\API\Configurator::$memcache)) {
            $m = \API\Configurator::$memcache->get($cache_key);
            return !empty($m);
        }

        return false;
    }

    public static function getMemCache($cache_key) {
        if (!empty(\API\Configurator::$memcache)) {
            $r = \API\Configurator::$memcache->get($cache_key);
            if (!empty($r)) return $r;
        }

        return null;
    }

    public static function setMemCache($cache_key, $value, $expirated = null) {
        if (!empty(\API\Configurator::$memcache)) {
            \API\Configurator::$memcache->set($cache_key, $value, null, $expirated);
        }
    }


    public static function getUploadedImg($id, $alt = "", $img_config = []) {
        $config = \API\Configurator::$config;

        $default_data = [
            "class_name" => null,
            "width" => null
        ];

        list("class_name" => $class_names, "width" => $width) = $img_config + $default_data;

        $key = "uploaded_file_picture_html:$id";

        if (!empty(self::getMemCache($key))) return self::getMemCache($key);

        $file = \API\Configurator::$connection->fetch("SELECT * FROM `fp_final_files` WHERE id = ?", $id);
        $default = "<img" . (!empty($class_names) ? " class='$class_names'" : "") . " src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' />";

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
            $sources[] = "<source type='" . (self::getImageMime($f_format)) . "' sizes='" . (join(", ", $sizes)) . "' srcset='" . (join(",", $srcset[$f_format])) . "' />";
        }

        $class_str = !empty($class_names) ? " class='$class_names'" : "";
        $p = "<picture>" . (join("", $sources)) . "<img{$class_str} src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' /></picture>";
        self::setMemCache($key, $p, strtotime("+5 days"));

        return $p;
    }

    public static function getImageMime($ext) {
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

    public static function getMimeTypeByPath($path) {
        $pi = pathinfo($path);
        $ext = $pi["extension"];

        $key = "mime_type:$ext";
        if (!empty(self::inMemCache($key))) return self::getMemCache($key);

        $mime = self::getImageMime($ext);

        if (empty($mime)) $mime = self::mime_content_type($path);

        if ($mime) {
            self::setMemCache($key, $mime);
        }

        return $mime;
    }

    public static function getUpladedImageLatte($id, $alt = "", $config = []) {
        /** @noinspection */
        return new \Latte\Runtime\Html(self::getUploadedImg($id, $alt, $config));
    }

    /**
     * Vrátí knihovnu pro manipulaci s obrázkem
     * @return \Imagine\Image\AbstractImagine
     */
    public static function getImageManipulationInstance(): \Imagine\Image\AbstractImagine {
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

    public static function getImageDriverInfo($throw = false) {

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

}