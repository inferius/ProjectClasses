<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
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
        global $connection;
        if (self::loadToMemcache()) {
            return;
        }

        self::$cahed = true;
        $list = $connection->query("SELECT fsld.content, fsld.short_text, fs.text_id, fs.id AS value FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", $_SESSION[SESS_LANG]["id"]);
        foreach ($list as $data) {
            $text = $data["short_text"];
            if (!empty($data["content"])) $text = $data["content"];
            self::$cache[$data["text_id"]] = $text;
        }
        //dump(self::$cache);
    }

    public static function getCacheValue($key) {
        global $memcache;
        global $config;
        if (!empty($memcache)) {
            return $memcache->get($config["localization"]["cache_prefix"] . ":" . $key);
        }
        else {
            return empty(self::$cache[$key]) ? null : self::$cache[$key];
        }
    }

    public static function loadToMemcache() {
        global $connection;
        global $memcache;
        global $config;


        if (!empty($memcache)) {
            $expire = 24 * 60 * 60;

            $loc = $memcache->get($config["localization"]["cache_prefix"]);
            //dumpe($loc);
            if (!empty($loc)) {
                self::$cahed = true;
                return true;
            }
            self::$cahed = true;

            $memcache->set($config["localization"]["cache_prefix"], strtotime("now"), 0, $expire);

            $list = $connection->query("SELECT fsld.content, fsld.short_text, fs.text_id, fs.id AS value FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", $_SESSION[SESS_LANG]["id"]);
            foreach ($list as $data) {
                $text = $data["short_text"];
                if (!empty($data["content"])) $text = $data["content"];
                $memcache->set($config["localization"]["cache_prefix"] . ":" . $data["text_id"], $text, 0, $expire);
            }
            //var_dump($memcache->get("localizations"));


            return true;
        }
        else return false;
    }
}

class L {
    public static function t($key, $def = "") { $k = Localization::getText($key, is_array($def) ? $def : null); if (empty($k) && !is_array($def)) return $def; return $k; }
    public static function pt($key, $def = "") { $k = Localization::getPlainText($key, is_array($def) ? $def : null); if (empty($k) && !is_array($def)) return $def; return $k; }

    public static function et($key) { echo Localization::getText($key); }
    public static function ept($key) { echo Localization::getPlainText($key); }
}