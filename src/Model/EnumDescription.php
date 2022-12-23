<?php

namespace API\Model;

class EnumDescription {
    private function __construct(string $text_id) {
        $enum_info = \API\Configurator::$connection->fetch("SELECT * FROM model_enums WHERE text_id = ?", $text_id);
        $enum_items = \API\Configurator::$connection->fetchAll("SELECT text_id, lockey as name FROM model_enums_item WHERE parent_id = ?", $enum_info["id"]);


    }

    public static function get(string $text_id): EnumDescription {
        $cache_key = "ModelEnumDescription[MODEL]:" . $text_id;

        if (!empty(\API\Configurator::$memcache)) {
            $cm = unserialize(\API\Configurator::$memcache->get($cache_key));
            if ($cm instanceof EnumDescription) return $cm;
            else {
                trigger_error("Deserialize EnumDescription not working", E_USER_WARNING);
            }
        }

        $cm = new EnumDescription($text_id);

        if (!empty(\API\Configurator::$memcache)) {
            \API\Configurator::$memcache->set($cache_key, serialize($cm));
        }

        return $cm;
    }
}
