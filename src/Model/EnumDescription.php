<?php

declare(strict_types=1);

namespace API\Model;

class EnumDescription {
    private function __construct(string $text_id) {
        $enum_info = \API\Configurator::$connection->fetch("SELECT * FROM model_enums WHERE text_id = ?", $text_id);
        $enum_items = \API\Configurator::$connection->fetchAll("SELECT text_id, lockey as name FROM model_enums_item WHERE parent_id = ?", $enum_info["id"]);


    }

    /**
     * Smaze popis vyctu z memcache (po zmene vyctu v administraci)
     * @param string $text_id
     * @return void
     */
    public static function forget(string $text_id): void {
        if (empty(\API\Configurator::$memcache)) return;

        \API\Configurator::$memcache->delete(\API\Configurator::cacheKey("ModelEnumDescription[MODEL]:" . $text_id));
    }

    public static function get(string $text_id): EnumDescription {
        $cache_key = \API\Configurator::cacheKey("ModelEnumDescription[MODEL]:" . $text_id);

        if (!empty(\API\Configurator::$memcache)) {
            $serialized = \API\Configurator::$memcache->get($cache_key);
            // prazdna cache (false) neni chyba - varovani jen u neciteleho obsahu
            if (!empty($serialized)) {
                $cm = unserialize($serialized);
                if ($cm instanceof EnumDescription) return $cm;
                else {
                    trigger_error("Deserialize EnumDescription not working", E_USER_WARNING);
                }
            }
        }

        $cm = new EnumDescription($text_id);

        if (!empty(\API\Configurator::$memcache)) {
            \API\Configurator::$memcache->set($cache_key, serialize($cm));
        }

        return $cm;
    }
}
