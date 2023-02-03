<?php

namespace API\Model;

use API\Exceptions\AttributeTypeNotFound;

class ClassDescription/* implements ArrayAccess*/ {

    /** @var string $name */
    private $name;
    /** @var string $text_id */
    private $text_id;
    /** @var TableClassInfo $table */
    private $table;
    /**
     * @var IAttributeInfo[]
     */
    private $attributes = [];

    /**
     * Vrátí název třídy
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Vrátí textový identifikátor
     * @return string
     */
    public function getTextId(): string {
        return $this->text_id;
    }

    /**
     * Vrátí informace o tabulkách
     * @return TableClassInfo
     */
    public function table(): TableClassInfo {
        return $this->table;
    }

    /**
     * Vrátí seznam atributu
     *
     * @return IAttributeInfo[]
     */
    public function getAttributes(): array {
        return array_values($this->attributes);
    }

    /**
     * Vrátí atribut
     * @param string $attrName
     * @return IAttributeInfo
     * @throws AttributeTypeNotFound
     */
    public function getAttributeInfo(string $attrName): IAttributeInfo {
        if (empty($this->attributes[$attrName])) {
            throw new \API\Exceptions\AttributeTypeNotFound("Attribute '$attrName' on class {$this->text_id}.");
        }
        return $this->attributes[$attrName];
    }


    private function __construct(string $text_id) {
        $class_info = \API\Configurator::$connection->fetch("SELECT * FROM model_classes WHERE text_id = ?", $text_id);
        $attrs = \API\Configurator::$connection->query("SELECT mat.*, mca.attr_alias FROM model_attr_types AS mat INNER JOIN model_classes_attrs AS mca ON mat.text_id = mca.a_txt_id WHERE mca.c_txt_id = ?", $text_id);

        $this->name = $class_info["class_name"];
        $this->text_id = $class_info["text_id"];
        $this->table = new TableClassInfo($class_info["table_name"]);
        $this->attributes = [];

        foreach ($attrs as $attr) {
            $a_class = new StandardAttribute($attr);
            $this->attributes[$a_class->getAlias()] = $a_class;


            if ($a_class->getType() === DataTypes::CLASSES) {
                $a_class->setSpecification($attr["data_subtype"] == $this->text_id ? $this : ClassDescription::get($attr["data_subtype"]));
            }
            else if ($a_class->getType() === DataTypes::ENUM) {
                $a_class->setSpecification(getEnumDescription_P($attr["data_subtype"]));
            }
            else if ($a_class->getType() === DataTypes::FILE) {
                $a_class->setSpecification(getFilePostprocess_P($class_info["text_id"], $attr["attr_alias"]));
            }
        }
    }

    public static function get(string $text_id): ClassDescription {
        $cache_key = "ModelClassDescription[MODEL]:" . $text_id;

        if (!empty(\API\Configurator::$memcache)) {
            $deserialized_data = \API\Configurator::$memcache->get($cache_key);
            if (!empty($deserialized_data)) {
                $cm = unserialize($deserialized_data);
                if ($cm instanceof ClassDescription) return $cm;
                else {
                    trigger_error("Deserialize ClassDescription not working", E_USER_WARNING);
                }
            }
        }

        $cm = new ClassDescription($text_id);

        if (!empty(\API\Configurator::$memcache)) {
            \API\Configurator::$memcache->set($cache_key, serialize($cm));
        }

        return $cm;
    }

    // Array access
    /*public function offsetExists(string $offset): bool {
        return !empty($this->attributes[$offset]);
    }

    public function offsetGet(string $offset): IAttributeInfo {
        return $this->attributes[$offset];
    }

    public function offsetSet(string $offset, IAttributeInfo $value): void {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(string $offset): void {
        unset($this->attributes[$offset]);
    }*/
}


function getEnumDescription_P($enum_txt_id) {
    $enum_info = \API\Configurator::$connection->fetch("SELECT * FROM model_enums WHERE text_id = ?", $enum_txt_id);

    $enum_items = \API\Configurator::$connection->fetchAll("SELECT text_id, lockey as name FROM model_enums_item WHERE parent_id = ?", $enum_info["id"]);

    return [
        "name" => $enum_info["name"],
        "text_id" => $enum_info["text_id"],
        "description" => $enum_info["description"],
        "items" => $enum_items
    ];
}

function getFilePostprocess_P($c_txt_id, $a_txt_id) {
    global $connection;

    //return \API\Configurator::$connection->fetchAll("SELECT fp.text_id, fp.method_body FROM model_classes_attrs_f_posprocess AS mcap INNER JOIN files_postprocess AS fp ON fp.text_id = mcap.pp_txt_id WHERE mcap.a_txt_id = ? AND mcap.c_txt_id = ?", $a_txt_id, $c_txt_id);
    return \API\Configurator::$connection->fetch("SELECT fpt.* FROM fp_files_postprocess_class_attr_posprocess AS fpc 
    INNER JOIN fp_files_postprocess_template AS fpt ON fpt.id = fpc.postprocess_id 
             WHERE fpc.class_name = ? AND fpc.attr_alias = ?", $c_txt_id, $a_txt_id);
}
