<?php

declare(strict_types=1);

namespace API;

class Localization {

    private static bool $cahed = false;
    private static $cache = [];
    private static $memcache;

    /**
     * Preklad klice v aktualnim jazyce, null kdyz preklad chybi.
     * @param mixed $key klic prekladu (ze sablon muze prijit i cislo nebo null)
     * @param array<string, mixed>|null $params [ "params" => [ "jmeno" => hodnota ] ] nahradi "@jmeno" v textu
     */
    public static function getText($key, $params = null): ?string {
        return self::getKeyValue($key, $params);
    }

    /**
     * Preklad bez HTML znacek (krome <br>), prazdny retezec kdyz preklad chybi.
     * @param array<string, mixed>|null $params
     */
    public static function getPlainText($key, $params = null): string {
        return strip_tags(self::getKeyValue($key, $params) ?? "", "<br>");
    }

    /** @param array<string, mixed>|null $params */
    private static function getKeyValue($key, $params = null): ?string {
        if (self::$cahed === false) self::loadToCache();

        $val = self::getCacheValue((string)$key);
        // memcache vraci false, kdyz klic nema
        if (!is_string($val)) return null;

        if (!empty($params["params"]) && is_array($params["params"])) {
            foreach ($params["params"] as $mkey => $mval) {
                $val = str_replace("@$mkey", (string)$mval, $val);
            }
        }

        return $val;
    }

    public static function loadToCache(): void {
        if (self::loadToMemcache()) {
            return;
        }

        self::$cahed = true;

        $list = \API\Configurator::$connection->query("SELECT fsld.*, fs.text_id FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", \API\Configurator::$currentLanguageId);
        foreach ($list as $data) {
            $text = self::rowText($data);
            self::$cache[self::getKey($data["text_id"])] = $text;
        }
        //dump(self::$cache);
    }

    /**
     * Text prekladu z radku _mct_translate_lang_data. Preklad je v jednom sloupci content (MEDIUMTEXT);
     * short_text je stary sloupec pred migraci (2026-10-05_02 v adminu) - pouzije se, kdyz content chybi.
     */
    private static function rowText($data) {
        if (isset($data["content"]) && $data["content"] !== "") return $data["content"];
        return $data["short_text"] ?? null;
    }

    private static function getKey($key): string {
        return \API\Configurator::$localizationPrefix . ":" . \API\Configurator::locale() . ":" . $key;
    }

    public static function getCacheValue($key) {
        if (!empty(\API\Configurator::$memcache)) {
            return \API\Configurator::$memcache->get(self::getKey($key));
        }
        else {
            return empty(self::$cache[self::getKey($key)]) ? null : self::$cache[self::getKey($key)];
        }
    }

    public static function loadToMemcache(): bool {
        if (!empty(\API\Configurator::$memcache)) {
            $expire = 24 * 60 * 60;

            $loc = \API\Configurator::$memcache->get(\API\Configurator::$localizationPrefix . ":" . \API\Configurator::locale());
            //dumpe($loc);
            if (!empty($loc)) {
                self::$cahed = true;
                return true;
            }
            self::$cahed = true;

            \API\Configurator::$memcache->set(\API\Configurator::$localizationPrefix . ":" . \API\Configurator::locale(), strtotime("now"), 0, $expire);

            $list = \API\Configurator::$connection->query("SELECT fsld.*, fs.text_id FROM _mct_translate AS fs INNER JOIN (SELECT * FROM _mct_translate_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", \API\Configurator::$currentLanguageId);
            foreach ($list as $data) {
                $text = self::rowText($data);
                \API\Configurator::$memcache->set(self::getKey($data["text_id"]), $text, 0, $expire);
            }
            //var_dump($memcache->get("localizations"));


            return true;
        }
        else return false;
    }

    public static function clear(): void {
        self::$cahed = false;
        self::$cache = [];
        if (!empty(\API\Configurator::$memcache)) \API\Configurator::$memcache->flush();
    }
}

