<?php

declare(strict_types=1);

namespace API;

class AttrTypesManager {
    private $properties = [];

    public function __get(string $name): mixed {
        return $this->properties[$name] ?? null;
    }

    public function __set(string $name, mixed $value) {
        $this->properties[$name] = $value;
    }

    public function __construct(string $table_name = "model_attr_types") {


        $attrs_sql = \API\Configurator::$connection->query("SELECT * FROM ?name", $table_name );

        foreach ($attrs_sql as $attr) {
            $this->{$attr["text_id"]} = new AttrTypeItem($attr);
        }
    }

    public function get($property_name): AttrTypeItem {
        return $this->$property_name;
    }
}
