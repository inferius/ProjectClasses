<?php

namespace API;

/**
 * @deprecated use {@see \API\Model\ClassDescription} instead
 */
class ClassDescription {

    public static function get($class_text_id) {
        return getClassDescription($class_text_id);
    }
}


function getClassDescription($class_text_id) {
    $cache_key = "ModelClassDescription:" . $class_text_id;

    if (!empty(\API\Configurator::$memcache)) {
        $r = \API\Configurator::$memcache->get($cache_key);
        if (!empty($r)) return $r;
    }
    //var_dump($class_text_id);


    $class_info = \API\Configurator::$connection->fetch("SELECT * FROM model_classes WHERE text_id = ?", $class_text_id);
    //$attrs = \API\Configurator::$connection->query("SELECT mat.*, mca.attr_alias FROM model_attr_types AS mat INNER JOIN model_classes_attrs AS mca ON mat.text_id = mca.a_txt_id WHERE mca.c_txt_id = ?", $class_text_id);
    $attrs = \API\Configurator::$connection->query("SELECT mat.*, mca.attr_alias FROM model_attr_types AS mat INNER JOIN model_classes_attrs AS mca ON mat.text_id = mca.a_txt_id WHERE mca.c_txt_id = ?", $class_text_id);

    $data = [
        "name" => $class_info["class_name"],
        "text_id" => $class_info["text_id"],
        "table" => $class_info["table_name"],
        "attributes" => []
    ];

    foreach ($attrs as $attr) {
        $data["attributes"][$attr["attr_alias"]] = [
            "type" => $attr["data_type"],
            "subtype" => $attr["data_subtype"],
            "text_id" => $attr["text_id"],
            "alias" => $attr["attr_alias"],
            "description" => $attr,
            "specification" => null
        ];
        if ($attr["data_type"] == "class") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getClassDescription($attr["data_subtype"]);
        }
        else if ($attr["data_type"] == "enum") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getEnumDescription($attr["data_subtype"]);
        }
        else if ($attr["data_type"] == "file") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getFilePostprocess($class_info["text_id"], $attr["attr_alias"]);
        }
    }

    if (!empty(\API\Configurator::$memcache)) {
        \API\Configurator::$memcache->set($cache_key, $data);
    }

    return $data;
}

function getEnumDescription($enum_txt_id) {
    global $connection;

    $enum_info = \API\Configurator::$connection->fetch("SELECT * FROM model_enums WHERE text_id = ?", $enum_txt_id);

    $enum_items = \API\Configurator::$connection->fetchAll("SELECT text_id, lockey as name FROM model_enums_item WHERE parent_id = ?", $enum_info["id"]);

    return [
        "name" => $enum_info["name"],
        "text_id" => $enum_info["text_id"],
        "description" => $enum_info["description"],
        "items" => $enum_items
    ];
}

function getFilePostprocess($c_txt_id, $a_txt_id) {
    global $connection;

    //return \API\Configurator::$connection->fetchAll("SELECT fp.text_id, fp.method_body FROM model_classes_attrs_f_posprocess AS mcap INNER JOIN files_postprocess AS fp ON fp.text_id = mcap.pp_txt_id WHERE mcap.a_txt_id = ? AND mcap.c_txt_id = ?", $a_txt_id, $c_txt_id);
    return \API\Configurator::$connection->fetch("SELECT fpt.* FROM fp_files_postprocess_class_attr_posprocess AS fpc 
    INNER JOIN fp_files_postprocess_template AS fpt ON fpt.id = fpc.postprocess_id 
             WHERE fpc.class_name = ? AND fpc.attr_alias = ?", $c_txt_id, $a_txt_id);
}
