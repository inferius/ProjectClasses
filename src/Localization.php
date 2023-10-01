<?php

class Localization {

    private static $cahed = false;
    private static $cache = [];
    private static $memcache;

    public static function getText($key, $params = null) {
        return self::getKeyValue($key, $params);
    }

    public static function getPlainText($key, $params = null) {
        return strip_tags(self::getKeyValue($key, $params), "<br>");
    }

    private static function getKeyValue($key, $params = null) {
        if (self::$cahed === false) self::loadToCache();
        
        $val = self::getCacheValue($key);

        if (!empty($params)) {
            if (!empty($params["params"]) && is_array($params["params"])) {
                foreach ($params["params"] as $mkey => $mval) {
                    $val = str_replace("@$mkey", $mval, $val);
                }
            }
        }

        return $val;
    }

    public static function loadToCache() {
        if (self::loadToMemcache()) {
            return;
        }

        self::$cahed = true;
        
        $list = \API\Configurator::$connection->query("SELECT fsld.content, fsld.short_text, fs.text_id, fs.id AS value FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", \API\Configurator::$currentLanguageId);
        foreach ($list as $data) {
            $text = $data["short_text"];
            if (!empty($data["content"])) $text = $data["content"];
            self::$cache[self::getKey($data["text_id"])] = $text;
        }
        //dump(self::$cache);
    }

    private static function getKey($key) {
        return \API\Configurator::$localizationPrefix . ":" . \API\Configurator::$locale . ":" . $key;
    }

    public static function getCacheValue($key) {
        if (!empty(\API\Configurator::$memcache)) {
            return \API\Configurator::$memcache->get(self::getKey($key));
        }
        else {
            return empty(self::$cache[self::getKey($key)]) ? null : self::$cache[self::getKey($key)];
        }
    }

    public static function loadToMemcache() {
        if (!empty(\API\Configurator::$memcache)) {
            $expire = 24 * 60 * 60;

            $loc = \API\Configurator::$memcache->get(\API\Configurator::$localizationPrefix . ":" . \API\Configurator::$locale);
            //dumpe($loc);
            if (!empty($loc)) {
                self::$cahed = true;
                return true;
            }
            self::$cahed = true;

            \API\Configurator::$memcache->set(\API\Configurator::$localizationPrefix . ":" . \API\Configurator::$locale, strtotime("now"), 0, $expire);

            $list = \API\Configurator::$connection->query("SELECT fsld.content, fsld.short_text, fs.text_id, fs.id AS value FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", \API\Configurator::$currentLanguageId);
            foreach ($list as $data) {
                $text = $data["short_text"];
                if (!empty($data["content"])) $text = $data["content"];
                \API\Configurator::$memcache->set(self::getKey($data["text_id"]), $text, 0, $expire);
            }
            //var_dump($memcache->get("localizations"));


            return true;
        }
        else return false;
    }

    public static function clear() {
        self::$cahed = false;
        self::$cache = [];
        \API\Configurator::$memcache->flush();
    }
}

