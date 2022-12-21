<?php

namespace API\Frontend;

class Setting {
    private $values = [];

    private static $attr_map = [ "int_val", "decimal_val", "short_text", "long_text", "datetime_val", "formatted_text", "boolean_val"];
    private $_context = null;

    function __construct() {
        global $context;
        $this->_context = /*(\Nette\Database\Table\Selection)*/ $context->table("frontend_setting");
        
        //$set_items = $context->table("frontend_setting"); //$connection->fetch("SELECT * FROM frontend_setting"); // \DB::query("SELECT * FROM frontend_setting");

        foreach ($this->_context as $si) {
            //$t = self::$attr_map[$si["type"]];
            //$this->values[$si["text_id"]] = new SettingItem($t, $si[$t], $si["text_id"], $si["name"], $si["type"]);
            $t = self::$attr_map[$si->type];
            $this->values[$si->text_id]  = new SettingItem($t, $si[$t], $si->text_id, $si->name, $si->type);
        }

    }

    public function getValue($attrname) {
        if (empty($this->values[$attrname])) return null;

        return $this->getItem($attrname)->getValue();
    }

    public function getItem($attrname): ?SettingItem {
        if (empty($this->values[$attrname])) return null;

        return $this->values[$attrname];
    }

    public function setValue($attrname, $value): bool {
        if (empty($this->values[$attrname])) return false;

        $this->values[$attrname]->setValue($value);

        return true;
    }

    public function getAllItems() {
        return array_values($this->values);
    }
}

class SettingItem {
    private $name;
    private $attrname;
    private $type;
    private $value;
    private $num_type;

    private static $input_type = [ "number", "number", "text", "textarea", "datetime", "textarea", "checkbox" ];

    public function __construct($type, $value, $attrname, $name, $num_type) {
        $this->type = $type;
        $this->value = $value;
        $this->attrname = $attrname;
        $this->name = $name;
        $this->num_type = $num_type;
    }

    public function getAttrName() { return $this->attrname; }
    public function getInputType() { return self::$input_type[$this->num_type];}
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getValue() { return $this->value; }
    public function setValue($value) {
        global $connection;
        //$affected_rows = $this->_context->where("text_id", $this->attrname)->update([ $this->type => $value ]);
        $res = $connection->query("UPDATE frontend_setting SET ", [ $this->type => $value ], " WHERE text_id = ?", $this->attrname);
        $affected_rows = $res->getRowCount();

        if ($affected_rows > 0) $this->value = $value;
    }

    public function toInt() {
        return intval($this->value);
    }

    public function toFormat($format = null) {
        if ($this->type == "datetime_val") {
            switch (strtolower($format)) {
                case "sql": return $this->value;
                case "timestamp": return strtotime($this->value);
                case "js_timestamp": return strtotime($this->value) * 1000;
                default: return date($format, strtotime($this->value));
            }
        }
        else if ($this->type == "decimal_val" || $this->type == "int_val") {
           /* if (empty($format)) $style = \NumberFormatter::DECIMAL; //return $this->value;
            else $style = empty($format["style"]) ? \NumberFormatter::DECIMAL : $format["style"];

            $f = new \NumberFormatter("cs-CZ", $style);

            if (is_array($format))
                foreach ($format as $key => $val) {
                    switch($key) {
                        case "style":
                            continue 2;
                    }

                    $f->setAttribute($key, $val);
                }

            return $f->format($this->value);*/
            return number_format($this->value,0, ',', ' ');
        }

        return $this->value;
    }
}